<?php

namespace Tests\Feature;

use App\Models\Kategori;
use App\Models\Meja;
use App\Models\Pelanggan;
use App\Models\Pengguna;
use App\Models\Pesanan;
use App\Models\Produk;
use App\Models\ReservasiMeja;
use App\Services\MejaQrService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pemilihan meja untuk pelanggan yang sudah login, status meja bisa dilihat
 * sebelum login, dan QR meja mengarah ke alamat yang bisa dibuka dari HP.
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

    /** Login sebagai pelanggan (pemesanan wajib akun). */
    private function pelanggan(): Pengguna
    {
        $pengguna = Pengguna::factory()->create([
            'peran' => 'pelanggan',
            'nomor_telepon' => '0812'.str_pad((string) random_int(0, 9999999), 7, '0', STR_PAD_LEFT),
            'nama_lengkap' => 'Budi Member',
        ]);

        $this->actingAs($pengguna);

        return $pengguna;
    }

    public function test_keranjang_dan_checkout_meminta_scan_meja(): void
    {
        $produk = $this->produk();
        $this->pelanggan();
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
        $this->pelanggan();
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
        $this->pelanggan();
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
        $this->pelanggan();
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
        $this->pelanggan();
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
        $pertama = $this->pelanggan();
        $meja = Meja::create(['nomor_meja' => 'M10', 'kapasitas' => 2, 'status' => 'kosong']);

        $this->post(route('pelanggan.keranjang.tambah', $produk->id_produk));

        $this->withSession(['meja_kode' => [
            'id_meja' => $meja->id_meja,
            'user_id' => $pertama->id_pengguna,
            'verified_at' => now()->timestamp,
        ]])->post(route('pelanggan.checkout.simpan'), [
            'tipe_pesanan' => 'dine-in',
            'id_meja' => $meja->id_meja,
        ])->assertRedirect();

        $this->assertSame('terisi', $meja->fresh()->status);

        // Pelanggan kedua (sesi berbeda, belum scan) tidak bisa memesan meja itu.
        $this->flushSession();
        $this->pelanggan();
        $this->post(route('pelanggan.keranjang.tambah', $produk->id_produk));
        $this->post(route('pelanggan.checkout.simpan'), [
            'tipe_pesanan' => 'dine-in',
            'id_meja' => $meja->id_meja,
        ])->assertRedirect(route('pelanggan.meja.pindai'))
            ->assertSessionHas('error');

        $this->assertSame(1, Pesanan::count());
    }

    public function test_meja_sendiri_dari_kode_kasir_tetap_bisa_dipakai(): void
    {
        $produk = $this->produk();
        $pengguna = $this->pelanggan();
        $meja = Meja::create(['nomor_meja' => 'M11', 'kapasitas' => 2, 'status' => 'terisi']);

        $this->post(route('pelanggan.keranjang.tambah', $produk->id_produk));

        $this->withSession([
            'meja_kode' => [
                'id_meja' => $meja->id_meja,
                'user_id' => $pengguna->id_pengguna,
                'verified_at' => now()->timestamp,
            ],
        ])->post(route('pelanggan.checkout.simpan'), [
            'tipe_pesanan' => 'dine-in',
            'id_meja' => $meja->id_meja,
        ])->assertRedirect();

        $this->assertSame($meja->id_meja, Pesanan::firstOrFail()->id_meja);
    }

    public function test_meja_dari_scan_terkunci_di_keranjang_dan_checkout(): void
    {
        $produk = $this->produk();
        $pengguna = $this->pelanggan();
        $meja = Meja::create(['nomor_meja' => 'M30', 'kapasitas' => 2, 'status' => 'terisi']);
        Meja::create(['nomor_meja' => 'M31', 'kapasitas' => 2, 'status' => 'kosong']);

        $this->withSession(['meja_kode' => [
            'id_meja' => $meja->id_meja,
            'user_id' => $pengguna->id_pengguna,
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
        $pengguna = $this->pelanggan();
        $meja = Meja::create(['nomor_meja' => 'M32', 'kapasitas' => 2, 'status' => 'terisi']);
        $mejaLain = Meja::create(['nomor_meja' => 'M33', 'kapasitas' => 2, 'status' => 'kosong']);

        $this->withSession(['meja_kode' => [
            'id_meja' => $meja->id_meja,
            'user_id' => $pengguna->id_pengguna,
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
            'id_meja' => $mejaLain->id_meja,
            'metode' => 'tunai',
        ])->assertRedirect();

        $this->assertSame($meja->id_meja, Pesanan::firstOrFail()->id_meja);
        $this->assertSame('kosong', $mejaLain->fresh()->status);
    }

    public function test_scan_meja_baru_mengalahkan_reservasi_lama_member(): void
    {
        $produk = $this->produk();
        $mejaLama = Meja::create(['nomor_meja' => 'M01', 'kapasitas' => 2, 'status' => 'terisi']);
        $mejaBaru = Meja::create(['nomor_meja' => 'M04', 'kapasitas' => 4, 'status' => 'kosong']);

        $pengguna = Pengguna::factory()->create([
            'peran' => 'pelanggan',
            'nomor_telepon' => '081200000021',
            'nama_lengkap' => 'Budi Member',
        ]);
        $member = Pelanggan::create([
            'nama_pelanggan' => 'Budi Member',
            'nomor_telepon' => '081200000021',
        ]);

        // Reservasi lama di meja 01 masih aktif (belum dilepas).
        ReservasiMeja::create([
            'id_meja' => $mejaLama->id_meja,
            'id_pelanggan' => $member->id_pelanggan,
            'kode_booking' => 'LAMA01',
            'status' => 'aktif',
            'dicek_in_pada' => now()->subMinutes(10),
            'berakhir_pada' => now()->addMinutes(60),
        ]);

        // Member memindai QR meja 04 dan memasukkan PIN kasir di meja itu.
        $this->actingAs($pengguna)
            ->withSession(['meja_kode' => [
                'id_meja' => $mejaBaru->id_meja,
                'user_id' => $pengguna->id_pengguna,
                'verified_at' => now()->timestamp,
            ]]);

        $this->post(route('pelanggan.keranjang.tambah', $produk->id_produk));

        $this->get(route('pelanggan.checkout'))
            ->assertOk()
            ->assertSee('Meja M04')
            ->assertDontSee('Meja M01');

        $this->post(route('pelanggan.checkout.simpan'), [
            'tipe_pesanan' => 'dine-in',
            'metode' => 'tunai',
        ])->assertRedirect();

        $this->assertSame($mejaBaru->id_meja, Pesanan::firstOrFail()->id_meja);
    }

    public function test_sesi_pin_meja_lain_bukan_bukti_verifikasi_meja_yang_dipindai(): void
    {
        $mejaLama = Meja::create(['nomor_meja' => 'M01', 'kapasitas' => 2, 'status' => 'terisi']);
        $mejaBaru = Meja::create(['nomor_meja' => 'M04', 'kapasitas' => 4, 'status' => 'kosong']);

        $pengguna = Pengguna::factory()->create([
            'peran' => 'pelanggan',
            'nomor_telepon' => '081200000022',
        ]);

        // Sesi PIN masih milik meja 01: halaman meja 04 tidak boleh mengaku
        // sudah terverifikasi, supaya pelanggan memasukkan PIN meja 04.
        $this->actingAs($pengguna)
            ->withSession(['meja_kode' => [
                'id_meja' => $mejaLama->id_meja,
                'user_id' => $pengguna->id_pengguna,
                'verified_at' => now()->timestamp,
            ]])
            ->get(route('meja.scan', ['meja' => $mejaBaru->qr_token]))
            ->assertOk()
            ->assertDontSee('Kehadiran Anda di meja ini sudah terverifikasi');

        $this->actingAs($pengguna)
            ->withSession(['meja_kode' => [
                'id_meja' => $mejaBaru->id_meja,
                'user_id' => $pengguna->id_pengguna,
                'verified_at' => now()->timestamp,
            ]])
            ->get(route('meja.scan', ['meja' => $mejaBaru->qr_token]))
            ->assertOk()
            ->assertSee('Kehadiran Anda di meja ini sudah terverifikasi');
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
