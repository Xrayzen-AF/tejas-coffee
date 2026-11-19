<?php

namespace App\Http\Controllers;

use App\Models\Meja;
use App\Services\MejaKodeService;
use App\Services\MejaQrService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Halaman yang terbuka setelah pelanggan scan QR fisik di meja.
 * QR hanya menunjuk meja; identitas pelanggan tetap dari akun login,
 * atau dari kode booking bila pelanggan tidak bisa login.
 */
class MejaQrController extends Controller
{
    public function __construct(
        private readonly MejaQrService $mejaQr,
        private readonly MejaKodeService $mejaKode,
    ) {}

    /**
     * Pemindai QR di dalam aplikasi: kamera membaca QR fisik di meja,
     * lalu diarahkan ke halaman status meja (meja.scan).
     */
    public function pindai(): View
    {
        return view('pelanggan.meja-pindai');
    }

    public function show(Request $request, Meja $meja): View
    {
        $idTerverifikasi = $request->session()->get($this->kunciVerifikasi($meja));
        $idTerverifikasi = is_numeric($idTerverifikasi) ? (int) $idTerverifikasi : null;

        $state = $this->mejaQr->status($meja, $request->user(), $idTerverifikasi);

        // Sesi kode kasir hanya boleh diakui untuk meja yang benar-benar
        // diverifikasi. Tanpa pengecekan ini, pelanggan yang masih memegang
        // sesi meja lama melihat "sudah terverifikasi" di meja baru padahal
        // PIN meja itu belum pernah dimasukkan — pesanan lalu jatuh ke meja lama.
        $sesiMeja = $this->mejaKode->mejaTerverifikasi(
            $request,
            $request->user() ? (int) $request->user()->getKey() : 0,
        );
        $sesiMeja = $sesiMeja !== null && (int) $sesiMeja === (int) $meja->id_meja ? $sesiMeja : null;

        return view('pelanggan.meja-scan', [
            'meja' => $meja,
            'state' => $state,
            'mejaKosong' => $this->mejaQr->mejaKosong($meja->id_meja),
            // Sesi tamu yang lolos verifikasi kode (bukan lewat akun login).
            'terverifikasi' => $state['kode'] === MejaQrService::KODE_MILIK_SENDIRI
                && ! $request->user(),
            // Sisa masa berlaku bukti kehadiran via kode kasir untuk meja ini
            // (null bila belum verifikasi atau sesinya milik meja lain).
            'kodeKasirDetik' => $sesiMeja !== null
                ? $this->mejaKode->sisaSesi(
                    $request,
                    $request->user() ? (int) $request->user()->getKey() : 0,
                )
                : null,
        ]);
    }

    /**
     * Verifikasi kode booking untuk tamu yang tidak bisa login.
     * Dibatasi throttle di rute agar kode tidak bisa ditebak berulang.
     */
    public function verifikasi(Request $request, Meja $meja): RedirectResponse
    {
        $validated = $request->validate([
            'kode_booking' => ['required', 'string', 'max:12'],
        ], [
            'kode_booking.required' => 'Kode booking wajib diisi.',
        ]);

        $reservasi = $this->mejaQr->verifikasiKode($meja, $validated['kode_booking']);

        $request->session()->put($this->kunciVerifikasi($meja), $reservasi->id_reservasi);

        return redirect()
            ->route('meja.scan', ['meja' => $meja->qr_token])
            ->with('success', "Kode booking cocok. Meja {$meja->nomor_meja} tercatat atas nama Anda.");
    }

    /**
     * Verifikasi kode sekali pakai yang diterbitkan kasir/barista.
     * Jalur ini menggantikan verifikasi GPS bagi tamu yang sudah di meja.
     */
    public function verifikasiKode(Request $request, Meja $meja): RedirectResponse
    {
        $validated = $request->validate([
            'kode_meja' => ['required', 'string', 'max:6'],
        ], [
            'kode_meja.required' => 'Kode dari kasir wajib diisi.',
        ]);

        $this->mejaKode->verifikasi($meja, $validated['kode_meja']);
        $this->mejaKode->simpanSesi($request, $meja, $request->user());
        // PIN sudah terpakai: jangan tampilkan lagi di layar pelanggan.
        $this->mejaKode->lupakanPengiriman($meja, $request);

        return redirect()
            ->route('meja.scan', ['meja' => $meja->qr_token])
            ->with('success', "Kode cocok. Kehadiran Anda di meja {$meja->nomor_meja} sudah terverifikasi.");
    }

    /**
     * Pelanggan menekan "Minta PIN ke kasir". Permintaan masuk ke layar kasir; PIN
     * akan dikirim balik ke halaman ini begitu kasir menerbitkannya.
     */
    public function mintaPin(Request $request, Meja $meja): JsonResponse
    {
        try {
            $hasil = $this->mejaKode->minta($meja, $request);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->validator->errors()->first('pin'),
            ], 429);
        }

        return response()->json([
            'status' => 'success',
            'kedaluwarsa_detik' => $hasil['kedaluwarsa_detik'],
            'message' => 'Permintaan PIN terkirim ke kasir. PIN akan muncul otomatis di halaman ini.',
        ]);
    }

    /** Polling pelanggan: apakah kasir sudah mengirim PIN untuk meja ini? */
    public function pinStatus(Request $request, Meja $meja): JsonResponse
    {
        if ($pin = $this->mejaKode->pinTerkirim($meja, $request)) {
            return response()->json([
                'status' => 'siap',
                'kode' => $pin['kode'],
                'sisa_detik' => $pin['sisa_detik'],
            ])->header('Cache-Control', 'no-store, max-age=0');
        }

        $permintaan = $this->mejaKode->permintaanAktif($meja);

        return response()->json([
            'status' => $permintaan ? 'menunggu' : 'tidak_ada',
            'umur_detik' => $permintaan['umur_detik'] ?? null,
        ])->header('Cache-Control', 'no-store, max-age=0');
    }

    public function checkin(Request $request, Meja $meja): RedirectResponse
    {
        $reservasi = $this->mejaQr->klaim($meja, $request->user());

        return redirect()
            ->route('meja.scan', ['meja' => $meja->qr_token])
            ->with('success', "Check-in berhasil. Meja {$meja->nomor_meja} sekarang atas nama Anda (kode {$reservasi->kode_booking}).");
    }

    public function lepas(Request $request, Meja $meja): RedirectResponse
    {
        $this->mejaQr->lepas($meja, $request->user());

        return redirect()
            ->route('meja.scan', ['meja' => $meja->qr_token])
            ->with('success', "Terima kasih. Meja {$meja->nomor_meja} sudah dilepas.");
    }

    private function kunciVerifikasi(Meja $meja): string
    {
        return 'meja_verifikasi_'.$meja->id_meja;
    }
}
