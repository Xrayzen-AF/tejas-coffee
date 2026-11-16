<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Admin\SettingController;
use App\Models\Pesanan;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Struk pembayaran 80mm (thermal).
 * Bisa dilihat kasir/admin dan pelanggan pemilik pesanan (lihat PesananPolicy).
 */
class StrukController extends Controller
{
    public function show(Request $request, Pesanan $pesanan): View
    {
        $pesanan->load([
            'detailPesanan.produk',
            'meja',
            'pelanggan',
            'pengguna',
            'transaksi.metodePembayaran',
            'promo',
        ]);

        $pengguna = $request->user();

        // Tamu tanpa akun hanya boleh melihat struk pesanan yang tercatat
        // pada sesi perangkatnya (hasil checkout tamu).
        $milikTamu = in_array(
            (int) $pesanan->id_pesanan,
            array_map('intval', (array) $request->session()->get('tamu_pesanan', [])),
            true,
        );

        if ($pengguna) {
            // S3-01 (anti-IDOR): staf bebas, pelanggan hanya struk miliknya sendiri.
            $this->authorize('view', $pesanan);
        } elseif (! $milikTamu) {
            abort(403, 'Bukan pesanan milik Anda.');
        }

        $staf = $pengguna?->isKasir() || $pengguna?->isAdmin();

        // Nomor antrian harian: urutan pesanan pada tanggal yang sama.
        $nomorAntrian = Pesanan::whereDate('waktu_pesan', $pesanan->waktu_pesan?->toDateString() ?? today())
            ->where('id_pesanan', '<=', $pesanan->id_pesanan)
            ->count();

        return view('struk.show', [
            'pesanan' => $pesanan,
            'toko' => SettingController::load(),
            'nomorAntrian' => max(1, $nomorAntrian),
            // Tamu pemilik sesi juga boleh mencetak/menyimpan struknya.
            'bolehCetak' => $staf || $milikTamu,
            'autoPrint' => $request->boolean('print'),
        ]);
    }
}
