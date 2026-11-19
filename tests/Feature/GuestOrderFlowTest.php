<?php

namespace Tests\Feature;

use App\Models\Kategori;
use App\Models\Pesanan;
use App\Models\Produk;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Kebijakan: pengunjung tanpa akun HANYA boleh melihat menu. Semua langkah
 * pemesanan (kustomisasi, keranjang, checkout, status) wajib login.
 */
class GuestOrderFlowTest extends TestCase
{
    use RefreshDatabase;

    private function buatMenu(int $stok = 10): Produk
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

    public function test_tamu_tanpa_akun_bisa_membuka_menu(): void
    {
        $this->buatMenu();

        $this->get(route('pelanggan.menu'))
            ->assertOk()
            ->assertSee('Americano')
            ->assertSee('Masuk untuk memesan', false);
    }

    public function test_tamu_diarahkan_login_saat_membuka_kustomisasi(): void
    {
        $produk = $this->buatMenu();

        $this->get(route('pelanggan.menu.kustom', $produk->id_produk))
            ->assertRedirect(route('login'));
    }

    public function test_tamu_diarahkan_login_saat_menambah_ke_keranjang(): void
    {
        $produk = $this->buatMenu();

        $this->post(route('pelanggan.keranjang.tambah', $produk->id_produk))
            ->assertRedirect(route('login'));

        $this->assertNull(session('keranjang'));
    }

    public function test_tamu_diarahkan_login_saat_membuka_keranjang_dan_checkout(): void
    {
        $this->get(route('pelanggan.keranjang.index'))->assertRedirect(route('login'));
        $this->get(route('pelanggan.checkout'))->assertRedirect(route('login'));
    }

    public function test_tamu_tidak_bisa_membuat_pesanan(): void
    {
        $produk = $this->buatMenu();

        $this->post(route('pelanggan.checkout.simpan'), [
            'tipe_pesanan' => 'takeaway',
            'nama_pemesan' => 'Budi Tamu',
        ])->assertRedirect(route('login'));

        $this->assertSame(0, Pesanan::count());
        $this->assertSame(10, $produk->fresh()->stok);
    }

    public function test_tamu_tidak_bisa_melihat_status_pesanan(): void
    {
        $pesanan = Pesanan::factory()->create([
            'id_pengguna' => null,
            'nama_pemesan' => 'Budi Tamu',
            'status_pesanan' => 'baru',
            'total_harga' => 18000,
        ]);

        $this->get(route('pelanggan.status', $pesanan->nomor_pesanan))
            ->assertRedirect(route('login'));
    }
}
