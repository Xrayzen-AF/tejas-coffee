<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Admin\SettingController;
use App\Support\CafeGeofence;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Gerbang GPS pelanggan: meminta izin lokasi lalu memverifikasi bahwa
 * perangkat berada di dalam radius cafe sebelum masuk ke aplikasi.
 */
class LokasiController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        if (! CafeGeofence::active()) {
            return redirect()->route('pelanggan.dashboard');
        }

        $data = $request->session()->get(CafeGeofence::SESSION_KEY);
        $terverifikasi = is_array($data)
            && (int) ($data['user_id'] ?? 0) === (int) $request->user()->getKey()
            && now()->timestamp - (int) ($data['verified_at'] ?? 0) <= CafeGeofence::FRESH_SECONDS;

        return view('pelanggan.lokasi', [
            'settings' => CafeGeofence::settings(),
            'store' => SettingController::load(),
            'terverifikasi' => $terverifikasi,
            'jarakTersimpan' => $terverifikasi ? (int) round((float) ($data['distance'] ?? 0)) : null,
            'maxAkurasi' => (int) round(CafeGeofence::maxAccuracy()),
            'targetAkurasi' => (int) round(CafeGeofence::targetAccuracy()),
            'ttl' => CafeGeofence::TTL_MINUTES,
            'interval' => CafeGeofence::WATCH_INTERVAL_SECONDS,
        ]);
    }

    /**
     * Dipanggil watchdog browser selama pelanggan memakai aplikasi.
     * Posisi diperbarui (atau sesi dicabut) tanpa memuat ulang halaman.
     */
    public function cek(Request $request): JsonResponse
    {
        if (! CafeGeofence::active()) {
            return response()->json(['ok' => true, 'required' => false]);
        }

        $v = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['required', 'numeric', 'min:0', 'max:100000'],
        ]);

        $s = CafeGeofence::settings();
        $akurasi = (float) $v['accuracy'];

        if ($akurasi > CafeGeofence::maxAccuracy()) {
            return response()->json([
                'ok' => false,
                'reason' => 'accuracy',
                'max_accuracy' => (int) round(CafeGeofence::maxAccuracy()),
                'message' => 'Sinyal GPS terlalu kasar (akurasi ±'.round($akurasi).' m, maksimal ±'.round(CafeGeofence::maxAccuracy()).' m). Pindah ke area terbuka.',
            ], 422);
        }

        $jarak = CafeGeofence::distance((float) $v['latitude'], (float) $v['longitude'], $s['latitude'], $s['longitude']);

        if (CafeGeofence::effectiveDistance($jarak, $akurasi) > $s['radius']) {
            $request->session()->forget(CafeGeofence::SESSION_KEY);
            // Flash agar halaman gerbang bisa menjelaskan kenapa verifikasi dicabut.
            $request->session()->flash('error', 'Kamu terdeteksi ±'.round($jarak).' m dari cafe (radius '.$s['radius'].' m). Verifikasi lokasi dibatalkan, silakan cek lokasi lagi.');

            return response()->json([
                'ok' => false,
                'reason' => 'outside',
                'distance' => (int) round($jarak),
                'redirect' => route('pelanggan.lokasi'),
                'message' => 'Kamu terdeteksi ±'.round($jarak).' m dari cafe (radius '.$s['radius'].' m). Verifikasi lokasi dibatalkan.',
            ], 403);
        }

        $request->session()->put(CafeGeofence::SESSION_KEY, [
            'user_id' => (int) $request->user()->getKey(),
            'latitude' => (float) $v['latitude'],
            'longitude' => (float) $v['longitude'],
            'accuracy' => $akurasi,
            'distance' => $jarak,
            'verified_at' => now()->timestamp,
        ]);

        return response()->json([
            'ok' => true,
            'distance' => (int) round($jarak),
            'checked_at' => now()->toIso8601String(),
        ]);
    }

    public function verify(Request $request): RedirectResponse
    {
        if (! CafeGeofence::active()) {
            return redirect()->route('pelanggan.dashboard');
        }

        $v = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['required', 'numeric', 'min:0', 'max:100000'],
        ], [
            'latitude.required' => 'Koordinat GPS tidak terbaca. Pastikan GPS aktif lalu coba lagi.',
            'longitude.required' => 'Koordinat GPS tidak terbaca. Pastikan GPS aktif lalu coba lagi.',
            'accuracy.required' => 'Akurasi GPS tidak terbaca. Coba di area terbuka.',
        ]);

        $s = CafeGeofence::settings();
        $akurasi = (float) $v['accuracy'];

        if ($akurasi > CafeGeofence::maxAccuracy()) {
            return back()->with('error', 'Sinyal GPS terlalu lemah (akurasi ±'.round($akurasi).' m, maksimal ±'.round(CafeGeofence::maxAccuracy()).' m). Pindah ke area terbuka lalu coba lagi.');
        }

        $jarak = CafeGeofence::distance((float) $v['latitude'], (float) $v['longitude'], $s['latitude'], $s['longitude']);
        $jarakEfektif = CafeGeofence::effectiveDistance($jarak, $akurasi);

        if ($jarakEfektif > $s['radius']) {
            return back()
                ->withInput()
                ->with('error', 'Kamu berada ±'.round($jarak).' m dari cafe, sedangkan radius yang diizinkan hanya '.$s['radius'].' m. Datang ke cafe untuk memesan.');
        }

        $request->session()->put(CafeGeofence::SESSION_KEY, [
            'user_id' => (int) $request->user()->getKey(),
            'latitude' => (float) $v['latitude'],
            'longitude' => (float) $v['longitude'],
            'accuracy' => $akurasi,
            'distance' => $jarak,
            'verified_at' => now()->timestamp,
        ]);

        $tujuan = $request->session()->pull('geofence.intended');

        return redirect($tujuan ?: route('pelanggan.dashboard'))
            ->with('success', 'Lokasi terverifikasi — kamu berada ±'.round($jarak).' m dari cafe.');
    }
}
