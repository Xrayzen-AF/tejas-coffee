<?php

namespace Tests\Feature;

use App\Models\MetodePembayaran;
use App\Models\Pengguna;
use App\Models\Pesanan;
use App\Models\Transaksi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminOrdersFilterTest extends TestCase
{
    use RefreshDatabase;

    private function buatTransaksi(Pesanan $pesanan, MetodePembayaran $metode): void
    {
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

    public function test_admin_bisa_filter_status_dan_metode_pembayaran(): void
    {
        $admin = Pengguna::factory()->create(['peran' => 'admin']);
        $cash = MetodePembayaran::create(['kode_metode' => 'CASH', 'nama_metode' => 'Tunai', 'ada_kembalian' => true]);
        $qris = MetodePembayaran::create(['kode_metode' => 'QRIS', 'nama_metode' => 'QRIS', 'ada_kembalian' => false]);

        $selesaiCash = Pesanan::factory()->create(['status_pesanan' => 'selesai', 'total_harga' => 30000]);
        $this->buatTransaksi($selesaiCash, $cash);

        $selesaiQris = Pesanan::factory()->create(['status_pesanan' => 'selesai', 'total_harga' => 20000]);
        $this->buatTransaksi($selesaiQris, $qris);

        $batalQris = Pesanan::factory()->create(['status_pesanan' => 'dibatalkan', 'total_harga' => 15000]);
        $this->buatTransaksi($batalQris, $qris);

        // Filter hanya metode QRIS: 2 pesanan, pesanan batal tidak masuk total.
        $qrisSaja = $this->actingAs($admin)->get(route('admin.orders.index', [
            'metode' => $qris->id_metode,
        ]));
        $qrisSaja->assertOk();
        $this->assertSame(2, $qrisSaja->viewData('jumlahTerfilter'));
        $this->assertSame(20000.0, $qrisSaja->viewData('totalTerfilter'));
        $this->assertSame(2, $qrisSaja->viewData('pesanan')->count());

        // Status + metode: hanya pesanan QRIS yang selesai.
        $kombinasi = $this->actingAs($admin)->get(route('admin.orders.index', [
            'status' => 'selesai',
            'metode' => $qris->id_metode,
        ]));
        $kombinasi->assertOk();
        $this->assertSame(1, $kombinasi->viewData('jumlahTerfilter'));
        $this->assertSame(20000.0, $kombinasi->viewData('totalTerfilter'));
        $this->assertSame(1, $kombinasi->viewData('pesanan')->count());

        // Metode tak dikenal diabaikan, bukan error.
        $this->actingAs($admin)
            ->get(route('admin.orders.index', ['metode' => 999999]))
            ->assertOk()
            ->assertViewHas('jumlahTerfilter', 3);
    }
}
