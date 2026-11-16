<?php

namespace Tests\Feature;

use App\Models\DetailPesanan;
use App\Models\Kategori;
use App\Models\MetodePembayaran;
use App\Models\Pengguna;
use App\Models\Pesanan;
use App\Models\Produk;
use App\Models\Transaksi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReorderAndStatusLabelTest extends TestCase
{
    use RefreshDatabase;

    private function pelanggan(): Pengguna
    {
        return Pengguna::factory()->create([
            'peran' => 'pelanggan',
            'nomor_telepon' => '081234567890',
        ]);
    }

    private function produk(string $nama, int $stok = 10, int $harga = 18000): Produk
    {
        $kategori = Kategori::firstOrCreate(
            ['nama_kategori' => 'Kopi'],
            ['status_aktif' => true],
        );

        return Produk::create([
            'id_kategori' => $kategori->id_kategori,
            'kode_produk' => 'KP-'.uniqid(),
            'nama_produk' => $nama,
            'deskripsi' => 'Menu uji',
            'harga' => $harga,
            'stok' => $stok,
            'status_tersedia' => true,
        ]);
    }

    private function pesananLama(Pengguna $pengguna, array $item, string $status = 'selesai'): Pesanan
    {
        $pesanan = Pesanan::factory()->create([
            'id_pengguna' => $pengguna->id_pengguna,
            'tipe_pesanan' => 'takeaway',
            'status_pesanan' => $status,
            'total_harga' => collect($item)->sum(fn ($i) => $i['harga_satuan'] * $i['jumlah']),
        ]);

        foreach ($item as $i) {
            DetailPesanan::create([
                'id_pesanan' => $pesanan->id_pesanan,
                'id_produk' => $i['produk']->id_produk,
                'jumlah' => $i['jumlah'],
                'harga_satuan' => $i['harga_satuan'],
                'subtotal' => $i['harga_satuan'] * $i['jumlah'],
                'catatan_item' => $i['catatan'] ?? null,
            ]);
        }

        return $pesanan;
    }

    public function test_pesan_lagi_menyalin_item_pesanan_lama_ke_keranjang(): void
    {
        $pengguna = $this->pelanggan();
        $americano = $this->produk('Americano');
        $latte = $this->produk('Latte', 10, 25000);

        $pesanan = $this->pesananLama($pengguna, [
            ['produk' => $americano, 'jumlah' => 2, 'harga_satuan' => 18000],
            ['produk' => $latte, 'jumlah' => 1, 'harga_satuan' => 25000, 'catatan' => 'Less sugar'],
        ]);

        $this->actingAs($pengguna)
            ->get(route('pelanggan.riwayat'))
            ->assertOk()
            ->assertSee('Pesan lagi');

        $this->actingAs($pengguna)
            ->post(route('pelanggan.riwayat.pesan-lagi', $pesanan->nomor_pesanan))
            ->assertRedirect(route('pelanggan.keranjang.index'))
            ->assertSessionHas('success');

        $keranjang = session('keranjang');
        $this->assertCount(2, $keranjang);

        $jumlah = collect($keranjang)->keyBy('id_produk');
        $this->assertSame(2, $jumlah[$americano->id_produk]['jumlah']);
        $this->assertSame(1, $jumlah[$latte->id_produk]['jumlah']);
        $this->assertSame('Less sugar', $jumlah[$latte->id_produk]['opsi_text']);
        $this->assertSame(18000.0, (float) $jumlah[$americano->id_produk]['harga']);
    }

    public function test_pesan_lagi_menolak_pesanan_milik_orang_lain(): void
    {
        $lain = Pengguna::factory()->create(['peran' => 'pelanggan', 'nomor_telepon' => '081999999999']);
        $americano = $this->produk('Americano');
        $pesanan = $this->pesananLama($lain, [
            ['produk' => $americano, 'jumlah' => 1, 'harga_satuan' => 18000],
        ]);

        $this->actingAs($this->pelanggan())
            ->post(route('pelanggan.riwayat.pesan-lagi', $pesanan->nomor_pesanan))
            ->assertForbidden();

        $this->assertNull(session('keranjang'));
    }

    public function test_pesan_lagi_melewati_menu_yang_sudah_habis(): void
    {
        $pengguna = $this->pelanggan();
        $habis = $this->produk('Cold Brew', 0);
        $pesanan = $this->pesananLama($pengguna, [
            ['produk' => $habis, 'jumlah' => 1, 'harga_satuan' => 18000],
        ]);

        $this->actingAs($pengguna)
            ->post(route('pelanggan.riwayat.pesan-lagi', $pesanan->nomor_pesanan))
            ->assertRedirect(route('pelanggan.riwayat'))
            ->assertSessionHas('error');

        $this->assertEmpty(session('keranjang', []));
    }

    public function test_label_status_mengikuti_pembayaran_dan_progres(): void
    {
        $pengguna = $this->pelanggan();

        $belumBayar = $this->pesananLama($pengguna, [], 'baru');
        $this->assertSame('Menunggu Pembayaran', $belumBayar->labelStatus());

        $metode = MetodePembayaran::create([
            'kode_metode' => 'CASH',
            'nama_metode' => 'Tunai',
            'ada_kembalian' => true,
        ]);

        $dibayar = $this->pesananLama($pengguna, [], 'baru');
        Transaksi::create([
            'id_pesanan' => $dibayar->id_pesanan,
            'id_metode' => $metode->id_metode,
            'subtotal' => 0,
            'total_bayar' => 0,
            'kembalian' => 0,
            'status_pembayaran' => 'lunas',
            'waktu_pembayaran' => now(),
        ]);
        $this->assertSame('Dibayar', $dibayar->fresh()->labelStatus());

        $dimasak = $this->pesananLama($pengguna, [], 'diproses');
        $this->assertSame('Sedang Dibuat', $dimasak->labelStatus());

        $selesai = $this->pesananLama($pengguna, [], 'selesai');
        $this->assertSame('Selesai', $selesai->labelStatus());

        $batal = $this->pesananLama($pengguna, [], 'dibatalkan');
        $this->assertSame('Dibatalkan', $batal->labelStatus());
    }
}
