<?php

namespace App\Services;

use App\Models\MetodePembayaran;
use App\Models\MidtransSettings;
use App\Models\Pelanggan;
use App\Models\Pesanan;
use App\Models\Transaksi;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Midtrans\Config;
use Midtrans\CoreApi;
use Midtrans\Transaction;

/**
 * Integrasi Midtrans Core API untuk pembayaran POS:
 * - QRIS  : charge `qris` -> gambar QR hosted Midtrans, dipindai pelanggan.
 *
 * Kredensial diambil dari tabel `midtrans_settings` (diatur admin) dan
 * fallback ke config/services.php + .env. Status pembayaran disinkronkan
 * lewat webhook DAN polling dari kasir, sehingga tetap jalan di localhost.
 */
class MidtransService
{
    /** Bank virtual account yang didukung Core API. */
    public const BANKS = [
        'bca' => 'BCA',
        'bni' => 'BNI',
        'bri' => 'BRI',
        'mandiri' => 'Mandiri',
        'permata' => 'Permata',
    ];

    public const EXPIRY_MINUTES = 15;

    /**
     * Acquirer QRIS yang terdaftar di Midtrans Core API. Satu QR dari
     * acquirer mana pun tetap bisa dipindai GoPay, ShopeePay, OVO, DANA,
     * LinkAja, maupun m-banking karena QRIS bersifat interoperabel.
     */
    public const QRIS_ACQUIRERS = [
        'gopay' => 'GoPay',
        'airpay shopee' => 'ShopeePay (AirPay Shopee)',
    ];

    private ?array $credentials = null;

    /**
     * @return array{server_key:string,client_key:string,is_production:bool,source:string}
     */
    public function credentials(): array
    {
        if ($this->credentials !== null) {
            return $this->credentials;
        }

        $row = MidtransSettings::query()
            ->whereNotNull('server_key')
            ->where('server_key', '<>', '')
            ->latest('updated_at')
            ->latest('id')
            ->first();

        $this->credentials = $row
            ? [
                'server_key' => trim((string) $row->server_key),
                'client_key' => trim((string) $row->client_key),
                'is_production' => (bool) $row->is_production,
                'source' => 'database',
            ]
            : [
                'server_key' => trim((string) config('services.midtrans.server_key')),
                'client_key' => trim((string) config('services.midtrans.client_key')),
                'is_production' => (bool) config('services.midtrans.is_production'),
                'source' => 'env',
            ];

        return $this->credentials;
    }

    public function isConfigured(): bool
    {
        $key = $this->credentials()['server_key'];

        return $key !== ''
            && ! str_contains(strtolower($key), 'xxxxxxxx');
    }

    public function serverKey(): string
    {
        return $this->credentials()['server_key'];
    }

    public function mode(): string
    {
        return $this->credentials()['is_production'] ? 'Production' : 'Sandbox';
    }

    public function configure(): void
    {
        $config = $this->credentials();

        Config::$serverKey = $config['server_key'];
        Config::$clientKey = $config['client_key'];
        Config::$isProduction = $config['is_production'];
        Config::$isSanitized = true;
        Config::$is3ds = true;
    }

    /**
     * Charge QRIS. Mengembalikan payload siap tampil + tersimpan di transaksi.
     */
    public function chargeQris(Pesanan $pesanan): array
    {
        $this->ensureConfigured();
        $this->configure();

        $orderId = $this->newOrderId($pesanan);
        $params = array_merge([
            'payment_type' => 'qris',
            'transaction_details' => [
                'order_id' => $orderId,
                'gross_amount' => $this->grossAmount($pesanan),
            ],
            'custom_expiry' => [
                'expiry_duration' => self::EXPIRY_MINUTES,
                'unit' => 'minute',
            ],
        ], $this->customerParams($pesanan));

        // Bila acquirer dipilih eksplisit (MIDTRANS_QRIS_ACQUIRER), kirim
        // `qris.acquirer`. Bila kosong, Midtrans memakai acquirer default
        // akun merchant — QRIS tetap bisa dibayar semua aplikasi QRIS.
        if ($acquirer = $this->qrisAcquirer()) {
            $params['qris'] = ['acquirer' => $acquirer];
        }

        try {
            $raw = $this->sendCharge($params);
        } catch (\Throwable $e) {
            throw $this->chargeException($e);
        }

        $payment = $this->normalize($raw, 'qris', $orderId);

        return $this->persistPending($pesanan, $payment);
    }

    /** Acquirer QRIS terpilih dari config; null = biarkan Midtrans memilih. */
    public function qrisAcquirer(): ?string
    {
        $acquirer = strtolower(trim((string) config('services.midtrans.qris_acquirer')));

        return array_key_exists($acquirer, self::QRIS_ACQUIRERS) ? $acquirer : null;
    }

    /**
     * Bayar pending QRIS yang masih berlaku agar tidak menagih ganda.
     */
    public function pendingPayment(Pesanan $pesanan): ?array
    {
        $trx = $pesanan->transaksi()->first();

        if (! $trx || $trx->status_pembayaran !== 'menunggu' || ! $trx->midtrans_order_id) {
            return null;
        }

        if ($trx->midtrans_expires_at && $trx->midtrans_expires_at->isPast()) {
            return null;
        }

        $payload = $trx->midtrans_payload;

        return is_array($payload) && ! empty($payload)
            ? array_merge($payload, $this->publicPayment($trx))
            : null;
    }

    public function publicPayment(Transaksi $trx): array
    {
        $payload = is_array($trx->midtrans_payload) ? $trx->midtrans_payload : [];

        return [
            'order_id' => $trx->midtrans_order_id,
            'transaction_id' => $payload['transaction_id'] ?? null,
            'payment_type' => $trx->midtrans_payment_type,
            'method' => $trx->midtrans_payment_type === 'qris' ? 'qris' : 'transfer',
            'bank' => $payload['bank'] ?? null,
            'status' => $trx->status_pembayaran,
            'gross_amount' => (float) $trx->total_bayar,
            'qr_url' => $payload['qr_url'] ?? null,
            'qr_string' => $payload['qr_string'] ?? null,
            'acquirer' => $payload['acquirer'] ?? null,
            'issuer' => $payload['issuer'] ?? null,
            'va_number' => $payload['va_number'] ?? null,
            'biller_code' => $payload['biller_code'] ?? null,
            'bill_key' => $payload['bill_key'] ?? null,
            'expires_at' => $trx->midtrans_expires_at?->toIso8601String(),
        ];
    }

    /**
     * Ambil status terbaru langsung dari Midtrans (fallback tanpa webhook).
     */
    /**
     * Titik integrasi tunggal ke Core API (mudah diganti fake di test).
     */
    protected function sendCharge(array $params): object
    {
        return CoreApi::charge($params);
    }

    public function remoteStatus(string $orderId): array
    {
        $this->ensureConfigured();
        $this->configure();

        try {
            $raw = Transaction::status($orderId);
        } catch (\Throwable $e) {
            throw $this->chargeException($e);
        }

        return $this->toArray($raw);
    }

    /**
     * Terapkan status Midtrans ke transaksi lokal secara idempoten.
     *
     * @return array{status_pembayaran:string,already_paid:bool,remote_status:string}
     */
    public function applyStatus(Pesanan $pesanan, array $raw): array
    {
        $remote = (string) ($raw['transaction_status'] ?? 'pending');
        $fraud = $raw['fraud_status'] ?? null;

        $paid = $remote === 'settlement'
            || ($remote === 'capture' && ! in_array($fraud, ['challenge', 'deny'], true));
        $failed = in_array($remote, ['deny', 'cancel', 'expire', 'failure'], true);

        $target = $paid ? 'lunas' : ($failed ? 'gagal' : 'menunggu');

        $result = DB::transaction(function () use ($pesanan, $raw, $remote, $fraud, $target) {
            $trx = $pesanan->transaksi()->lockForUpdate()->first();

            if ($trx && $trx->status_pembayaran === 'lunas') {
                return ['status_pembayaran' => 'lunas', 'already_paid' => true, 'remote_status' => $remote];
            }

            $payload = is_array($trx?->midtrans_payload) ? $trx->midtrans_payload : [];
            $va = $raw['va_numbers'][0]['va_number']
                ?? $raw['permata_va_number']
                ?? $payload['va_number']
                ?? null;
            $bank = $raw['va_numbers'][0]['bank']
                ?? ($raw['permata_va_number'] ?? null ? 'permata' : ($payload['bank'] ?? null));
            $paymentType = $raw['payment_type'] ?? $trx?->midtrans_payment_type ?? $payload['payment_type'] ?? null;
            $transactionId = $raw['transaction_id'] ?? $payload['transaction_id'] ?? null;
            $grossAmount = (float) ($raw['gross_amount'] ?? $trx?->total_bayar ?? $pesanan->total_harga);

            $payload = array_merge($payload, [
                'order_id' => $raw['order_id'] ?? $trx?->midtrans_order_id,
                'transaction_id' => $transactionId,
                'payment_type' => $paymentType,
                'status' => $remote,
                'fraud_status' => $fraud,
                'gross_amount' => $grossAmount,
                'va_number' => $va,
                'bank' => $bank ? strtoupper((string) $bank) : null,
                // `issuer` = aplikasi yang benar-benar membayar QRIS
                // (gopay, shopeepay, ovo, dana, linkaja, atau kode bank).
                'acquirer' => $raw['acquirer'] ?? ($payload['acquirer'] ?? null),
                'issuer' => $raw['issuer'] ?? ($payload['issuer'] ?? null),
            ]);

            $data = [
                'midtrans_order_id' => $raw['order_id'] ?? $trx?->midtrans_order_id,
                'midtrans_payment_type' => $paymentType,
                'midtrans_payload' => $payload,
                'midtrans_expires_at' => ! empty($raw['expiry_time'])
                    ? $this->parseExpiry($raw['expiry_time'])
                    : $trx?->midtrans_expires_at,
            ];

            if ($target === 'lunas') {
                $data += [
                    'subtotal' => (float) $pesanan->total_harga,
                    'total_bayar' => $grossAmount,
                    'kembalian' => 0,
                    'bank_atau_ewallet' => $this->paymentLabel($paymentType, $bank, $raw['issuer'] ?? null),
                    'nomor_referensi' => $va ?: $transactionId,
                    'status_pembayaran' => 'lunas',
                    'waktu_pembayaran' => now(),
                ];
            } elseif ($target === 'gagal') {
                $data += [
                    'status_pembayaran' => 'gagal',
                    'nomor_referensi' => $trx?->nomor_referensi ?? $va ?? $transactionId,
                ];
            } else {
                $data += [
                    'status_pembayaran' => 'menunggu',
                    'nomor_referensi' => $trx?->nomor_referensi ?? $va ?? $transactionId,
                    'bank_atau_ewallet' => $this->paymentLabel($paymentType, $bank, $raw['issuer'] ?? null),
                ];
            }

            if ($trx) {
                $trx->update($data);
            } else {
                $metode = $this->methodFor($paymentType === 'qris' ? 'qris' : 'transfer');
                $data['id_metode'] = $metode->id_metode;
                $data['subtotal'] ??= (float) $pesanan->total_harga;
                $data['total_bayar'] ??= $grossAmount;
                $data['kembalian'] ??= 0;
                $trx = $pesanan->transaksi()->create($data);
            }

            if ($target === 'lunas' && $pesanan->id_pelanggan && (float) $pesanan->total_harga > 0) {
                $poin = (int) floor((float) $pesanan->total_harga / Pesanan::RUPIAH_PER_POIN);
                if ($poin > 0) {
                    Pelanggan::where('id_pelanggan', $pesanan->id_pelanggan)
                        ->increment('poin_loyalitas', $poin);
                }
            }

            return ['status_pembayaran' => $target, 'already_paid' => false, 'remote_status' => $remote];
        });

        return $result;
    }

    public function findByOrderId(string $orderId): ?Pesanan
    {
        $pesanan = Pesanan::query()
            ->whereHas('transaksi', fn ($q) => $q->where('midtrans_order_id', $orderId))
            ->first();

        if ($pesanan) {
            return $pesanan;
        }

        $clean = preg_replace('/-\d+$/', '', $orderId);

        return Pesanan::query()
            ->where('nomor_pesanan', $orderId)
            ->orWhere('nomor_pesanan', $clean)
            ->first();
    }

    private function ensureConfigured(): void
    {
        if (! $this->isConfigured()) {
            throw new \RuntimeException(
                'Midtrans belum dikonfigurasi. Isi Server Key & Client Key di Admin → Store Settings.'
            );
        }
    }

    private function grossAmount(Pesanan $pesanan): int
    {
        $amount = (int) round((float) $pesanan->total_harga);

        if ($amount <= 0) {
            throw new \RuntimeException('Total tagihan harus lebih dari 0.');
        }

        return $amount;
    }

    private function newOrderId(Pesanan $pesanan): string
    {
        $suffix = time();

        do {
            $orderId = $pesanan->nomor_pesanan.'-'.$suffix;
            $suffix++;
        } while (Transaksi::where('midtrans_order_id', $orderId)->exists());

        return $orderId;
    }

    private function customerParams(Pesanan $pesanan): array
    {
        $customer = $pesanan->pelanggan;

        return [
            'customer_details' => array_filter([
                'first_name' => $customer?->nama_pelanggan ?: 'Pelanggan Kasir',
                'phone' => $customer?->nomor_telepon,
            ]),
        ];
    }

    private function normalize(object|array $raw, string $method, string $orderId, ?string $bank = null): array
    {
        $raw = $this->toArray($raw);

        // Utamakan QR format ASPI (`generate-qr-code-v2`) agar bisa dipindai
        // semua aplikasi QRIS; fallback ke `generate-qr-code` versi lama.
        $qrUrl = null;
        foreach (['generate-qr-code-v2', 'generate-qr-code'] as $nama) {
            foreach ($raw['actions'] ?? [] as $action) {
                if (($action['name'] ?? null) === $nama && ! empty($action['url'])) {
                    $qrUrl = $action['url'];
                    break;
                }
            }
            if ($qrUrl) {
                break;
            }
        }

        $va = $raw['va_numbers'][0]['va_number'] ?? $raw['permata_va_number'] ?? null;
        $vaBank = $raw['va_numbers'][0]['bank'] ?? ($raw['permata_va_number'] ?? null ? 'permata' : $bank);

        return [
            'order_id' => $raw['order_id'] ?? $orderId,
            'transaction_id' => $raw['transaction_id'] ?? null,
            'payment_type' => $raw['payment_type'] ?? ($method === 'qris' ? 'qris' : 'bank_transfer'),
            'method' => $method,
            'bank' => $vaBank ? strtoupper((string) $vaBank) : null,
            'status' => $raw['transaction_status'] ?? 'pending',
            'gross_amount' => (float) ($raw['gross_amount'] ?? 0),
            'qr_url' => $qrUrl,
            'qr_string' => $raw['qr_string'] ?? null,
            'va_number' => $va,
            'biller_code' => $raw['biller_code'] ?? null,
            'bill_key' => $raw['bill_key'] ?? null,
            'acquirer' => $raw['acquirer'] ?? null,
            'issuer' => $raw['issuer'] ?? null,
            'expires_at' => $this->parseExpiry($raw['expiry_time'] ?? null),
        ];
    }

    private function persistPending(Pesanan $pesanan, array $payment): array
    {
        $metode = $this->methodFor($payment['method']);

        DB::transaction(function () use ($pesanan, $payment, $metode) {
            $trx = $pesanan->transaksi()->lockForUpdate()->first();

            if ($trx && $trx->status_pembayaran === 'lunas') {
                throw new \RuntimeException('Pesanan ini sudah lunas.');
            }

            $data = [
                'id_metode' => $metode->id_metode,
                'subtotal' => (float) $pesanan->total_harga,
                'total_bayar' => (float) ($payment['gross_amount'] ?: $pesanan->total_harga),
                'kembalian' => 0,
                'bank_atau_ewallet' => $this->paymentLabel($payment['payment_type'], $payment['bank'] ?? null),
                'nomor_referensi' => $payment['va_number'] ?: $payment['transaction_id'],
                'midtrans_order_id' => $payment['order_id'],
                'midtrans_payment_type' => $payment['payment_type'],
                'midtrans_payload' => $payment,
                'midtrans_expires_at' => $payment['expires_at'],
                'status_pembayaran' => 'menunggu',
            ];

            if ($trx) {
                $trx->update($data);
            } else {
                $pesanan->transaksi()->create($data);
            }
        });

        return array_merge($payment, [
            'id_metode' => $metode->id_metode,
            'status_pembayaran' => 'menunggu',
        ]);
    }

    private function methodFor(string $method): MetodePembayaran
    {
        $code = $method === 'qris' ? 'QRIS' : 'TRANSFER';

        return MetodePembayaran::where('kode_metode', $code)->first()
            ?? MetodePembayaran::firstOrCreate(
                ['kode_metode' => $code],
                [
                    'nama_metode' => $method === 'qris' ? 'QRIS / Midtrans' : 'Transfer Bank',
                    'ada_kembalian' => false,
                ]
            );
    }

    private function paymentLabel(?string $paymentType, ?string $bank, ?string $issuer = null): string
    {
        if ($paymentType === 'qris') {
            $wallet = $this->issuerLabel($issuer);

            return $wallet ? 'QRIS · '.$wallet : 'QRIS (Midtrans)';
        }

        $bank = $bank ? strtoupper($bank) : 'BANK';

        return 'VA '.$bank.' (Midtrans)';
    }

    /** Nama aplikasi e-wallet penerbit QRIS yang dikenali laporan Midtrans. */
    private function issuerLabel(?string $issuer): ?string
    {
        return match (strtolower(trim((string) $issuer))) {
            'gopay' => 'GoPay',
            'shopeepay' => 'ShopeePay',
            'ovo' => 'OVO',
            'dana' => 'DANA',
            'linkaja' => 'LinkAja',
            default => null,
        };
    }

    private function parseExpiry(?string $value): ?string
    {
        if (! $value) {
            return now()->addMinutes(self::EXPIRY_MINUTES)->toIso8601String();
        }

        try {
            return CarbonImmutable::parse($value, 'Asia/Jakarta')
                ->setTimezone(config('app.timezone'))
                ->toIso8601String();
        } catch (\Throwable) {
            return now()->addMinutes(self::EXPIRY_MINUTES)->toIso8601String();
        }
    }

    private function chargeException(\Throwable $e): \RuntimeException
    {
        Log::warning('Midtrans error: '.$e->getMessage());

        return new \RuntimeException($this->friendlyError($e), 0, $e);
    }

    private function friendlyError(\Throwable $e): string
    {
        $message = $e->getMessage();

        if (preg_match('/API response: (\{.*\})/s', $message, $matches)) {
            $decoded = json_decode($matches[1], true);
            if (! empty($decoded['status_message'])) {
                return 'Midtrans: '.$decoded['status_message'];
            }
        }

        if (str_contains($message, 'Midtrans belum dikonfigurasi')
            || str_contains($message, 'Total tagihan')) {
            return $message;
        }

        if (preg_match('/HTTP status code: (\d+)/', $message, $matches)) {
            return 'Midtrans menolak permintaan (HTTP '.$matches[1].'). Periksa pengaturan akun Midtrans.';
        }

        return 'Gagal menghubungi Midtrans. Coba lagi atau gunakan pembayaran tunai.';
    }

    private function toArray(object|array $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        return json_decode(json_encode($value), true) ?: [];
    }
}
