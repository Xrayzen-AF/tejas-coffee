<?php

namespace Tests\Feature;

use App\Models\Kategori;
use App\Models\Meja;
use App\Models\Pesanan;
use App\Models\Produk;
use App\Services\MejaQrService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pemilihan meja dikembalikan untuk pelanggan (termasuk tamu), status meja
 * bisa dilihat sebelum login, dan QR meja mengarah ke alamat yang bisa
 * dibuka dari HP.
 */
class TableSelectionAndQrTest extends TestCase
{
    use RefreshDatabase;

    private function produk(): Produk
    {
        $kategori = Kategori::create(['nama_kategori' => 'Kopi', 'status_aktif' => true]);

        return Produk::create([
            'id_kategori' => $kategori->id_kategori,
            'kode_produk' => 'KP-'.uniqid(),
            'nama_produk' => 'Americano',
            'deskripsi' => 'Espresso + air',
            'harga' => 18000,
            'stok' => 10,
            'status_tersedia' => true,
        ]);
    }

    public function test_keranjang_dan_checkout_meminta_scan_meja(): void
    {
        $produk = $this->produk();
        Meja::create(['nomor_meja' => 'M05', 'kapasitas' => 2, 'status' => 'kosong']);

        $this->post(route('pelanggan.keranjang.tambah', $produk->id_produk));

        $this->get(route('pelanggan.keranjang.index'))
            ->assertOk()
            ->assertSee('Meja belum terverifikasi')
            ->assertSee(route('pelanggan.meja.pindai'), false);

        $this->get(route('pelanggan.checkout'))
            ->assertOk()
            ->assertSee('Meja (untuk makan di tempat)')
            ->assertSee('wajib pindai QR meja');
    }

    public function test_dine_in_tanpa_scan_meja_ditolak(): void
    {
        $produk = $this->produk();
        $meja = Meja::create(['nomor_meja' => 'M03', 'kapasitas' => 4, 'status' => 'kosong']);

        $this->post(route('pelanggan.keranjang.tambah', $produk->id_produk));

        // Memilih meja manual (tanpa scan + PIN) tidak lagi cukup.
        $this->post(route('pelanggan.checkout.simpan'), [
            'tipe_pesanan' => 'dine-in',
            'nama_pemesan' => 'Budi Tamu',
            'id_meja' => $meja->id_meja,
        ])->assertRedirect(route('pelanggan.meja.pindai'))
            ->assertSessionHas('error');

        $this->assertSame(0, Pesanan::count());
        $this->assertSame('kosong', $meja->fresh()->status);
    }

    public function test_takeaway_tidak_menempel_meja_walau_meja_dipilih(): void
    {
        $produk = $this->produk();
        $meja = Meja::create(['nomor_meja' => 'M06', 'kapasitas' => 2, 'status' => 'kosong']);

        $this->post(route('pelanggan.keranjang.tambah', $produk->id_produk));

        $this->post(route('pelanggan.checkout.simpan'), [
            'tipe_pesanan' => 'takeaway',
            'nama_pemesan' => 'Budi Tamu',
            'id_meja' => $meja->id_meja,
        ])->assertRedirect();

        $this->assertNull(Pesanan::firstOrFail()->id_meja);
        $this->assertSame('kosong', $meja->fresh()->status);
    }

    public function test_checkout_tidak_lagi_menampilkan_daftar_meja(): void
    {
        $produk = $this->produk();
        Meja::create(['nomor_meja' => 'M01', 'kapasitas' => 2, 'status' => 'kosong']);
        Meja::create(['nomor_meja' => 'M07', 'kapasitas' => 4, 'status' => 'terisi']);

        $this->post(route('pelanggan.keranjang.tambah', $produk->id_produk));

        $this->get(route('pelanggan.checkout'))
            ->assertOk()
            ->assertDontSee('Meja M01')
            ->assertDontSee('Meja M07');
    }

    public function test_id_meja_liar_tanpa_scan_tetap_ditolak(): void
    {
        $produk = $this->produk();
        $mejaLain = Meja::create(['nomor_meja' => 'M09', 'kapasitas' => 2, 'status' => 'terisi']);

        $this->post(route('pelanggan.keranjang.tambah', $produk->id_produk));

        // Mengirim id_meja langsung (melewati UI) tidak bisa menggantikan scan + PIN.
        $this->post(route('pelanggan.checkout.simpan'), [
            'tipe_pesanan' => 'dine-in',
            'nama_pemesan' => 'Budi Tamu',
            'id_meja' => $mejaLain->id_meja,
        ])->assertRedirect(route('pelanggan.meja.pindai'))
            ->assertSessionHas('error');

        $this->assertSame(0, Pesanan::count());
        $this->assertSame('terisi', $mejaLain->fresh()->status);
    }

    public function test_meja_terkunci_untuk_pelanggan_pertama_yang_memesan(): void
    {
        $produk = $this->produk();
        $meja = Meja::create(['nomor_meja' => 'M10', 'kapasitas' => 2, 'status' => 'kosong']);

        $this->post(route('pelanggan.keranjang.tambah', $produk->id_produk));

        $this->withSession(['meja_kode' => [
            'id_meja' => $meja->id_meja,
            'user_id' => 0,
            'verified_at' => now()->timestamp,
        ]])->post(route('pelanggan.checkout.simpan'), [
            'tipe_pesanan' => 'dine-in',
            'nama_pemesan' => 'Budi Tamu',
            'id_meja' => $meja->id_meja,
        ])->assertRedirect();

        $this->assertSame('terisi', $meja->fresh()->status);

        // Tamu kedua (sesi berbeda, belum scan) tidak bisa memesan meja itu.
        $this->flushSession();
        $this->post(route('pelanggan.keranjang.tambah', $produk->id_produk));
        $this->post(route('pelanggan.checkout.simpan'), [
            'tipe_pesanan' => 'dine-in',
            'nama_pemesan' => 'Tamu Kedua',
            'id_meja' => $meja->id_meja,
        ])->assertRedirect(route('pelanggan.meja.pindai'))
            ->assertSessionHas('error');

        $this->assertSame(1, Pesanan::count());
    }

    public function test_meja_sendiri_dari_kode_kasir_tetap_bisa_dipakai(): void
    {
        $produk = $this->produk();
        $meja = Meja::create(['nomor_meja' => 'M11', 'kapasitas' => 2, 'status' => 'terisi']);

        $this->post(route('pelanggan.keranjang.tambah', $produk->id_produk));

        $this->withSession([
            'meja_kode' => [
                'id_meja' => $meja->id_meja,
                'user_id' => 0,
                'verified_at' => now()->timestamp,
            ],
        ])->post(route('pelanggan.checkout.simpan'), [
            'tipe_pesanan' => 'dine-in',
            'nama_pemesan' => 'Budi Tamu',
            'id_meja' => $meja->id_meja,
        ])->assertRedirect();

        $this->assertSame($meja->id_meja, Pesanan::firstOrFail()->id_meja);
    }

    public function test_meja_dari_scan_terkunci_di_keranjang_dan_checkout(): void
    {
        $produk = $this->produk();
        $meja = Meja::create(['nomor_meja' => 'M30', 'kapasitas' => 2, 'status' => 'terisi']);
        Meja::create(['nomor_meja' => 'M31', 'kapasitas' => 2, 'status' => 'kosong']);

        $this->withSession(['meja_kode' => [
            'id_meja' => $meja->id_meja,
            'user_id' => 0,
            'verified_at' => now()->timestamp,
        ]]);

        $this->post(route('pelanggan.keranjang.tambah', $produk->id_produk));

        $this->get(route('pelanggan.checkout'))
            ->assertOk()
            ->assertSee('Meja M30')
            ->assertSee('terkunci dari scan QR')
            ->assertDontSee('Meja M31')
            ->assertDontSee('Pilih meja');
    }

    public function test_pilihan_meja_lain_diabaikan_saat_meja_sudah_dari_scan(): void
    {
        $produk = $this->produk();
        $meja = Meja::create(['nomor_meja' => 'M32', 'kapasitas' => 2, 'status' => 'terisi']);
        $mejaLain = Meja::create(['nomor_meja' => 'M33', 'kapasitas' => 2, 'status' => 'kosong']);

        $this->withSession(['meja_kode' => [
            'id_meja' => $meja->id_meja,
            'user_id' => 0,
            'verified_at' => now()->timestamp,
        ]]);

        $this->post(route('pelanggan.keranjang.tambah', $produk->id_produk));

        // Kirim meja lain lewat form tipe: meja terkunci tidak berubah.
        $this->post(route('pelanggan.keranjang.tipe'), [
            'tipe_pesanan' => 'dine-in',
            'id_meja' => $mejaLain->id_meja,
        ])->assertRedirect(route('pelanggan.checkout'));

        $this->get(route('pelanggan.checkout'))
            ->assertSee('Meja M32')
            ->assertDontSee('Meja M33');

        // Checkout dengan id meja lain tetap memakai meja hasil scan.
        $this->post(route('pelanggan.checkout.simpan'), [
            'tipe_pesanan' => 'dine-in',
            'nama_pemesan' => 'Budi Tamu',
            'id_meja' => $mejaLain->id_meja,
            'metode' => 'tunai',
        ])->assertRedirect();

        $this->assertSame($meja->id_meja, Pesanan::firstOrFail()->id_meja);
        $this->assertSame('kosong', $mejaLain->fresh()->status);
    }

    public function test_status_meja_bisa_dibuka_tanpa_login(): void
    {
        Meja::create(['nomor_meja' => 'M01', 'kapasitas' => 2, 'status' => 'kosong']);

        $this->getJson(route('pelanggan.meja.status'))
            ->assertOk()
            ->assertJsonStructure(['tables']);
    }

    public function test_qr_meja_memakai_alamat_publik_yang_bisa_dibuka_dari_hp(): void
    {
        config(['app.public_url' => 'http://192.168.1.10:8000']);
        $meja = Meja::create(['nomor_meja' => 'M02', 'kapasitas' => 2, 'status' => 'kosong']);

        $url = app(MejaQrService::class)->urlScan($meja);

        $this->assertSame('http://192.168.1.10:8000/meja/'.$meja->qr_token, $url);
    }

    public function test_halaman_scan_meja_memperkenalkan_aplikasi_sebelum_login(): void
    {
        $meja = Meja::create(['nomor_meja' => 'M04', 'kapasitas' => 4, 'status' => 'kosong']);

        $this->get(route('meja.scan', ['meja' => $meja->qr_token]))
            ->assertOk()
            ->assertSee('Tentang Aplikasi')
            ->assertSee('Tejas Coffee')
            ->assertSee('Tanpa akun');
    }
}
