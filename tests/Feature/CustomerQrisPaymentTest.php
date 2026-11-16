<?php

namespace Tests\Feature;

use App\Models\Kategori;
use App\Models\MetodePembayaran;
use App\Models\MidtransSettings;
use App\Models\Pengguna;
use App\Models\Pesanan;
use App\Models\Produk;
use App\Services\MidtransService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * QRIS diprakarsai pelanggan: otorisasi memakai sesi pemilik pesanan
 * (akun atau sesi tamu), QR ditampilkan, dan polling bisa menyinkronkan
 * status langsung ke Midtrans.
 */
class CustomerQrisPaymentTest extends TestCase
{
    use RefreshDatabase;

    private function siapkanMidtrans(): void
    {
        MidtransSettings::create([
            'server_key' => 'SB-Mid-server-test',
            'client_key' => 'SB-Mid-client-test',
            'is_production' => false,
        ]);
    }

    private function fakeMidtrans(): MidtransService
    {
        $service = new class extends MidtransService
        {
            public array $lastParams = [];

            public ?array $remote = null;

            protected function sendCharge(array $params): object
            {
                $this->lastParams = $params;

                return (object) [
                    'order_id' => $params['transaction_details']['order_id'],
                    'transaction_id' => 'trx-qris-pelanggan',
                    'payment_type' => 'qris',
                    'transaction_status' => 'pending',
                    'gross_amount' => (string) $params['transaction_details']['gross_amount'],
                    'acquirer' => 'gopay',
                    'actions' => [
                        ['name' => 'generate-qr-code-v2', 'url' => 'https://api.midtrans.com/v4/qris/aspi/qr-code'],
                    ],
                ];
            }

            public function remoteStatus(string $orderId): array
            {
                return $this->remote ?? [
                    'order_id' => $orderId,
                    'transaction_status' => 'pending',
                    'payment_type' => 'qris',
                ];
            }
        };

        $this->app->instance(MidtransService::class, $service);

        return $service;
    }

    private function pesananTamu(int $total = 25000): Pesanan
    {
        return Pesanan::factory()->create([
            'id_pengguna' => null,
            'id_pelanggan' => null,
            'nama_pemesan' => 'Budi Tamu',
            'tipe_pesanan' => 'takeaway',
            'status_pesanan' => 'baru',
            'total_harga' => $total,
        ]);
    }

    public function test_tamu_bisa_membuat_charge_qris_untuk_pesanannya(): void
    {
        $this->siapkanMidtrans();
        $fake = $this->fakeMidtrans();
        $pesanan = $this->pesananTamu();

        $this->withSession(['tamu_pesanan' => [$pesanan->id_pesanan]])
            ->postJson(route('pelanggan.pesanan.bayar-qris', $pesanan->id_pesanan))
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('payment.qr_url', 'https://api.midtrans.com/v4/qris/aspi/qr-code');

        $this->assertSame('qris', $fake->lastParams['payment_type']);
        $this->assertSame('menunggu', $pesanan->transaksi()->first()->status_pembayaran);
    }

    public function test_tamu_lain_tidak_bisa_membuat_charge(): void
    {
        $this->siapkanMidtrans();
        $this->fakeMidtrans();
        $pesanan = $this->pesananTamu();

        $this->postJson(route('pelanggan.pesanan.bayar-qris', $pesanan->id_pesanan))
            ->assertForbidden();

        $this->assertNull($pesanan->transaksi()->first());
    }

    public function test_member_bisa_membuat_charge_untuk_pesanannya(): void
    {
        $this->siapkanMidtrans();
        $this->fakeMidtrans();
        $pengguna = Pengguna::factory()->create(['peran' => 'pelanggan', 'nomor_telepon' => '081234567890']);

        $pesanan = Pesanan::factory()->create([
            'id_pengguna' => $pengguna->id_pengguna,
            'status_pesanan' => 'baru',
            'total_harga' => 18000,
        ]);

        $this->actingAs($pengguna)
            ->postJson(route('pelanggan.pesanan.bayar-qris', $pesanan->id_pesanan))
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $this->assertSame('menunggu', $pesanan->transaksi()->first()->status_pembayaran);
    }

    public function test_pesanan_lunas_tidak_bisa_dicharge_lagi(): void
    {
        $this->siapkanMidtrans();
        $this->fakeMidtrans();
        $pesanan = $this->pesananTamu();

        $pesanan->transaksi()->create([
            'id_metode' => MetodePembayaran::create([
                'kode_metode' => 'QRIS',
                'nama_metode' => 'QRIS / Midtrans',
                'ada_kembalian' => false,
            ])->id_metode,
            'subtotal' => 25000,
            'total_bayar' => 25000,
            'kembalian' => 0,
            'status_pembayaran' => 'lunas',
            'waktu_pembayaran' => now(),
        ]);

        $this->withSession(['tamu_pesanan' => [$pesanan->id_pesanan]])
            ->postJson(route('pelanggan.pesanan.bayar-qris', $pesanan->id_pesanan))
            ->assertStatus(422)
            ->assertJsonPath('message', 'Pesanan ini sudah lunas.');
    }

    public function test_polling_sync_menandai_lunas_dari_midtrans(): void
    {
        $this->siapkanMidtrans();
        $fake = $this->fakeMidtrans();
        $pesanan = $this->pesananTamu();

        $this->withSession(['tamu_pesanan' => [$pesanan->id_pesanan]])
            ->postJson(route('pelanggan.pesanan.bayar-qris', $pesanan->id_pesanan))
            ->assertOk();

        $fake->remote = [
            'order_id' => $pesanan->transaksi()->first()->midtrans_order_id,
            'transaction_id' => 'trx-qris-pelanggan',
            'payment_type' => 'qris',
            'transaction_status' => 'settlement',
            'gross_amount' => '25000.00',
            'acquirer' => 'gopay',
            'issuer' => 'shopeepay',
        ];

        $this->withSession(['tamu_pesanan' => [$pesanan->id_pesanan]])
            ->getJson(route('pelanggan.pesanan.status-live', $pesanan->id_pesanan).'?sync=1')
            ->assertOk()
            ->assertJsonPath('status_pembayaran', 'lunas');

        $trx = $pesanan->transaksi()->first();
        $this->assertSame('lunas', $trx->status_pembayaran);
        $this->assertSame('QRIS · ShopeePay', $trx->bank_atau_ewallet);
    }

    private function buatMenu(): Produk
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

    public function test_checkout_menawarkan_qris_dan_tunai_di_kasir(): void
    {
        $this->siapkanMidtrans();
        $produk = $this->buatMenu();
        $this->post(route('pelanggan.keranjang.tambah', $produk->id_produk));

        $this->get(route('pelanggan.checkout'))
            ->assertOk()
            ->assertSee('QRIS — Bayar dari HP')
            ->assertSee('Tunai di Kasir')
            ->assertSee('value="qris"', false)
            ->assertSee('value="tunai"', false);
    }

    public function test_pesanan_dengan_metode_qris_diarahkan_ke_panel_qr(): void
    {
        $this->siapkanMidtrans();
        $produk = $this->buatMenu();
        $this->post(route('pelanggan.keranjang.tambah', $produk->id_produk));

        $res = $this->post(route('pelanggan.checkout.simpan'), [
            'tipe_pesanan' => 'takeaway',
            'nama_pemesan' => 'Budi Tamu',
            'metode' => 'qris',
        ]);

        $pesanan = Pesanan::firstOrFail();

        $res->assertRedirect(route('pelanggan.status', $pesanan->nomor_pesanan).'?bayar=qris')
            ->assertSessionHas('success');

        // Halaman status langsung menyiapkan pembayaran QRIS.
        $this->get(route('pelanggan.status', $pesanan->nomor_pesanan).'?bayar=qris')
            ->assertOk()
            ->assertSee('Menunggu pembayaran QRIS')
            ->assertSee('Tampilkan Kode QRIS');
    }

    public function test_pesanan_dengan_metode_tunai_tetap_di_kasir(): void
    {
        $this->siapkanMidtrans();
        $produk = $this->buatMenu();
        $this->post(route('pelanggan.keranjang.tambah', $produk->id_produk));

        $res = $this->post(route('pelanggan.checkout.simpan'), [
            'tipe_pesanan' => 'takeaway',
            'nama_pemesan' => 'Budi Tamu',
            'metode' => 'tunai',
        ]);

        $pesanan = Pesanan::firstOrFail();

        $res->assertRedirect(route('pelanggan.status', $pesanan->nomor_pesanan));

        $this->get(route('pelanggan.status', $pesanan->nomor_pesanan))
            ->assertOk()
            ->assertSee('Menunggu pembayaran di kasir');
    }

    public function test_halaman_status_menampilkan_panel_qris(): void
    {
        $pesanan = $this->pesananTamu();

        $this->withSession(['tamu_pesanan' => [$pesanan->id_pesanan]])
            ->get(route('pelanggan.status', $pesanan->nomor_pesanan))
            ->assertOk()
            ->assertSee('Tampilkan Kode QRIS')
            ->assertSee('GoPay, ShopeePay, DANA, OVO, LinkAja', false);
    }
}
