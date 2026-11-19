<?php

namespace Tests\Feature;

use App\Models\Kategori;
use App\Models\Notifikasi;
use App\Models\Pengguna;
use App\Models\Produk;
use App\Models\StokBiji;
use App\Services\NotifikasiStok;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Notifikasi otomatis ke admin saat stok menu / bahan habis.
 */
class NotifikasiStokTest extends TestCase
{
    use RefreshDatabase;

    private function produk(int $stok, string $kode = 'KP-01'): Produk
    {
        $kategori = Kategori::firstOrCreate(['nama_kategori' => 'Kopi']);

        return Produk::create([
            'id_kategori' => $kategori->id_kategori,
            'kode_produk' => $kode,
            'nama_produk' => 'Es Kopi Susu',
            'harga' => 25000,
            'stok' => $stok,
            'status_tersedia' => true,
        ]);
    }

    private function bahan(float $sisa): StokBiji
    {
        return StokBiji::create([
            'nama_biji' => 'Susu UHT',
            'kategori' => 'Susu & Krimer',
            'satuan' => 'liter',
            'sisa_kg' => $sisa,
            'threshold_kg' => 2,
            'safety_cap_kg' => 10,
            'laju_konsumsi_kg_per_jam' => 1,
        ]);
    }

    public function test_stok_menu_habis_membuat_notifikasi_admin_sekali_saja(): void
    {
        $service = app(NotifikasiStok::class);
        $produk = $this->produk(0);

        $notifikasi = $service->periksaProduk($produk);
        $this->assertNotNull($notifikasi);
        $this->assertSame(Notifikasi::TIPE_STOK_PRODUK, $notifikasi->tipe);
        $this->assertStringContainsString('Es Kopi Susu', $notifikasi->judul);
        $this->assertFalse($notifikasi->dibaca);

        // Pemeriksaan berikutnya tidak membuat duplikat selama belum dibaca.
        $this->assertNull($service->periksaProduk($produk));
        $this->assertSame(1, Notifikasi::query()->count());
    }

    public function test_stok_menu_tersedia_lagi_menutup_notifikasi_habis(): void
    {
        $service = app(NotifikasiStok::class);
        $produk = $this->produk(0);
        $service->periksaProduk($produk);

        $produk->update(['stok' => 12]);
        $service->periksaProduk($produk->refresh());

        $this->assertSame(0, Notifikasi::query()->belumDibaca()->count());
    }

    public function test_stok_bahan_habis_membuat_notifikasi_admin(): void
    {
        $bahan = $this->bahan(0);

        $notifikasi = app(NotifikasiStok::class)->periksaBahan($bahan);

        $this->assertNotNull($notifikasi);
        $this->assertSame(Notifikasi::TIPE_STOK_BAHAN, $notifikasi->tipe);
        $this->assertDatabaseHas('notifikasi', [
            'id_stok_biji' => $bahan->id_stok_biji,
            'dibaca' => false,
        ]);
    }

    public function test_admin_melihat_jumlah_dan_daftar_notifikasi(): void
    {
        $admin = Pengguna::factory()->create(['peran' => 'admin']);
        app(NotifikasiStok::class)->periksaProduk($this->produk(0));

        $this->actingAs($admin)
            ->getJson(route('admin.notifikasi.poll'))
            ->assertOk()
            ->assertJsonPath('jumlah', 1)
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.judul', 'Stok habis: Es Kopi Susu');
    }

    public function test_admin_menandai_notifikasi_sudah_dibaca(): void
    {
        $admin = Pengguna::factory()->create(['peran' => 'admin']);
        $notifikasi = app(NotifikasiStok::class)->periksaProduk($this->produk(0));

        $this->actingAs($admin)
            ->patchJson(route('admin.notifikasi.baca', $notifikasi))
            ->assertOk();

        $this->assertTrue($notifikasi->refresh()->dibaca);
        $this->actingAs($admin)
            ->getJson(route('admin.notifikasi.poll'))
            ->assertJsonPath('jumlah', 0);
    }

    public function test_tandai_semua_dibaca_dari_lonceng(): void
    {
        $admin = Pengguna::factory()->create(['peran' => 'admin']);
        app(NotifikasiStok::class)->periksaProduk($this->produk(0));
        app(NotifikasiStok::class)->periksaBahan($this->bahan(0));

        $this->actingAs($admin)
            ->postJson(route('admin.notifikasi.baca-semua'))
            ->assertOk()
            ->assertJsonPath('jumlah', 0);

        $this->assertSame(0, Notifikasi::query()->belumDibaca()->count());
    }

    public function test_penyapuan_membuat_notifikasi_untuk_stok_yang_sudah_habis(): void
    {
        $produk = $this->produk(0);

        $jumlah = app(NotifikasiStok::class)->periksaSemua();

        $this->assertSame(1, $jumlah);
        $this->assertDatabaseHas('notifikasi', [
            'id_produk' => $produk->id_produk,
            'tipe' => Notifikasi::TIPE_STOK_PRODUK,
        ]);

        // Penyapuan berikutnya tidak menduplikasi pada hari yang sama.
        $this->assertSame(0, app(NotifikasiStok::class)->periksaSemua());
    }

    public function test_polling_lonceng_langsung_menemukan_stok_habis_lalu_tidak_mengulang_setelah_dibaca(): void
    {
        $admin = Pengguna::factory()->create(['peran' => 'admin']);
        $this->produk(0);

        $this->actingAs($admin)
            ->getJson(route('admin.notifikasi.poll'))
            ->assertOk()
            ->assertJsonPath('jumlah', 1);

        $this->actingAs($admin)
            ->postJson(route('admin.notifikasi.baca-semua'))
            ->assertOk();

        // Masih hari yang sama: tidak muncul lagi hanya karena dibaca.
        $this->actingAs($admin)
            ->getJson(route('admin.notifikasi.poll'))
            ->assertJsonPath('jumlah', 0);
    }

    public function test_lonceng_notifikasi_tampil_di_dasbor_admin(): void
    {
        $admin = Pengguna::factory()->create(['peran' => 'admin']);
        app(NotifikasiStok::class)->periksaProduk($this->produk(0));

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('admin-notif-badge', false);
    }

    public function test_kasir_tidak_bisa_mengakses_notifikasi_admin(): void
    {
        $kasir = Pengguna::factory()->create(['peran' => 'kasir']);

        $this->actingAs($kasir)
            ->getJson(route('admin.notifikasi.poll'))
            ->assertForbidden();
    }
}
