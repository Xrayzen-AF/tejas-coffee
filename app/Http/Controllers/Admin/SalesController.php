<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DetailPesanan;
use App\Models\MetodePembayaran;
use App\Models\Pesanan;
use App\Models\Transaksi;
use App\Services\Agents\SalesAgent;
use App\Services\Agents\StaffingAgent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Sales & Revenue — laporan finansial admin (baca saja).
 * Sumber angka: SalesAgent + tabel transaksi/pesanan.
 */
class SalesController extends Controller
{
    public function index(SalesAgent $sales, StaffingAgent $staffing): View
    {
        $summary = $sales->financialSummary();
        $topBrews = $sales->topSellingBrews(5);
        $telemetry = $staffing->trafficTelemetry();

        // Omzet 7 hari terakhir (lunas saja).
        $driver = Transaksi::query()->getConnection()->getDriverName();
        $tglExpr = $driver === 'sqlite'
            ? 'date(waktu_pembayaran)'
            : 'DATE(waktu_pembayaran)';

        $harian = Transaksi::query()
            ->selectRaw("{$tglExpr} as tanggal, SUM(total_bayar) as omzet, COUNT(*) as transaksi")
            ->where('status_pembayaran', 'lunas')
            ->where('waktu_pembayaran', '>=', now()->subDays(6)->startOfDay())
            ->groupBy('tanggal')
            ->orderBy('tanggal')
            ->get()
            ->keyBy('tanggal');

        $grafik = [];
        for ($i = 6; $i >= 0; $i--) {
            $tgl = now()->subDays($i)->format('Y-m-d');
            $grafik[] = [
                'tanggal' => $tgl,
                'label' => now()->subDays($i)->format('d M'),
                'omzet' => (float) ($harian[$tgl]->omzet ?? 0),
                'transaksi' => (int) ($harian[$tgl]->transaksi ?? 0),
            ];
        }
        $maxOmzet = max(1, collect($grafik)->max('omzet'));

        // Breakdown metode pembayaran hari ini.
        $perMetode = Transaksi::query()
            ->join('metode_pembayaran', 'metode_pembayaran.id_metode', '=', 'transaksi.id_metode')
            ->select('metode_pembayaran.nama_metode')
            ->selectRaw('COUNT(*) as transaksi, SUM(transaksi.total_bayar) as omzet')
            ->where('transaksi.status_pembayaran', 'lunas')
            ->whereDate('transaksi.waktu_pembayaran', today())
            ->groupBy('metode_pembayaran.nama_metode')
            ->orderByDesc('omzet')
            ->get();

        // Status pesanan hari ini.
        $perStatus = Pesanan::query()
            ->select('status_pesanan', DB::raw('COUNT(*) as total'))
            ->whereDate('waktu_pesan', today())
            ->groupBy('status_pesanan')
            ->pluck('total', 'status_pesanan');

        // Margin per produk (HPP = 25% harga jual, konsisten dengan SalesAgent).
        $marginProduk = DetailPesanan::query()
            ->join('produk', 'produk.id_produk', '=', 'detail_pesanan.id_produk')
            ->select('produk.nama_produk')
            ->selectRaw('SUM(detail_pesanan.jumlah) as cups, SUM(detail_pesanan.subtotal) as pendapatan')
            ->whereHas('pesanan', fn ($q) => $q->where('status_pesanan', 'selesai'))
            ->groupBy('produk.id_produk', 'produk.nama_produk')
            ->orderByDesc('pendapatan')
            ->limit(8)
            ->get()
            ->map(fn ($r) => [
                'nama' => $r->nama_produk,
                'cups' => (int) $r->cups,
                'pendapatan' => (float) $r->pendapatan,
                'margin_pct' => 75.0,
                'at_risk' => (int) $r->cups < SalesAgent::VOLUME_AT_RISK_THRESHOLD,
            ]);

        return view('admin.sales.index', [
            'summary' => $summary,
            'topBrews' => $topBrews,
            'telemetry' => $telemetry,
            'grafik' => $grafik,
            'maxOmzet' => $maxOmzet,
            'perMetode' => $perMetode,
            'perStatus' => $perStatus,
            'marginProduk' => $marginProduk,
            'activeMenu' => 'sales',
        ]);
    }

    /**
     * Laporan penjualan siap-cetak (A4).
     *
     * Basis angka: transaksi berstatus lunas pada rentang tanggal.
     * Omzet memakai `transaksi.subtotal` (nilai tagihan) supaya uang tunai
     * berlebih + kembalian tidak ikut terhitung sebagai pendapatan.
     */
    public function cetak(Request $request): View
    {
        ['dari' => $dari, 'sampai' => $sampai, 'catatan' => $catatan, 'mulai' => $mulai, 'selesai' => $selesai, 'tipe' => $tipe, 'metode' => $metode] = $this->filterLaporan($request);

        $lunas = $this->transaksiLunas($mulai, $selesai, $tipe, $metode);

        // Ringkasan nominal transaksi lunas.
        $ringkasan = (clone $lunas)
            ->selectRaw('COUNT(*) as transaksi, COALESCE(SUM(subtotal), 0) as omzet, COALESCE(SUM(total_bayar), 0) as diterima, COALESCE(SUM(kembalian), 0) as kembalian')
            ->first();

        // Potongan & biaya dari pesanan yang benar-benar lunas.
        $ringkasanPesanan = Pesanan::query()
            ->join('transaksi', 'transaksi.id_pesanan', '=', 'pesanan.id_pesanan')
            ->where('transaksi.status_pembayaran', 'lunas')
            ->whereBetween('transaksi.waktu_pembayaran', [$mulai, $selesai])
            ->when($tipe, fn ($q, $t) => $q->where('pesanan.tipe_pesanan', $t))
            ->when($metode, fn ($q, $m) => $q->where('transaksi.id_metode', $m))
            ->selectRaw('COUNT(DISTINCT pesanan.id_pesanan) as pesanan, COALESCE(SUM(pesanan.diskon), 0) as diskon, COALESCE(SUM(pesanan.biaya_layanan), 0) as layanan')
            ->first();

        // Item & produk terjual (hanya dari pesanan lunas pada periode).
        $detailTerjual = DetailPesanan::query()
            ->join('pesanan', 'pesanan.id_pesanan', '=', 'detail_pesanan.id_pesanan')
            ->join('transaksi', 'transaksi.id_pesanan', '=', 'pesanan.id_pesanan')
            ->where('transaksi.status_pembayaran', 'lunas')
            ->whereBetween('transaksi.waktu_pembayaran', [$mulai, $selesai])
            ->when($tipe, fn ($q, $t) => $q->where('pesanan.tipe_pesanan', $t))
            ->when($metode, fn ($q, $m) => $q->where('transaksi.id_metode', $m));

        $itemTerjual = (int) (clone $detailTerjual)->sum('detail_pesanan.jumlah');

        $perProduk = (clone $detailTerjual)
            ->join('produk', 'produk.id_produk', '=', 'detail_pesanan.id_produk')
            ->select('produk.nama_produk')
            ->selectRaw('SUM(detail_pesanan.jumlah) as cups, SUM(detail_pesanan.subtotal) as pendapatan')
            ->groupBy('produk.id_produk', 'produk.nama_produk')
            ->orderByDesc('pendapatan')
            ->get();

        // Rekap harian.
        $driver = Transaksi::query()->getConnection()->getDriverName();
        $tglExpr = $driver === 'sqlite' ? 'date(waktu_pembayaran)' : 'DATE(waktu_pembayaran)';

        $perHari = (clone $lunas)
            ->selectRaw("{$tglExpr} as tanggal, COUNT(*) as transaksi, SUM(subtotal) as omzet")
            ->groupBy('tanggal')
            ->orderBy('tanggal')
            ->get();

        // Rekap metode pembayaran.
        $perMetode = (clone $lunas)
            ->join('metode_pembayaran', 'metode_pembayaran.id_metode', '=', 'transaksi.id_metode')
            ->select('metode_pembayaran.nama_metode')
            ->selectRaw('COUNT(*) as transaksi, SUM(transaksi.subtotal) as omzet')
            ->groupBy('metode_pembayaran.nama_metode')
            ->orderByDesc('omzet')
            ->get();

        // Daftar transaksi untuk lampiran rinci.
        $daftarTransaksi = (clone $lunas)
            ->with(['pesanan.meja', 'pesanan.pengguna', 'metodePembayaran'])
            ->orderBy('waktu_pembayaran')
            ->orderBy('id_transaksi')
            ->get();

        // Pesanan batal pada periode (tidak dihitung omzet).
        $batal = Pesanan::query()
            ->whereBetween('waktu_pesan', [$mulai, $selesai])
            ->where('status_pesanan', 'dibatalkan')
            ->when($tipe, fn ($q, $t) => $q->where('tipe_pesanan', $t))
            ->selectRaw('COUNT(*) as jumlah, COALESCE(SUM(total_harga), 0) as nilai')
            ->first();

        return view('admin.sales.print', [
            'dari' => $dari,
            'sampai' => $sampai,
            'catatan' => $catatan,
            'toko' => SettingController::load(),
            'ringkasan' => $ringkasan,
            'ringkasanPesanan' => $ringkasanPesanan,
            'itemTerjual' => $itemTerjual,
            'perProduk' => $perProduk,
            'perHari' => $perHari,
            'perMetode' => $perMetode,
            'daftarTransaksi' => $daftarTransaksi,
            'batal' => $batal,
            'tipe' => $tipe,
            'metode' => $metode,
            'daftarMetode' => MetodePembayaran::query()->orderBy('nama_metode')->get(['id_metode', 'nama_metode']),
            'dicetakOleh' => $request->user()?->nama_lengkap ?? 'Admin',
            'dicetakPada' => now(),
        ]);
    }

    /**
     * Unduh rincian transaksi lunas sebagai CSV untuk periode yang sama
     * dengan laporan cetak (dari/sampai di query string).
     *
     * Format: pemisah titik-koma + BOM UTF-8 agar langsung rapi di Excel
     * (locale Indonesia memakai koma sebagai desimal).
     */
    public function exportCsv(Request $request): StreamedResponse
    {
        ['dari' => $dari, 'sampai' => $sampai, 'mulai' => $mulai, 'selesai' => $selesai, 'tipe' => $tipe, 'metode' => $metode] = $this->filterLaporan($request);

        $transaksi = $this->transaksiLunas($mulai, $selesai, $tipe, $metode)
            ->with(['pesanan.meja', 'pesanan.pengguna', 'metodePembayaran'])
            ->orderBy('waktu_pembayaran')
            ->orderBy('id_transaksi')
            ->get();

        $namaFile = 'laporan-transaksi-'.$dari->format('Ymd').'-'.$sampai->format('Ymd')
            .($tipe ? '-'.$tipe : '')
            .($metode ? '-'.strtolower(MetodePembayaran::query()->whereKey($metode)->value('kode_metode') ?? 'metode') : '')
            .'.csv';

        return response()->streamDownload(function () use ($transaksi) {
            $out = fopen('php://output', 'w');

            // BOM supaya Excel membaca UTF-8 dengan benar.
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, [
                'No', 'Waktu', 'No. Pesanan', 'Tipe', 'Meja', 'Kasir / Pelayan',
                'Metode', 'Total (nilai tagihan)', 'Uang Diterima', 'Kembalian',
            ], ';', '"', '\\', "\r\n");

            foreach ($transaksi as $i => $t) {
                $pelayan = ($t->pesanan?->pengguna?->peran ?? null) === 'pelanggan'
                    ? 'Self Order'
                    : ($t->pesanan?->pengguna?->nama_lengkap ?? '-');

                fputcsv($out, [
                    $i + 1,
                    $t->waktu_pembayaran?->format('Y-m-d H:i') ?? '-',
                    $t->pesanan?->nomor_pesanan ?? '-',
                    match ($t->pesanan?->tipe_pesanan) {
                        'dine-in' => 'Dine-in',
                        'takeaway' => 'Takeaway',
                        default => 'Delivery',
                    },
                    $t->pesanan?->meja?->nomor_meja ?? '-',
                    $pelayan,
                    $t->metodePembayaran?->nama_metode ?? '-',
                    (int) $t->subtotal,
                    (int) $t->total_bayar,
                    (int) $t->kembalian,
                ], ';', '"', '\\', "\r\n");
            }

            fputcsv($out, [
                'TOTAL', '', '', '', '', '', '',
                (int) $transaksi->sum('subtotal'),
                (int) $transaksi->sum('total_bayar'),
                (int) $transaksi->sum('kembalian'),
            ], ';', '"', '\\', "\r\n");

            fclose($out);
        }, $namaFile, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Filter laporan dari query string:
     * - `dari`/`sampai` (Y-m-d): default awal bulan ini s/d hari ini, tanggal
     *   tidak valid diabaikan, tanggal tertukar dibalik, rentang maks 366 hari.
     * - `tipe`: dine-in / takeaway / delivery (nilai lain diabaikan).
     * - `metode`: id_metode yang benar-benar ada (nilai lain diabaikan).
     *
     * @return array{dari: Carbon, sampai: Carbon, catatan: ?string, mulai: Carbon, selesai: Carbon, tipe: ?string, metode: ?int}
     */
    private function filterLaporan(Request $request): array
    {
        $catatan = [];

        $dari = $this->parseTanggal($request->query('dari')) ?? now()->startOfMonth()->startOfDay();
        $sampai = $this->parseTanggal($request->query('sampai')) ?? today()->startOfDay();

        if ($sampai->lt($dari)) {
            [$dari, $sampai] = [$sampai, $dari];
            $catatan[] = 'Tanggal awal dan akhir tertukar — urutannya dibalik otomatis.';
        }

        if ($dari->diffInDays($sampai) > 365) {
            $dari = $sampai->copy()->subDays(365);
            $catatan[] = 'Rentang laporan dibatasi maksimal 366 hari.';
        }

        $tipeInput = (string) $request->query('tipe', '');
        $tipe = in_array($tipeInput, Pesanan::TIPE_PESANAN, true) ? $tipeInput : null;

        if ($tipeInput !== '' && $tipe === null) {
            $catatan[] = 'Filter tipe pesanan tidak dikenal — diabaikan.';
        }

        $metode = null;
        $metodeInput = (string) $request->query('metode', '');

        if ($metodeInput !== '') {
            $metode = ctype_digit($metodeInput)
                ? MetodePembayaran::query()->whereKey((int) $metodeInput)->value('id_metode')
                : null;

            if ($metode === null) {
                $catatan[] = 'Filter metode pembayaran tidak dikenal — diabaikan.';
            }
        }

        return [
            'dari' => $dari,
            'sampai' => $sampai,
            'catatan' => $catatan === [] ? null : implode(' ', $catatan),
            'mulai' => $dari->copy()->startOfDay(),
            'selesai' => $sampai->copy()->endOfDay(),
            'tipe' => $tipe,
            'metode' => $metode === null ? null : (int) $metode,
        ];
    }

    /**
     * Transaksi lunas pada rentang waktu tertentu (basis semua angka laporan),
     * opsional disaring per tipe pesanan dan metode pembayaran.
     */
    private function transaksiLunas(Carbon $mulai, Carbon $selesai, ?string $tipe = null, ?int $metode = null): Builder
    {
        return Transaksi::query()
            ->where('status_pembayaran', 'lunas')
            ->whereBetween('waktu_pembayaran', [$mulai, $selesai])
            ->when($tipe, fn ($q, $t) => $q->whereHas('pesanan', fn ($p) => $p->where('tipe_pesanan', $t)))
            ->when($metode, fn ($q, $m) => $q->where('transaksi.id_metode', $m));
    }

    /** Parse `Y-m-d` secara ketat; null bila kosong/tidak valid. */
    private function parseTanggal(?string $value): ?Carbon
    {
        $value = trim((string) $value);

        if (! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m) || ! checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return null;
        }

        return Carbon::create((int) $m[1], (int) $m[2], (int) $m[3])->startOfDay();
    }
}
