<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Notifikasi;
use App\Services\NotifikasiStok;
use Illuminate\Http\JsonResponse;

/**
 * Lonceng notifikasi admin: data polling, tandai satu, dan tandai semua.
 */
class NotifikasiController extends Controller
{
    public function poll(NotifikasiStok $notif): JsonResponse
    {
        // Sapu stok yang sudah habis terlebih dahulu: notifikasi tetap muncul
        // walau stok menjadi 0 sebelum fitur ini dipasang atau lewat jalur
        // yang tidak memicu pemeriksaan (mis. impor data).
        $notif->periksaSemua();

        return response()->json($notif->ringkasan());
    }

    public function baca(Notifikasi $notifikasi): JsonResponse
    {
        $notifikasi->tandaiDibaca();

        return response()->json(['message' => 'Notifikasi ditandai sudah dibaca.']);
    }

    public function bacaSemua(NotifikasiStok $notif): JsonResponse
    {
        Notifikasi::query()->belumDibaca()->update(['dibaca' => true]);

        return response()->json([
            'message' => 'Semua notifikasi ditandai sudah dibaca.',
            'jumlah' => $notif->ringkasan()['jumlah'],
        ]);
    }
}
