<?php

namespace App\Http\Controllers;

use App\Models\Meja;
use App\Services\MejaKodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Penerbitan kode sekali pakai per meja (kasir/barista).
 *
 * Kode hanya ditampilkan sekali di layar staf, berlaku 3 menit, dan
 * menggantikan verifikasi GPS sebagai bukti tamu benar-benar di meja.
 */
class MejaKodeController extends Controller
{
    public function __construct(private readonly MejaKodeService $kode) {}

    public function index(): View
    {
        $daftarMeja = Meja::orderBy('nomor_meja')->get();

        return view('kasir.kode-meja', [
            'daftarMeja' => $daftarMeja,
            'kodeAktif' => $daftarMeja->mapWithKeys(
                fn (Meja $meja) => [$meja->id_meja => $this->kode->sisaDetik($meja)]
            ),
            'ttlDetik' => MejaKodeService::TTL_DETIK,
            'maksPercobaan' => MejaKodeService::MAKS_PERCOBAAN,
        ]);
    }

    /** Terbitkan kode baru; dipanggil via fetch dari halaman staf. */
    public function terbitkan(Request $request, Meja $meja): JsonResponse
    {
        $hasil = $this->kode->terbitkan($meja, $request->user());

        return response()->json([
            'kode' => $hasil['kode'],
            'kedaluwarsa_detik' => $hasil['kedaluwarsa_detik'],
            'meja' => $meja->nomor_meja,
        ]);
    }

    /** Batalkan kode yang masih aktif untuk satu meja. */
    public function batalkan(Meja $meja): JsonResponse
    {
        $this->kode->batalkan($meja);

        return response()->json([
            'message' => 'Kode Meja '.$meja->nomor_meja.' dibatalkan.',
        ]);
    }

    /** Status meja + sisa waktu kode, dipolling halaman staf. */
    public function status(): JsonResponse
    {
        $meja = Meja::orderBy('nomor_meja')->get(['id_meja', 'nomor_meja', 'kapasitas', 'status']);

        return response()->json([
            'tables' => $meja->map(function (Meja $m) {
                $permintaan = $this->kode->permintaanAktif($m);
                $lepas = $this->kode->lepasTerakhir($m);

                return [
                    'id_meja' => (int) $m->id_meja,
                    'nomor_meja' => $m->nomor_meja,
                    'kapasitas' => (int) $m->kapasitas,
                    'status' => $m->status,
                    'sisa_detik' => $this->kode->sisaDetik($m),
                    // Pelanggan sedang menunggu PIN? Kasir melihat penandanya.
                    'permintaan_id' => $permintaan['id'] ?? null,
                    'permintaan_detik' => $permintaan['umur_detik'] ?? null,
                    // Meja yang baru dilepas pelanggan (penanda 2 menit).
                    'lepas_id' => $lepas['id'] ?? null,
                    'lepas_detik' => $lepas['lepas_detik'] ?? null,
                ];
            })->values(),
        ]);
    }
}
