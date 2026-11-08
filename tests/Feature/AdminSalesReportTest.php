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

/**
 * Laporan penjualan admin: akses terbatas admin, filter periode, dan
 * angka ringkasan hanya dari transaksi lunas di rentang tanggal.
 */
class AdminSalesReportTest extends TestCase
{
    use RefreshDatabase;

    private function metodeCash(): MetodePembayaran
    {
        return MetodePembayaran::create([
            'kode_metode' => 'CASH',
            'nama_metode' => 'Tunai',
            'ada_kembalian' => true,
        ]);
    }

    private function buatTransaksi(Pesanan $pesanan, MetodePembayaran $metode, string $status, float $nominal, $waktu): Transaksi
    {
        return Transaksi::create([
            'id_pesanan' => $pesanan->id_pesanan,
            'id_metode' => $metode->id_metode,
            'subtotal' => $nominal,
            'total_bayar' => $nominal,
            'kembalian' => 0,
            'status_pembayaran' => $status,
            'waktu_pembayaran' => $waktu,
        ]);
    }

    public function test_admin_bisa_melihat_laporan_dengan_angka_dari_transaksi_lunas_pada_periode(): void
    {
        $admin = Pengguna::factory()->create(['peran' => 'admin']);
        $kasir = Pengguna::factory()->create(['peran' => 'kasir']);
        $metode = $this->metodeCash();

        $kategori = Kategori::create(['nama_kategori' => 'Kopi']);
        $produk = Produk::create([
            'id_kategori' => $kategori->id_kategori,
            'kode_produk' => 'KP-01',
            'nama_produk' => 'Es Kopi Susu',
            'harga' => 25000,
            'stok' => 50,
            'status_tersedia' => true,
        ]);

        // Lunas di dalam periode: 2 x 25.000 = 50.000.
        $lunas = Pesanan::factory()->create([
            'id_pengguna' => $kasir->id_pengguna,
            'status_pesanan' => 'selesai',
            'total_harga' => 50000,
        ]);
        DetailPesanan::create([
            'id_pesanan' => $lunas->id_pesanan,
            'id_produk' => $produk->id_produk,
            'jumlah' => 2,
            'harga_satuan' => 25000,
            'subtotal' => 50000,
        ]);
        $this->buatTransaksi($lunas, $metode, 'lunas', 50000, now());

        // Lunas di luar periode (10 hari lalu) — tidak boleh ikut dihitung.
        $lama = Pesanan::factory()->create([
            'id_pengguna' => $kasir->id_pengguna,
            'status_pesanan' => 'selesai',
            'total_harga' => 25000,
        ]);
        $this->buatTransaksi($lama, $metode, 'lunas', 25000, now()->subDays(10));

        // Gagal bayar di dalam periode — bukan omzet.
        $gagal = Pesanan::factory()->create([
            'id_pengguna' => $kasir->id_pengguna,
            'status_pesanan' => 'dibatalkan',
            'total_harga' => 40000,
        ]);
        $this->buatTransaksi($gagal, $metode, 'gagal', 40000, now());

        $response = $this->actingAs($admin)->get(route('admin.sales.cetak', [
            'dari' => today()->subDays(3)->toDateString(),
            'sampai' => today()->toDateString(),
        ]));

        $response->assertOk()
            ->assertSee('Laporan Penjualan')
            ->assertSee('Total Omzet')
            ->assertSee('Rp 50.000')
            ->assertSee('Es Kopi Susu')
            ->assertSee($lunas->nomor_pesanan)
            ->assertDontSee('Rp 65.000')
            ->assertDontSee($lama->nomor_pesanan)
            ->assertDontSee($gagal->nomor_pesanan);
    }

    public function test_hanya_admin_yang_bisa_membuka_laporan_penjualan(): void
    {
        $this->actingAs(Pengguna::factory()->create(['peran' => 'kasir']))
            ->get(route('admin.sales.cetak'))
            ->assertForbidden();

        $this->actingAs(Pengguna::factory()->create(['peran' => 'barista']))
            ->get(route('admin.sales.cetak'))
            ->assertForbidden();

        $this->actingAs(Pengguna::factory()->create(['peran' => 'pelanggan']))
            ->get(route('admin.sales.cetak'))
            ->assertForbidden();
    }

    public function test_tanggal_tidak_valid_dan_tertukar_ditangani_tanpa_error(): void
    {
        $admin = Pengguna::factory()->create(['peran' => 'admin']);

        $this->actingAs($admin)
            ->get(route('admin.sales.cetak', ['dari' => '2026-99-99', 'sampai' => 'bukan-tanggal']))
            ->assertOk()
            ->assertSee('Laporan Penjualan');

        $this->actingAs($admin)
            ->get(route('admin.sales.cetak', [
                'dari' => today()->toDateString(),
                'sampai' => today()->subDays(2)->toDateString(),
            ]))
            ->assertOk()
            ->assertSee('urutannya dibalik otomatis');
    }

    public function test_ekspor_csv_hanya_berisi_transaksi_lunas_pada_periode(): void
    {
        $admin = Pengguna::factory()->create(['peran' => 'admin']);
        $kasir = Pengguna::factory()->create(['peran' => 'kasir', 'nama_lengkap' => 'Kasir Satu']);
        $metode = $this->metodeCash();

        $lunas = Pesanan::factory()->create([
            'id_pengguna' => $kasir->id_pengguna,
            'status_pesanan' => 'selesai',
            'total_harga' => 50000,
        ]);
        $this->buatTransaksi($lunas, $metode, 'lunas', 50000, now());

        $lama = Pesanan::factory()->create([
            'id_pengguna' => $kasir->id_pengguna,
            'status_pesanan' => 'selesai',
            'total_harga' => 25000,
        ]);
        $this->buatTransaksi($lama, $metode, 'lunas', 25000, now()->subDays(10));

        $gagal = Pesanan::factory()->create([
            'id_pengguna' => $kasir->id_pengguna,
            'status_pesanan' => 'dibatalkan',
            'total_harga' => 40000,
        ]);
        $this->buatTransaksi($gagal, $metode, 'gagal', 40000, now());

        $dari = today()->subDays(3);
        $sampai = today();

        $response = $this->actingAs($admin)->get(route('admin.sales.cetak.csv', [
            'dari' => $dari->toDateString(),
            'sampai' => $sampai->toDateString(),
        ]));

        $response->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->assertDownload('laporan-transaksi-'.$dari->format('Ymd').'-'.$sampai->format('Ymd').'.csv');

        $csv = $response->streamedContent();

        $this->assertStringContainsString('No;Waktu;"No. Pesanan";Tipe', $csv);
        $this->assertStringContainsString($lunas->nomor_pesanan, $csv);
        $this->assertStringContainsString('Kasir Satu', $csv);
        $this->assertStringContainsString('TOTAL;;;;;;;50000', $csv);
        $this->assertStringNotContainsString($lama->nomor_pesanan, $csv);
        $this->assertStringNotContainsString($gagal->nomor_pesanan, $csv);
    }

    private function pesananLunas(Pengguna $kasir, MetodePembayaran $metode, string $tipe, float $nominal): Pesanan
    {
        $pesanan = Pesanan::factory()->create([
            'id_pengguna' => $kasir->id_pengguna,
            'tipe_pesanan' => $tipe,
            'status_pesanan' => 'selesai',
            'total_harga' => $nominal,
        ]);

        $this->buatTransaksi($pesanan, $metode, 'lunas', $nominal, now());

        return $pesanan;
    }

    public function test_filter_tipe_pesanan_menyaring_laporan_dan_csv(): void
    {
        $admin = Pengguna::factory()->create(['peran' => 'admin']);
        $kasir = Pengguna::factory()->create(['peran' => 'kasir']);
        $metode = $this->metodeCash();

        $dineIn = $this->pesananLunas($kasir, $metode, 'dine-in', 50000);
        $takeaway = $this->pesananLunas($kasir, $metode, 'takeaway', 25000);

        $this->actingAs($admin)
            ->get(route('admin.sales.cetak', ['tipe' => 'dine-in']))
            ->assertOk()
            ->assertSee('Filter: Dine-in')
            ->assertSee('Rp 50.000')
            ->assertSee($dineIn->nomor_pesanan)
            ->assertDontSee($takeaway->nomor_pesanan);

        $csv = $this->actingAs($admin)
            ->get(route('admin.sales.cetak.csv', ['tipe' => 'dine-in']))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString($dineIn->nomor_pesanan, $csv);
        $this->assertStringNotContainsString($takeaway->nomor_pesanan, $csv);
    }

    public function test_filter_metode_pembayaran_menyaring_laporan_dan_csv(): void
    {
        $admin = Pengguna::factory()->create(['peran' => 'admin']);
        $kasir = Pengguna::factory()->create(['peran' => 'kasir']);
        $tunai = $this->metodeCash();
        $qris = MetodePembayaran::create([
            'kode_metode' => 'QRIS',
            'nama_metode' => 'QRIS',
            'ada_kembalian' => false,
        ]);

        $tunaiPesanan = $this->pesananLunas($kasir, $tunai, 'dine-in', 50000);
        $qrisPesanan = $this->pesananLunas($kasir, $qris, 'takeaway', 25000);

        $this->actingAs($admin)
            ->get(route('admin.sales.cetak', ['metode' => $qris->id_metode]))
            ->assertOk()
            ->assertSee('Filter: Semua tipe · QRIS')
            ->assertSee('Rp 25.000')
            ->assertSee($qrisPesanan->nomor_pesanan)
            ->assertDontSee($tunaiPesanan->nomor_pesanan);

        $csv = $this->actingAs($admin)
            ->get(route('admin.sales.cetak.csv', ['metode' => $qris->id_metode]))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString($qrisPesanan->nomor_pesanan, $csv);
        $this->assertStringNotContainsString($tunaiPesanan->nomor_pesanan, $csv);
    }

    public function test_filter_tipe_dan_metode_tidak_dikenal_diabaikan(): void
    {
        $admin = Pengguna::factory()->create(['peran' => 'admin']);
        $kasir = Pengguna::factory()->create(['peran' => 'kasir']);
        $metode = $this->metodeCash();

        $this->pesananLunas($kasir, $metode, 'dine-in', 50000);
        $this->pesananLunas($kasir, $metode, 'takeaway', 25000);

        $this->actingAs($admin)
            ->get(route('admin.sales.cetak', ['tipe' => 'kopi', 'metode' => '999999']))
            ->assertOk()
            ->assertSee('Filter tipe pesanan tidak dikenal')
            ->assertSee('Filter metode pembayaran tidak dikenal')
            ->assertSee('Rp 75.000');
    }

    public function test_ekspor_csv_terbatas_untuk_admin(): void
    {
        $this->actingAs(Pengguna::factory()->create(['peran' => 'kasir']))
            ->get(route('admin.sales.cetak.csv'))
            ->assertForbidden();

        $this->actingAs(Pengguna::factory()->create(['peran' => 'pelanggan']))
            ->get(route('admin.sales.cetak.csv'))
            ->assertForbidden();
    }

    public function test_tombol_ekspor_csv_tersedia_di_laporan_cetak(): void
    {
        $this->actingAs(Pengguna::factory()->create(['peran' => 'admin']))
            ->get(route('admin.sales.cetak'))
            ->assertOk()
            ->assertSee('Ekspor CSV')
            ->assertSee(route('admin.sales.cetak.csv'), false);
    }

    public function test_tombol_cetak_tersedia_di_halaman_sales(): void
    {
        $this->actingAs(Pengguna::factory()->create(['peran' => 'admin']))
            ->get(route('admin.sales.index'))
            ->assertOk()
            ->assertSee('Cetak Laporan')
            ->assertSee(route('admin.sales.cetak'), false);
    }
}
