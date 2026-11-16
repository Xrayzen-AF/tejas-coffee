<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\SettingController;
use App\Models\Kategori;
use App\Models\Meja;
use App\Models\Pengguna;
use App\Models\Pesanan;
use App\Models\Produk;
use App\Services\MejaKodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Kode sekali pakai per meja: penerbitan oleh staf, verifikasi oleh tamu,
 * sifat sekali pakai/kedaluwarsa, dan pembebasan dari gerbang GPS.
 */
class MejaKodeTest extends TestCase
{
    use RefreshDatabase;

    private const LAT = -6.1753924;

    private const LNG = 106.8271528;

    private ?string $backup = null;

    protected function setUp(): void
    {
        parent::setUp();

        $path = storage_path('app/'.SettingController::FILE);
        $this->backup = File::exists($path) ? File::get($path) : null;
    }

    protected function tearDown(): void
    {
        $path = storage_path('app/'.SettingController::FILE);

        if ($this->backup === null) {
            File::delete($path);
        } else {
            File::put($path, $this->backup);
        }

        parent::tearDown();
    }

    private function aktifkanGeofence(): void
    {
        $settings = array_merge(SettingController::load(), [
            'geofence_enabled' => true,
            'cafe_latitude' => self::LAT,
            'cafe_longitude' => self::LNG,
            'radius_meter' => 150,
        ]);

        File::put(
            storage_path('app/'.SettingController::FILE),
            json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
    }

    private function meja(string $nomor = 'M01'): Meja
    {
        return Meja::create(['nomor_meja' => $nomor, 'kapasitas' => 4, 'status' => 'kosong']);
    }

    private function staf(string $peran = 'kasir'): Pengguna
    {
        return Pengguna::factory()->create(['peran' => $peran]);
    }

    private function pelanggan(): Pengguna
    {
        return Pengguna::factory()->create(['peran' => 'pelanggan']);
    }

    private function menu(): Produk
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

    /** Terbitkan kode lewat endpoint kasir, kembalikan kodenya. */
    private function terbitkan(Meja $meja, ?Pengguna $staf = null): string
    {
        $res = $this->actingAs($staf ?? $this->staf())
            ->postJson(route('meja-kode.terbitkan', ['meja' => $meja->id_meja]))
            ->assertOk();

        $kode = $res->json('kode');
        $this->assertMatchesRegularExpression('/^\d{6}$/', (string) $kode);

        return $kode;
    }

    private function verifikasi(Meja $meja, string $kode, ?Pengguna $tamu = null)
    {
        return $this->actingAs($tamu ?? $this->pelanggan())
            ->post(route('meja.kode.verifikasi', ['meja' => $meja->qr_token]), [
                'kode_meja' => $kode,
            ]);
    }

    public function test_kasir_dan_barista_bisa_menerbitkan_kode(): void
    {
        $meja = $this->meja();

        $this->terbitkan($meja, $this->staf('kasir'));
        $this->terbitkan($meja, $this->staf('barista'));

        $this->actingAs($this->staf())
            ->get(route('meja-kode.index'))
            ->assertOk()
            ->assertSee('Kode Sekali Pakai per Meja');
    }

    public function test_halaman_kasir_menampilkan_status_meja_dan_pin_aktif(): void
    {
        $meja = $this->meja('M01');
        $mejaKosong = $this->meja('M02');
        $this->terbitkan($meja);

        $this->actingAs($this->staf())
            ->get(route('meja-kode.index'))
            ->assertOk()
            ->assertSee('PIN untuk tamu')
            ->assertSee('Meja M01')
            ->assertSee('Meja M02')
            ->assertSee('Buat PIN')
            ->assertViewHas('kodeAktif', fn ($peta) => $peta[$meja->id_meja] > 0
                && $peta[$mejaKosong->id_meja] === null);
    }

    public function test_pelanggan_minta_pin_lalu_kasir_mengirimkannya(): void
    {
        $meja = $this->meja();
        $staf = $this->staf();

        // Pelanggan menekan "Minta PIN ke Kasir".
        $this->postJson(route('meja.pin.minta', $meja->qr_token))
            ->assertOk()
            ->assertJsonPath('status', 'success');

        // Permintaan itu muncul di layar kasir.
        $this->actingAs($staf)
            ->getJson(route('meja-kode.status'))
            ->assertOk()
            ->assertJsonPath('tables.0.permintaan_detik', fn ($detik) => is_int($detik) && $detik >= 0);

        // Kasir menekan "Buat PIN" — PIN otomatis terkirim ke pelanggan.
        $kode = $this->terbitkan($meja, $staf);

        $this->getJson(route('meja.pin.status', $meja->qr_token))
            ->assertOk()
            ->assertJsonPath('status', 'siap')
            ->assertJsonPath('kode', $kode)
            ->assertJsonPath('sisa_detik', fn ($sisa) => $sisa > 0
                && $sisa <= MejaKodeService::KIRIM_TTL_DETIK);

        // PIN tetap tunduk pada verifikasi biasa (sekali pakai).
        $this->verifikasi($meja, $kode)->assertSessionHas('success');
    }

    public function test_pin_hanya_terlihat_oleh_sesi_yang_meminta(): void
    {
        $meja = $this->meja();
        $this->postJson(route('meja.pin.minta', $meja->qr_token))->assertOk();

        $kode = $this->terbitkan($meja);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $kode);

        // Perangkat lain tidak bisa membaca PIN tersebut.
        $this->flushSession();

        $this->getJson(route('meja.pin.status', $meja->qr_token))
            ->assertOk()
            ->assertJsonPath('status', 'tidak_ada')
            ->assertJsonMissingPath('kode');
    }

    public function test_minta_ulang_diizinkan_setelah_pin_kedaluwarsa(): void
    {
        $meja = $this->meja();
        $staf = $this->staf();

        $this->postJson(route('meja.pin.minta', $meja->qr_token))->assertOk();

        // Terlalu cepat minta lagi.
        $this->postJson(route('meja.pin.minta', $meja->qr_token))->assertStatus(429);

        $kode = $this->terbitkan($meja, $staf);
        $this->getJson(route('meja.pin.status', $meja->qr_token))
            ->assertJsonPath('kode', $kode);

        $this->travel(MejaKodeService::KIRIM_TTL_DETIK + 5)->seconds();

        // PIN hilang dari layar pelanggan...
        $this->getJson(route('meja.pin.status', $meja->qr_token))
            ->assertJsonPath('status', 'tidak_ada');

        // ...dan tombol "Minta ulang" bekerja.
        $this->postJson(route('meja.pin.minta', $meja->qr_token))
            ->assertOk()
            ->assertJsonPath('status', 'success');
    }

    public function test_kasir_bisa_membatalkan_pin_yang_aktif(): void
    {
        $meja = $this->meja();
        $kode = $this->terbitkan($meja);

        $this->actingAs($this->staf())
            ->deleteJson(route('meja-kode.batalkan', ['meja' => $meja->id_meja]))
            ->assertOk();

        $this->assertNull(Cache::get('meja-kode:'.$meja->id_meja));

        // Kode lama tidak berlaku lagi setelah dibatalkan.
        $this->verifikasi($meja, $kode)->assertSessionHasErrors('kode_meja');
    }

    public function test_pelanggan_tidak_boleh_membatalkan_pin(): void
    {
        $meja = $this->meja();
        $this->terbitkan($meja);

        $this->actingAs($this->pelanggan())
            ->deleteJson(route('meja-kode.batalkan', ['meja' => $meja->id_meja]))
            ->assertForbidden();

        $this->assertNotNull(Cache::get('meja-kode:'.$meja->id_meja));
    }

    public function test_status_meja_kasir_menyertakan_sisa_pin(): void
    {
        $meja = $this->meja();
        $this->terbitkan($meja);

        $this->actingAs($this->staf())
            ->getJson(route('meja-kode.status'))
            ->assertOk()
            ->assertJsonPath('tables.0.nomor_meja', 'M01')
            ->assertJsonPath('tables.0.status', 'kosong')
            ->assertJsonPath('tables.0.sisa_detik', fn ($sisa) => $sisa > 0);
    }

    public function test_pelanggan_tidak_boleh_menerbitkan_kode(): void
    {
        $meja = $this->meja();

        $this->actingAs($this->pelanggan())
            ->postJson(route('meja-kode.terbitkan', ['meja' => $meja->id_meja]))
            ->assertForbidden();
    }

    public function test_kode_benar_membuka_sesi_kehadiran(): void
    {
        $meja = $this->meja();
        $kode = $this->terbitkan($meja);
        $tamu = $this->pelanggan();

        $this->verifikasi($meja, $kode, $tamu)
            ->assertRedirect(route('meja.scan', ['meja' => $meja->qr_token]))
            ->assertSessionHas('success')
            ->assertSessionHas(MejaKodeService::SESSION_KEY);

        // Kode sudah terpakai: kunci cache dibersihkan.
        $this->assertNull(Cache::get('meja-kode:'.$meja->id_meja));
    }

    public function test_terlalu_banyak_percobaan_diberi_pesan_ramah(): void
    {
        $meja = $this->meja();
        $this->terbitkan($meja);

        $res = null;

        // Batas rute 15 percobaan/menit; percobaan ke-16 harus diblokir.
        for ($i = 0; $i < 16; $i++) {
            $res = $this->post(route('meja.kode.verifikasi', $meja->qr_token), [
                'kode_meja' => '000000',
            ]);
        }

        $res->assertRedirect()
            ->assertSessionHas('error');

        $this->assertStringContainsString('Terlalu banyak percobaan', (string) session('error'));
    }

    public function test_pin_yang_sudah_dipakai_tidak_tampil_lagi(): void
    {
        $meja = $this->meja();
        $this->postJson(route('meja.pin.minta', $meja->qr_token))->assertOk();

        $kode = $this->terbitkan($meja);

        $this->getJson(route('meja.pin.status', $meja->qr_token))
            ->assertJsonPath('kode', $kode);

        $this->verifikasi($meja, $kode)->assertSessionHas('success');

        // Setelah dipakai, PIN tidak boleh tampil lagi di layar pelanggan.
        $this->getJson(route('meja.pin.status', $meja->qr_token))
            ->assertJsonPath('status', 'tidak_ada');
    }

    public function test_sesi_pin_tidak_terbawa_ke_akun_lain(): void
    {
        $meja = $this->meja();
        $kode = $this->terbitkan($meja);
        $pemilik = $this->pelanggan();

        $this->verifikasi($meja, $kode, $pemilik)->assertSessionHas('success');

        // Akun lain memakai browser yang sama: meja tidak boleh ikut menjadi miliknya.
        $produk = $this->menu();
        $lain = Pengguna::factory()->create([
            'peran' => 'pelanggan',
            'nomor_telepon' => '081299990000',
        ]);

        $this->actingAs($lain)
            ->post(route('pelanggan.keranjang.tambah', $produk->id_produk))
            ->assertRedirect();

        $this->post(route('pelanggan.checkout.simpan'), [
            'tipe_pesanan' => 'dine-in',
            'metode' => 'tunai',
        ])->assertRedirect(route('pelanggan.meja.pindai'));

        $this->assertSame(0, Pesanan::count());
    }

    public function test_kode_salah_ditolak_dan_tidak_membuat_sesi(): void
    {
        $meja = $this->meja();
        $this->terbitkan($meja);

        $this->verifikasi($meja, '000000')
            ->assertSessionMissing(MejaKodeService::SESSION_KEY)
            ->assertSessionHasErrors('kode_meja');

        // Kode masih tersimpan setelah percobaan salah (masih di bawah batas).
        $this->assertNotNull(Cache::get('meja-kode:'.$meja->id_meja));
    }

    public function test_kode_hanya_bisa_dipakai_sekali(): void
    {
        $meja = $this->meja();
        $kode = $this->terbitkan($meja);

        $this->verifikasi($meja, $kode)->assertSessionHas('success');
        $this->verifikasi($meja, $kode)->assertSessionHasErrors('kode_meja');
    }

    public function test_kode_kedaluwarsa_setelah_tiga_menit(): void
    {
        $meja = $this->meja();
        $kode = $this->terbitkan($meja);

        $this->travel(MejaKodeService::TTL_DETIK + 5)->seconds();

        $this->verifikasi($meja, $kode)->assertSessionHasErrors('kode_meja');
    }

    public function test_terbit_ulang_membatalkan_kode_lama(): void
    {
        $meja = $this->meja();
        $lama = $this->terbitkan($meja);
        $baru = $this->terbitkan($meja);

        if ($lama !== $baru) {
            $this->verifikasi($meja, $lama)->assertSessionHasErrors('kode_meja');
        }

        $this->verifikasi($meja, $baru)->assertSessionHas('success');
    }

    public function test_sesi_kode_melewati_gerbang_gps(): void
    {
        $this->aktifkanGeofence();
        $meja = $this->meja();
        $kode = $this->terbitkan($meja);
        $tamu = $this->pelanggan();

        // Tanpa kode: gerbang GPS menahan.
        $this->actingAs($tamu)
            ->get(route('pelanggan.menu'))
            ->assertRedirect(route('pelanggan.lokasi'));

        $this->verifikasi($meja, $kode, $tamu);

        $this->actingAs($tamu)
            ->get(route('pelanggan.menu'))
            ->assertOk();
    }

    public function test_sesi_kode_kedaluwarsa_dan_gps_kembali_wajib(): void
    {
        $this->aktifkanGeofence();
        $meja = $this->meja();
        $kode = $this->terbitkan($meja);
        $tamu = $this->pelanggan();

        $this->verifikasi($meja, $kode, $tamu);

        $this->travel(MejaKodeService::SESI_MENIT + 1)->minutes();

        $this->actingAs($tamu)
            ->get(route('pelanggan.menu'))
            ->assertRedirect(route('pelanggan.lokasi'));
    }

    public function test_sesi_kode_milik_akun_lain_tidak_dipakai(): void
    {
        $this->aktifkanGeofence();
        $meja = $this->meja();
        $kode = $this->terbitkan($meja);

        $this->verifikasi($meja, $kode, $this->pelanggan());

        $this->actingAs($this->pelanggan())
            ->get(route('pelanggan.menu'))
            ->assertRedirect(route('pelanggan.lokasi'));
    }
}
