<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\SettingController;
use App\Models\Pengguna;
use App\Support\CafeGeofence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class GeofenceTest extends TestCase
{
    use RefreshDatabase;

    // Titik cafe uji (Monas, Jakarta) + koordinat di dalam/luar radius.
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

    private function simpanSetting(array $override = []): void
    {
        $settings = array_merge(SettingController::load(), [
            'geofence_enabled' => true,
            'cafe_latitude' => self::LAT,
            'cafe_longitude' => self::LNG,
            'radius_meter' => 150,
            // Default uji: batas akurasi diturunkan dari radius, bukan nilai
            // manual yang mungkin tersisa di store-settings.json mesin lokal.
            'max_accuracy_meter' => null,
        ], $override);

        File::put(
            storage_path('app/'.SettingController::FILE),
            json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
    }

    private function pelanggan(): Pengguna
    {
        return Pengguna::factory()->create(['peran' => 'pelanggan']);
    }

    public function test_geofence_tanpa_titik_cafe_tidak_menahan_pelanggan(): void
    {
        $this->assertFalse(CafeGeofence::active());

        $this->actingAs($this->pelanggan())
            ->get(route('pelanggan.dashboard'))
            ->assertOk();
    }

    public function test_pelanggan_wajib_verifikasi_lokasi_saat_geofence_aktif(): void
    {
        $this->simpanSetting();

        $this->actingAs($this->pelanggan())
            ->get(route('pelanggan.menu'))
            ->assertRedirect(route('pelanggan.lokasi'));
    }

    public function test_halaman_gerbang_gps_dirender(): void
    {
        $this->simpanSetting();

        $this->actingAs($this->pelanggan())
            ->get(route('pelanggan.lokasi'))
            ->assertOk()
            ->assertSee('Aktifkan GPS Sebelum Memesan')
            ->assertSee('radius 150 meter');
    }

    public function test_halaman_gerbang_dialihkan_saat_geofence_nonaktif(): void
    {
        $this->actingAs($this->pelanggan())
            ->get(route('pelanggan.lokasi'))
            ->assertRedirect(route('pelanggan.dashboard'));
    }

    public function test_verifikasi_di_dalam_radius_membuka_akses_dan_menyimpan_sesi(): void
    {
        $this->simpanSetting();
        $user = $this->pelanggan();

        $this->actingAs($user)
            ->post(route('pelanggan.lokasi.verifikasi'), [
                'latitude' => self::LAT + 0.0004, // ±44 m
                'longitude' => self::LNG,
                'accuracy' => 12,
            ])
            ->assertRedirect(route('pelanggan.dashboard'))
            ->assertSessionHas(CafeGeofence::SESSION_KEY);

        $this->actingAs($user)
            ->get(route('pelanggan.dashboard'))
            ->assertOk();
    }

    public function test_verifikasi_di_luar_radius_ditolak(): void
    {
        $this->simpanSetting();
        $user = $this->pelanggan();

        $this->actingAs($user)
            ->post(route('pelanggan.lokasi.verifikasi'), [
                'latitude' => self::LAT + 0.01, // ±1,1 km
                'longitude' => self::LNG,
                'accuracy' => 15,
            ])
            ->assertSessionMissing(CafeGeofence::SESSION_KEY)
            ->assertSessionHas('error');

        $this->actingAs($user)
            ->get(route('pelanggan.dashboard'))
            ->assertRedirect(route('pelanggan.lokasi'));
    }

    public function test_akurasi_gps_terlalu_rendah_ditolak(): void
    {
        $this->simpanSetting();

        $this->actingAs($this->pelanggan())
            ->post(route('pelanggan.lokasi.verifikasi'), [
                'latitude' => self::LAT,
                'longitude' => self::LNG,
                'accuracy' => 1500,
            ])
            ->assertSessionMissing(CafeGeofence::SESSION_KEY)
            ->assertSessionHas('error');
    }

    public function test_batas_akurasi_mengikuti_radius_cafe(): void
    {
        $this->simpanSetting(['radius_meter' => 150]);
        $this->assertEqualsWithDelta(52.5, CafeGeofence::maxAccuracy(), 0.001);
        $this->assertEqualsWithDelta(30.0, CafeGeofence::targetAccuracy(), 0.001);

        $this->simpanSetting(['radius_meter' => 20]);
        $this->assertEqualsWithDelta(25.0, CafeGeofence::maxAccuracy(), 0.001);
        $this->assertEqualsWithDelta(15.0, CafeGeofence::targetAccuracy(), 0.001);

        $this->simpanSetting(['radius_meter' => 1000]);
        $this->assertEqualsWithDelta(60.0, CafeGeofence::maxAccuracy(), 0.001);
        $this->assertEqualsWithDelta(30.0, CafeGeofence::targetAccuracy(), 0.001);
    }

    public function test_batas_akurasi_bisa_ditimpa_lewat_pengaturan(): void
    {
        $this->simpanSetting(['radius_meter' => 100, 'max_accuracy_meter' => 150]);
        $this->assertEqualsWithDelta(150.0, CafeGeofence::maxAccuracy(), 0.001);

        // Dikosongkan lagi -> kembali otomatis dari radius (35% x 100 m).
        $this->simpanSetting(['radius_meter' => 100, 'max_accuracy_meter' => null]);
        $this->assertEqualsWithDelta(35.0, CafeGeofence::maxAccuracy(), 0.001);
    }

    public function test_akurasi_wifi_diterima_bila_admin_melonggarkan_batas(): void
    {
        $this->simpanSetting(['radius_meter' => 100, 'max_accuracy_meter' => 150]);
        $user = $this->pelanggan();

        $this->actingAs($user)
            ->post(route('pelanggan.lokasi.verifikasi'), [
                'latitude' => self::LAT + 0.0002, // ±22 m
                'longitude' => self::LNG,
                'accuracy' => 113, // akurasi khas lokasi berbasis WiFi
            ])
            ->assertRedirect(route('pelanggan.dashboard'))
            ->assertSessionHas(CafeGeofence::SESSION_KEY);
    }

    public function test_hanya_separuh_akurasi_dipakai_sebagai_toleransi(): void
    {
        $this->assertEqualsWithDelta(70.0, CafeGeofence::effectiveDistance(100.0, 60.0), 0.001);
        $this->assertEqualsWithDelta(0.0, CafeGeofence::effectiveDistance(20.0, 60.0), 0.001);
    }

    public function test_akurasi_kasar_ditolak_walau_berada_di_titik_cafe(): void
    {
        $this->simpanSetting();
        $user = $this->pelanggan();

        $this->actingAs($user)
            ->postJson(route('pelanggan.lokasi.cek'), [
                'latitude' => self::LAT,
                'longitude' => self::LNG,
                'accuracy' => 80,
            ])
            ->assertStatus(422)
            ->assertJson(['ok' => false, 'reason' => 'accuracy'])
            ->assertJsonPath('max_accuracy', 53);

        $this->actingAs($user)
            ->post(route('pelanggan.lokasi.verifikasi'), [
                'latitude' => self::LAT,
                'longitude' => self::LNG,
                'accuracy' => 80,
            ])
            ->assertSessionMissing(CafeGeofence::SESSION_KEY)
            ->assertSessionHas('error');
    }

    public function test_pemindai_qr_meja_tetap_terbuka_saat_gps_belum_lolos(): void
    {
        $this->simpanSetting();

        // Jalur cadangan resmi: jangan ikut ditahan middleware geofence.
        $this->actingAs($this->pelanggan())
            ->get(route('pelanggan.meja.pindai'))
            ->assertOk();
    }

    public function test_gerbang_gps_menyediakan_tautan_kode_meja(): void
    {
        $this->simpanSetting();

        $this->actingAs($this->pelanggan())
            ->get(route('pelanggan.lokasi'))
            ->assertOk()
            ->assertSee('Verifikasi lewat QR meja / kode kasir')
            ->assertSee(route('pelanggan.meja.pindai'), false);
    }

    public function test_koordinat_wajib_numerik_dan_tidak_boleh_kosong(): void
    {
        $this->simpanSetting();
        $user = $this->pelanggan();

        $this->actingAs($user)
            ->post(route('pelanggan.lokasi.verifikasi'), ['accuracy' => 10])
            ->assertSessionHasErrors(['latitude', 'longitude']);

        $this->actingAs($user)
            ->post(route('pelanggan.lokasi.verifikasi'), [
                'latitude' => 'bukan-angka',
                'longitude' => 'bukan-angka',
                'accuracy' => 10,
            ])
            ->assertSessionHasErrors(['latitude', 'longitude']);

        $this->actingAs($user)
            ->post(route('pelanggan.lokasi.verifikasi'), [
                'latitude' => 95,
                'longitude' => 200,
                'accuracy' => 10,
            ])
            ->assertSessionHasErrors(['latitude', 'longitude']);

        $this->assertNull(session(CafeGeofence::SESSION_KEY));
    }

    public function test_watchdog_menolak_koordinat_tidak_valid_dengan_json(): void
    {
        $this->simpanSetting();

        $this->actingAs($this->pelanggan())
            ->postJson(route('pelanggan.lokasi.cek'), [
                'latitude' => 95,
                'longitude' => 200,
                'accuracy' => 10,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['latitude', 'longitude']);
    }

    public function test_haversine_akurat_untuk_jarak_pendek(): void
    {
        // 0,001 derajat lintang ≈ 111,2 meter.
        $this->assertEqualsWithDelta(111.2, CafeGeofence::distance(0.0, 0.0, 0.001, 0.0), 0.5);

        // Titik yang sama harus menghasilkan 0 (bukan NaN).
        $this->assertEqualsWithDelta(0.0, CafeGeofence::distance(self::LAT, self::LNG, self::LAT, self::LNG), 0.0001);
    }

    public function test_verifikasi_kedaluwarsa_setelah_ttl(): void
    {
        $this->simpanSetting();
        $user = $this->pelanggan();

        $this->actingAs($user)
            ->withSession([
                CafeGeofence::SESSION_KEY => [
                    'user_id' => $user->id_pengguna,
                    'latitude' => self::LAT,
                    'longitude' => self::LNG,
                    'accuracy' => 10,
                    'verified_at' => now()->subMinutes(CafeGeofence::TTL_MINUTES + 1)->timestamp,
                ],
            ])
            ->get(route('pelanggan.dashboard'))
            ->assertRedirect(route('pelanggan.lokasi'));
    }

    public function test_radius_diperkecil_membuat_sesi_lama_tidak_berlaku(): void
    {
        $this->simpanSetting(['radius_meter' => 50]);
        $user = $this->pelanggan();

        $this->actingAs($user)
            ->withSession([
                CafeGeofence::SESSION_KEY => [
                    'user_id' => $user->id_pengguna,
                    'latitude' => self::LAT + 0.001, // ±111 m
                    'longitude' => self::LNG,
                    'accuracy' => 5,
                    'verified_at' => now()->timestamp,
                ],
            ])
            ->get(route('pelanggan.dashboard'))
            ->assertRedirect(route('pelanggan.lokasi'));
    }

    public function test_verifikasi_yang_tidak_dicek_ulang_realtime_ditolak(): void
    {
        $this->simpanSetting();
        $user = $this->pelanggan();

        $this->actingAs($user)
            ->withSession([
                CafeGeofence::SESSION_KEY => [
                    'user_id' => $user->id_pengguna,
                    'latitude' => self::LAT,
                    'longitude' => self::LNG,
                    'accuracy' => 5,
                    'verified_at' => now()->subSeconds(CafeGeofence::FRESH_SECONDS + 30)->timestamp,
                ],
            ])
            ->get(route('pelanggan.dashboard'))
            ->assertRedirect(route('pelanggan.lokasi'));
    }

    public function test_watchdog_memperbarui_sesi_saat_masih_di_dalam_radius(): void
    {
        $this->simpanSetting();
        $user = $this->pelanggan();

        $this->actingAs($user)
            ->postJson(route('pelanggan.lokasi.cek'), [
                'latitude' => self::LAT + 0.0003,
                'longitude' => self::LNG,
                'accuracy' => 10,
            ])
            ->assertOk()
            ->assertJson(['ok' => true])
            ->assertSessionHas(CafeGeofence::SESSION_KEY);

        $this->actingAs($user)
            ->get(route('pelanggan.dashboard'))
            ->assertOk();
    }

    public function test_watchdog_mencabut_verifikasi_saat_pelanggan_keluar_radius(): void
    {
        $this->simpanSetting();
        $user = $this->pelanggan();

        $this->actingAs($user)
            ->withSession([
                CafeGeofence::SESSION_KEY => [
                    'user_id' => $user->id_pengguna,
                    'latitude' => self::LAT,
                    'longitude' => self::LNG,
                    'accuracy' => 5,
                    'verified_at' => now()->timestamp,
                ],
            ])
            ->postJson(route('pelanggan.lokasi.cek'), [
                'latitude' => self::LAT + 0.02, // ±2,2 km
                'longitude' => self::LNG,
                'accuracy' => 20,
            ])
            ->assertStatus(403)
            ->assertJson(['ok' => false, 'reason' => 'outside'])
            ->assertSessionMissing(CafeGeofence::SESSION_KEY)
            ->assertSessionHas('error');

        $this->actingAs($user)
            ->get(route('pelanggan.menu'))
            ->assertRedirect(route('pelanggan.lokasi'));
    }

    public function test_watchdog_dipasang_di_halaman_pelanggan_tapi_tidak_di_gerbang(): void
    {
        $this->simpanSetting();
        $user = $this->pelanggan();
        $sesi = [
            'user_id' => $user->id_pengguna,
            'latitude' => self::LAT,
            'longitude' => self::LNG,
            'accuracy' => 5,
            'verified_at' => now()->timestamp,
        ];

        $this->actingAs($user)
            ->withSession([CafeGeofence::SESSION_KEY => $sesi])
            ->get(route('pelanggan.dashboard'))
            ->assertOk()
            ->assertSee(route('pelanggan.lokasi.cek'), false);

        $this->actingAs($user)
            ->get(route('pelanggan.lokasi'))
            ->assertOk()
            ->assertDontSee(route('pelanggan.lokasi.cek'), false);
    }

    public function test_admin_bisa_menyimpan_titik_dan_radius_cafe(): void
    {
        $admin = Pengguna::factory()->create(['peran' => 'admin']);

        $this->actingAs($admin)
            ->put(route('admin.settings.geofence'), [
                'geofence_enabled' => 1,
                'cafe_latitude' => -6.2,
                'cafe_longitude' => 106.8,
                'radius_meter' => 200,
            ])
            ->assertSessionHasNoErrors();

        $saved = SettingController::load();
        $this->assertTrue((bool) $saved['geofence_enabled']);
        $this->assertSame(-6.2, (float) $saved['cafe_latitude']);
        $this->assertSame(200, (int) $saved['radius_meter']);
        $this->assertTrue(CafeGeofence::active());
    }

    public function test_geofence_tidak_bisa_diaktifkan_tanpa_titik_cafe(): void
    {
        $admin = Pengguna::factory()->create(['peran' => 'admin']);

        $this->actingAs($admin)
            ->put(route('admin.settings.geofence'), [
                'geofence_enabled' => 1,
                'cafe_latitude' => '',
                'cafe_longitude' => '',
                'radius_meter' => 150,
            ])
            ->assertSessionHasErrors('cafe_latitude');

        $this->assertFalse(CafeGeofence::active());
    }

    public function test_menyimpan_profil_toko_tidak_menghapus_setting_geofence(): void
    {
        $this->simpanSetting();
        $admin = Pengguna::factory()->create(['peran' => 'admin']);

        $this->actingAs($admin)
            ->put(route('admin.settings.update'), [
                'store_name' => 'Tejas Coffee',
                'branch' => 'Cabang Uji',
                'address' => 'Jl. Uji No. 1',
                'phone' => '021-000',
                'open_hour' => '07:00',
                'close_hour' => '21:00',
                'threshold_default_kg' => 5,
                'safety_cap_default_kg' => 25,
                'poin_per_10k' => 1,
                'biaya_layanan' => 0,
                'roastery_destination' => 'Roastery',
            ])
            ->assertSessionHasNoErrors();

        $saved = SettingController::load();
        $this->assertSame('Cabang Uji', $saved['branch']);
        $this->assertSame(self::LAT, (float) $saved['cafe_latitude']);
        $this->assertSame(150, (int) $saved['radius_meter']);
    }
}
