<?php

namespace App\Console\Commands;

use App\Models\Pesanan;
use Illuminate\Console\Command;

/**
 * Kebijakan bayar-di-muka: pesanan yang belum dibayar melewati batas waktu
 * dibatalkan otomatis supaya stok kembali tersedia dan dapur tidak
 * memproses order fiktif. Stok + poin dikembalikan oleh Pesanan::batalkan().
 */
class BatalkanPesananBelumBayar extends Command
{
    protected $signature = 'pesanan:batalkan-belum-bayar {--menit=15 : Umur maksimum pesanan yang belum dibayar}';

    protected $description = 'Batalkan pesanan status "baru" yang belum dibayar melewati batas waktu';

    public function handle(): int
    {
        $batas = now()->subMinutes((int) $this->option('menit'));

        $kandidat = Pesanan::with('transaksi')
            ->where('status_pesanan', 'baru')
            ->where('waktu_pesan', '<=', $batas)
            ->whereDoesntHave('transaksi', fn ($q) => $q->where('status_pembayaran', 'lunas'))
            ->get();

        $jumlah = 0;

        foreach ($kandidat as $pesanan) {
            try {
                $pesanan->batalkan();
                $jumlah++;
            } catch (\Throwable $e) {
                $this->warn('Gagal membatalkan '.$pesanan->nomor_pesanan.': '.$e->getMessage());
            }
        }

        $this->info($jumlah.' pesanan belum dibayar dibatalkan.');

        return self::SUCCESS;
    }
}
