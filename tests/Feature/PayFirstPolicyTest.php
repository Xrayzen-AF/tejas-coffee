<?php

namespace Tests\Feature;

use App\Models\DetailPesanan;
use App\Models\Kategori;
use App\Models\Meja;
use App\Models\MetodePembayaran;
use App\Models\Pelanggan;
use App\Models\Pengguna;
use App\Models\Pesanan;
use App\Models\Produk;
use App\Models\ReservasiMeja;
use App\Models\Transaksi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Kebijakan bayar-di-muka (pay-first):
 * - dapur hanya menerima pesanan lunas,
 * - pesanan belum dibayar >15 menit dibatalkan otomatis,
 * - satu meja/sesi hanya boleh punya satu pesanan belum dibayar.
 */
class PayFirstPolicyTest extends TestCase
{
    use RefreshDatabase;

    private function pengguna(string $peran): Pengguna
    {
        return Pengguna::factory()->create(['peran' => $peran]);
    }

    private function pelanggan(): Pengguna
    {
        return Pengguna::factory()->create([
            'peran' => 'pelanggan',
            'nomor_telepon' => '081234567890',
        ]);
    }

    private function produk(int $stok = 10): Produk
    {
        $kategori = Kategori::create(['nama_kategori' => 'Kopi', 'status_aktif' => true]);

        return Produk::create([
            'id_kategori' => $kategori->id_kategori,
            'kode_produk' => 'KP-'.uniqid(),
            'nama_produk' => 'Americano',
            'deskripsi' => 'Espresso + air',
            'harga' => 18000,
            'stok' => $stok,
            'status_tersedia' => true,
        ]);
    }

    private function lunasi(Pesanan $pesanan): void
    {
        $metode = MetodePembayaran::firstOrCreate(
            ['kode_metode' => 'CASH'],
            ['nama_metode' => 'Tunai', 'ada_kembalian' => true],
        );

        Transaksi::create([
            'id_pesanan' => $pesanan->id_pesanan,
            'id_metode' => $metode->id_metode,
            'subtotal' => $pesanan->total_harga,
            'total_bayar' => $pesanan->total_harga,
            'kembalian' => 0,
            'status_pembayaran' => 'lunas',
            'waktu_pembayaran' => now(),
        ]);
    }

    public function test_kds_hanya_menampilkan_pesanan_yang_sudah_lunas(): void
    {
        $kasir = $this->pengguna('kasir');
        $barista = $this->pengguna('barista');

        $belumBayar = Pesanan::factory()->create([
            'id_pengguna' => $kasir->id_pengguna,
            'tipe_pesanan' => 'takeaway',
            'status_pesanan' => 'baru',
            'total_harga' => 18000,
        ]);

        $lunas = Pesanan::factory()->create([
            'id_pengguna' => $kasir->id_pengguna,
            'tipe_pesanan' => 'takeaway',
            'status_pesanan' => 'baru',
            'total_harga' => 22000,
        ]);
        $this->lunasi($lunas);

        $this->actingAs($barista)
            ->get(route('barista.dashboard'))
            ->assertOk()
            ->assertSee($lunas->nomor_pesanan)
            ->assertDontSee($belumBayar->nomor_pesanan);
    }

    public function test_pesanan_belum_dibayar_lebih_15_menit_dibatalkan_otomatis(): void
    {
        $kasir = $this->pengguna('kasir');
        $produk = $this->produk(10);

        $pesanan = Pesanan::factory()->create([
            'id_pengguna' => $kasir->id_pengguna,
            'tipe_pesanan' => 'takeaway',
            'status_pesanan' => 'baru',
            'total_harga' => 36000,
        ]);

        DetailPesanan::create([
            'id_pesanan' => $pesanan->id_pesanan,
            'id_produk' => $produk->id_produk,
            'jumlah' => 2,
            'harga_satuan' => 18000,
            'subtotal' => 36000,
        ]);

        // Stok sudah terpotong saat pesanan dibuat.
        $produk->decrement('stok', 2);
        $pesanan->forceFill(['waktu_pesan' => now()->subMinutes(20)])->save();

        Artisan::call('pesanan:batalkan-belum-bayar');

        $this->assertSame('dibatalkan', $pesanan->fresh()->status_pesanan);
        $this->assertSame(10, $produk->fresh()->stok);
    }

    public function test_pesanan_kedua_sebelum_dibayar_ditolak(): void
    {
        $produk = $this->produk();
        $pengguna = $this->pelanggan();
        $meja = Meja::create(['nomor_meja' => 'M05', 'kapasitas' => 2]);

        $member = Pelanggan::create([
            'nama_pelanggan' => $pengguna->nama_lengkap,
            'nomor_telepon' => $pengguna->nomor_telepon,
            'poin_loyalitas' => 0,
        ]);

        ReservasiMeja::create([
            'id_meja' => $meja->id_meja,
            'id_pelanggan' => $member->id_pelanggan,
            'kode_booking' => 'BK-M05',
            'status' => 'aktif',
            'dicek_in_pada' => now(),
            'berakhir_pada' => now()->addMinutes(90),
        ]);

        $this->actingAs($pengguna)
            ->post(route('pelanggan.keranjang.tambah', $produk->id_produk));

        $this->post(route('pelanggan.checkout.simpan'), ['tipe_pesanan' => 'dine-in'])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, Pesanan::count());

        // Pesanan kedua sebelum yang pertama dibayar harus ditolak.
        $this->post(route('pelanggan.keranjang.tambah', $produk->id_produk));

        $this->post(route('pelanggan.checkout.simpan'), ['tipe_pesanan' => 'dine-in'])
            ->assertRedirect(route('pelanggan.keranjang.index'))
            ->assertSessionHas('error');

        $this->assertSame(1, Pesanan::count());
    }
}
