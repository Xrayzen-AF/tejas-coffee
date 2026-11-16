<?php

namespace Tests\Feature;

use App\Models\DetailPesanan;
use App\Models\Kategori;
use App\Models\Meja;
use App\Models\MetodePembayaran;
use App\Models\Pengguna;
use App\Models\Pesanan;
use App\Models\Produk;
use App\Models\Transaksi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pesanan tamu (id_pengguna NULL) harus tetap bisa dirender di semua layar
 * staf dan struknya bisa dibuka oleh tamu pemilik sesi.
 */
class GuestOrderRenderingTest extends TestCase
{
    use RefreshDatabase;

    private function pesananTamu(bool $lunas = true): Pesanan
    {
        $kategori = Kategori::create(['nama_kategori' => 'Kopi', 'status_aktif' => true]);
        $produk = Produk::create([
            'id_kategori' => $kategori->id_kategori,
            'kode_produk' => 'KP-'.uniqid(),
            'nama_produk' => 'Americano',
            'harga' => 18000,
            'stok' => 10,
            'status_tersedia' => true,
        ]);
        $meja = Meja::create(['nomor_meja' => 'M01', 'kapasitas' => 2, 'status' => 'kosong']);

        $pesanan = Pesanan::factory()->create([
            'id_pengguna' => null,
            'id_pelanggan' => null,
            'nama_pemesan' => 'Budi Tamu',
            'id_meja' => $meja->id_meja,
            'tipe_pesanan' => 'dine-in',
            'status_pesanan' => 'baru',
            'total_harga' => 18000,
        ]);

        DetailPesanan::create([
            'id_pesanan' => $pesanan->id_pesanan,
            'id_produk' => $produk->id_produk,
            'jumlah' => 1,
            'harga_satuan' => 18000,
            'subtotal' => 18000,
        ]);

        if ($lunas) {
            $metode = MetodePembayaran::create([
                'kode_metode' => 'QRIS',
                'nama_metode' => 'QRIS / Midtrans',
                'ada_kembalian' => false,
            ]);

            Transaksi::create([
                'id_pesanan' => $pesanan->id_pesanan,
                'id_metode' => $metode->id_metode,
                'subtotal' => 18000,
                'total_bayar' => 18000,
                'kembalian' => 0,
                'status_pembayaran' => 'lunas',
                'waktu_pembayaran' => now(),
            ]);
        }

        return $pesanan;
    }

    private function staf(string $peran): Pengguna
    {
        return Pengguna::factory()->create(['peran' => $peran]);
    }

    public function test_kds_barista_merender_pesanan_tamu(): void
    {
        $pesanan = $this->pesananTamu();

        $this->actingAs($this->staf('barista'))
            ->get(route('barista.dashboard'))
            ->assertOk()
            ->assertSee($pesanan->nomor_pesanan);
    }

    public function test_dashboard_kasir_merender_pesanan_tamu(): void
    {
        $this->pesananTamu();

        $this->actingAs($this->staf('kasir'))
            ->get(route('kasir.dashboard'))
            ->assertOk();
    }

    public function test_dashboard_admin_merender_pesanan_tamu(): void
    {
        $this->pesananTamu();

        $this->actingAs($this->staf('admin'))
            ->get(route('admin.dashboard'))
            ->assertOk();
    }

    public function test_laporan_penjualan_merender_pesanan_tamu(): void
    {
        $this->pesananTamu();

        $this->actingAs($this->staf('admin'))
            ->get(route('admin.sales.index'))
            ->assertOk();
    }

    public function test_manajemen_pesanan_admin_merender_pesanan_tamu(): void
    {
        $pesanan = $this->pesananTamu();

        $this->actingAs($this->staf('admin'))
            ->get(route('admin.orders.index'))
            ->assertOk()
            ->assertSee($pesanan->nomor_pesanan);
    }

    public function test_tamu_bisa_membuka_struk_pesanannya_sendiri(): void
    {
        $pesanan = $this->pesananTamu();

        $this->withSession(['tamu_pesanan' => [$pesanan->id_pesanan]])
            ->get(route('struk.show', $pesanan))
            ->assertOk()
            ->assertSee('Budi Tamu');
    }

    public function test_tamu_lain_tidak_bisa_membuka_struk(): void
    {
        $pesanan = $this->pesananTamu();

        $this->get(route('struk.show', $pesanan))->assertForbidden();
    }
}
