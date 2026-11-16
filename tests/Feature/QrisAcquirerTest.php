<?php

namespace Tests\Feature;

use App\Models\MidtransSettings;
use App\Models\Pengguna;
use App\Models\Pesanan;
use App\Services\MidtransService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Memastikan dukungan e-wallet QRIS bukan sekadar teks UI:
 * - payload charge mengirim `qris.acquirer` bila dikonfigurasi,
 * - QR format ASPI (v2) dipakai lebih dulu,
 * - issuer (aplikasi pembayar) dicatat dan dilabeli di transaksi.
 */
class QrisAcquirerTest extends TestCase
{
    use RefreshDatabase;

    private function fakeMidtrans(array $extra = []): MidtransService
    {
        $service = new class($extra) extends MidtransService
        {
            public array $lastParams = [];

            public function __construct(private array $extra) {}

            protected function sendCharge(array $params): object
            {
                $this->lastParams = $params;

                return (object) array_merge([
                    'order_id' => $params['transaction_details']['order_id'],
                    'transaction_id' => 'trx-qris-test',
                    'payment_type' => $params['payment_type'],
                    'transaction_status' => 'pending',
                    'gross_amount' => (string) $params['transaction_details']['gross_amount'],
                    'expiry_time' => now('Asia/Jakarta')->addMinutes(15)->format('Y-m-d H:i:s'),
                ], $this->extra);
            }
        };

        $this->app->instance(MidtransService::class, $service);

        return $service;
    }

    private function siapkanMidtrans(): void
    {
        MidtransSettings::create([
            'server_key' => 'SB-Mid-server-test',
            'client_key' => 'SB-Mid-client-test',
            'is_production' => false,
        ]);
    }

    private function kasir(): Pengguna
    {
        return Pengguna::factory()->create(['peran' => 'kasir']);
    }

    private function pesanan(int $total = 25000): Pesanan
    {
        return Pesanan::factory()->create([
            'status_pesanan' => 'baru',
            'total_harga' => $total,
        ]);
    }

    public function test_charge_qris_mengirim_acquirer_yang_dikonfigurasi(): void
    {
        $this->siapkanMidtrans();
        config(['services.midtrans.qris_acquirer' => 'airpay shopee']);
        $fake = $this->fakeMidtrans();

        $this->actingAs($this->kasir())
            ->postJson(route('pesanan.midtrans.qris', $this->pesanan()->id_pesanan))
            ->assertOk();

        $this->assertSame('qris', $fake->lastParams['payment_type']);
        $this->assertSame('airpay shopee', $fake->lastParams['qris']['acquirer']);
    }

    public function test_acquirer_tidak_dikenal_tidak_menghalangi_charge(): void
    {
        $this->siapkanMidtrans();
        // DANA/OVO/LinkAja bukan acquirer QRIS Midtrans, hanya issuer.
        config(['services.midtrans.qris_acquirer' => 'dana']);
        $fake = $this->fakeMidtrans();

        $this->actingAs($this->kasir())
            ->postJson(route('pesanan.midtrans.qris', $this->pesanan()->id_pesanan))
            ->assertOk();

        $this->assertArrayNotHasKey('qris', $fake->lastParams);
    }

    public function test_qr_format_aspi_dipilih_daripada_qr_lama(): void
    {
        $this->siapkanMidtrans();
        $this->fakeMidtrans([
            'actions' => [
                ['name' => 'generate-qr-code', 'url' => 'https://api.midtrans.com/v2/qris/lama/qr-code'],
                ['name' => 'generate-qr-code-v2', 'url' => 'https://api.midtrans.com/v4/qris/aspi/qr-code'],
            ],
        ]);

        $this->actingAs($this->kasir())
            ->postJson(route('pesanan.midtrans.qris', $this->pesanan()->id_pesanan))
            ->assertOk()
            ->assertJsonPath('payment.qr_url', 'https://api.midtrans.com/v4/qris/aspi/qr-code');
    }

    public function test_issuer_ewallet_dicatat_dan_dilabeli_saat_lunas(): void
    {
        $this->siapkanMidtrans();
        $service = $this->fakeMidtrans();

        $kasus = [
            'shopeepay' => 'QRIS · ShopeePay',
            'gopay' => 'QRIS · GoPay',
            'ovo' => 'QRIS · OVO',
            'dana' => 'QRIS · DANA',
            'linkaja' => 'QRIS · LinkAja',
        ];

        foreach ($kasus as $issuer => $label) {
            $pesanan = $this->pesanan();
            $service->chargeQris($pesanan);

            $service->applyStatus($pesanan, [
                'order_id' => $pesanan->transaksi()->first()->midtrans_order_id,
                'transaction_id' => 'trx-'.$issuer,
                'payment_type' => 'qris',
                'transaction_status' => 'settlement',
                'gross_amount' => '25000.00',
                'acquirer' => 'gopay',
                'issuer' => $issuer,
            ]);

            $trx = $pesanan->transaksi()->first();
            $this->assertSame('lunas', $trx->status_pembayaran, "issuer {$issuer} harus lunas");
            $this->assertSame($label, $trx->bank_atau_ewallet);
            $this->assertSame($issuer, $service->publicPayment($trx)['issuer']);
        }
    }
}
