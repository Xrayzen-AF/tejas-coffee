<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MetodePembayaran;
use App\Models\Pesanan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Halaman admin untuk tabel `pesanan` — tabel TRANSAKSI, jadi di sini
 * sengaja TIDAK ada create/edit isi. Yang boleh: lihat daftar + detail,
 * dorong status lewat state machine (baru → diproses → selesai),
 * dan batalkan (rollback stok & poin via Pesanan::batalkan()).
 */
class OrderManagementController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->query('status');
        if (! in_array($status, Pesanan::STATUS_PESANAN, true)) {
            $status = null;
        }

        // Filter metode pembayaran memakai id_metode (Tunai, QRIS, ...).
        $metodeList = MetodePembayaran::orderBy('id_metode')->get();
        $metode = $request->filled('metode') ? (int) $request->query('metode') : null;
        if ($metode && ! $metodeList->contains('id_metode', $metode)) {
            $metode = null;
        }

        $terfilter = Pesanan::query()
            ->when($status, fn ($q) => $q->where('status_pesanan', $status))
            ->when($metode, fn ($q) => $q->whereHas('transaksi',
                fn ($t) => $t->where('id_metode', $metode)));

        $pesanan = (clone $terfilter)
            ->with(['detailPesanan.produk:id_produk,nama_produk', 'meja:id_meja,nomor_meja', 'transaksi.metodePembayaran'])
            ->orderByDesc('waktu_pesan')
            ->paginate(10)
            ->withQueryString();

        // Angka kartu status ikut filter metode agar konsisten dengan daftar.
        $hitung = [];
        foreach (Pesanan::STATUS_PESANAN as $s) {
            $hitung[$s] = Pesanan::query()
                ->where('status_pesanan', $s)
                ->when($metode, fn ($q) => $q->whereHas('transaksi',
                    fn ($t) => $t->where('id_metode', $metode)))
                ->count();
        }

        return view('admin.orders.index', [
            'pesanan' => $pesanan,
            'hitung' => $hitung,
            'statusAktif' => $status,
            'statuses' => Pesanan::STATUS_PESANAN,
            'transisi' => Pesanan::TRANSISI_STATUS,
            'metodeList' => $metodeList,
            'metodeAktif' => $metode,
            'jumlahTerfilter' => (clone $terfilter)->count(),
            // Pesanan batal tidak dihitung sebagai nilai transaksi.
            'totalTerfilter' => (float) (clone $terfilter)
                ->where('status_pesanan', '!=', 'dibatalkan')
                ->sum('total_harga'),
            'activeMenu' => 'orders',
        ]);
    }

    public function updateStatus(Request $request, Pesanan $pesanan): RedirectResponse
    {
        $v = $request->validate([
            'status_pesanan' => ['required', 'in:'.implode(',', Pesanan::STATUS_PESANAN)],
        ]);

        $tujuan = $v['status_pesanan'];

        if ($tujuan === 'selesai' && $pesanan->status_pesanan === 'baru') {
            if (! $pesanan->sudahLunas()) {
                return back()->with('error', 'Pesanan belum dibayar. Lunasi terlebih dahulu.');
            }
            $asal = $pesanan->status_pesanan;
            $pesanan->update(['status_pesanan' => 'selesai']);
            Pesanan::sinkronMeja($pesanan->id_meja);

            return back()->with('success', "Pesanan {$pesanan->nomor_pesanan}: {$asal} → selesai.");
        }

        if (! Pesanan::bolehTransisi($pesanan->status_pesanan, $tujuan)) {
            return back()->with('error', "Status {$pesanan->status_pesanan} tidak bisa langsung jadi {$tujuan}.");
        }

        $asal = $pesanan->status_pesanan;
        $pesanan->update(['status_pesanan' => $tujuan]);
        Pesanan::sinkronMeja($pesanan->id_meja);

        return back()->with('success', "Pesanan {$pesanan->nomor_pesanan}: {$asal} → {$tujuan}.");
    }

    public function destroy(Pesanan $pesanan): RedirectResponse
    {
        $nomor = $pesanan->nomor_pesanan;
        $pesanan->batalkan();

        return back()->with('success', "Pesanan {$nomor} dibatalkan — stok & poin dikembalikan.");
    }
}
