<?php

namespace Tests\Feature;

use App\Models\Kategori;
use App\Models\Meja;
use App\Models\Pengguna;
use App\Models\Pesanan;
use App\Models\Produk;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Alur pelanggan hybrid: tamu tanpa akun bisa memesan, tetap wajib
 * mengisi nama, dan hanya bisa melihat pesanannya sendiri lewat sesi.
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

    private function masukkanKeranjang(Produk $produk): void
    {
        $this->post(route('pelanggan.keranjang.tambah', $produk->id_produk))->assertRedirect();
    }

    public function test_tamu_tanpa_akun_bisa_membuka_menu(): void
    {
        $this->buatMenu();

        $this->get(route('pelanggan.menu'))
            ->assertOk()
            ->assertSee('Americano');
    }

    public function test_tamu_wajib_mengisi_nama_saat_checkout(): void
    {
        $produk = $this->buatMenu();
        $this->masukkanKeranjang($produk);

        $this->post(route('pelanggan.checkout.simpan'), ['tipe_pesanan' => 'takeaway'])
            ->assertSessionHasErrors('nama_pemesan');

        $this->assertSame(0, Pesanan::count());
    }

    public function test_tamu_bisa_membuat_pesanan_dan_melihat_statusnya(): void
    {
        $produk = $this->buatMenu();
        $this->masukkanKeranjang($produk);

        $this->post(route('pelanggan.checkout.simpan'), [
            'tipe_pesanan' => 'takeaway',
            'nama_pemesan' => 'Budi Tamu',
        ])->assertRedirect();

        $pesanan = Pesanan::firstOrFail();
        $this->assertNull($pesanan->id_pengguna);
        $this->assertNull($pesanan->id_pelanggan);
        $this->assertSame('Budi Tamu', $pesanan->nama_pemesan);
        $this->assertSame(9, $produk->fresh()->stok);

        $this->get(route('pelanggan.status', $pesanan->nomor_pesanan))->assertOk();
    }

    public function test_tamu_tidak_bisa_melihat_pesanan_orang_lain(): void
    {
        $pesananLain = Pesanan::factory()->create([
            'id_pengguna' => Pengguna::factory()->create(['peran' => 'kasir'])->id_pengguna,
            'status_pesanan' => 'baru',
            'total_harga' => 18000,
        ]);

        $this->get(route('pelanggan.status', $pesananLain->nomor_pesanan))->assertForbidden();
    }

    public function test_dine_in_tamu_tanpa_meja_diarahkan_scan_qr(): void
    {
        $produk = $this->buatMenu();
        $this->masukkanKeranjang($produk);

        $this->post(route('pelanggan.checkout.simpan'), [
            'tipe_pesanan' => 'dine-in',
            'nama_pemesan' => 'Budi Tamu',
        ])->assertRedirect(route('pelanggan.meja.pindai'));

        $this->assertSame(0, Pesanan::count());
    }

    public function test_dine_in_tamu_memakai_meja_dari_kode_kasir(): void
    {
        $produk = $this->buatMenu();
        $meja = Meja::create(['nomor_meja' => 'M05', 'kapasitas' => 2, 'status' => 'kosong']);

        $this->masukkanKeranjang($produk);

        $this->withSession([
            'meja_kode' => [
                'id_meja' => $meja->id_meja,
                'user_id' => 0,
                'verified_at' => now()->timestamp,
            ],
        ])->get(route('pelanggan.checkout'))
            ->assertOk()
            ->assertSee('M05');

        $this->withSession([
            'meja_kode' => [
                'id_meja' => $meja->id_meja,
                'user_id' => 0,
                'verified_at' => now()->timestamp,
            ],
        ])->post(route('pelanggan.checkout.simpan'), [
            'tipe_pesanan' => 'dine-in',
            'nama_pemesan' => 'Budi Tamu',
        ])->assertRedirect();

        $pesanan = Pesanan::firstOrFail();
        $this->assertSame($meja->id_meja, $pesanan->id_meja);
        $this->assertSame('Budi Tamu', $pesanan->nama_pemesan);
    }
}
