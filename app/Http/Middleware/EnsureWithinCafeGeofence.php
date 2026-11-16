<?php

namespace App\Http\Middleware;

use App\Services\MejaKodeService;
use App\Support\CafeGeofence;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menahan pelanggan di halaman verifikasi lokasi sampai GPS-nya
 * terbukti berada di dalam radius cafe. Dipasang pada seluruh route
 * pelanggan (menu, keranjang, checkout) agar pesanan fiktif dari luar
 * cafe tidak bisa dibuat.
 */
class EnsureWithinCafeGeofence
{
    public function __construct(private readonly MejaKodeService $kodeMeja) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! CafeGeofence::active()) {
            return $next($request);
        }

        $user = $request->user();

        if (! $user || $user->peran !== 'pelanggan') {
            return $next($request);
        }

        if ($this->sessionValid($request, (int) $user->getKey())) {
            return $next($request);
        }

        // Jalur alternatif: tamu yang sudah memverifikasi kode sekali pakai
        // dari kasir sedang terbukti ada di meja, jadi tidak perlu GPS.
        if ($this->kodeMeja->sesiValid($request, (int) $user->getKey())) {
            return $next($request);
        }

        // Simpan tujuan awal supaya setelah GPS valid pelanggan
        // kembali ke halaman yang tadi ingin dibuka (mis. checkout).
        $request->session()->put('geofence.intended', $request->fullUrl());

        return redirect()->route('pelanggan.lokasi');
    }

    private function sessionValid(Request $request, int $userId): bool
    {
        $data = $request->session()->get(CafeGeofence::SESSION_KEY);

        if (! is_array($data) || (int) ($data['user_id'] ?? 0) !== $userId) {
            return false;
        }

        $umurDetik = now()->timestamp - (int) ($data['verified_at'] ?? 0);

        // Harus dicek ulang secara berkala (watchdog browser), bukan sekali saja.
        if ($umurDetik < 0 || $umurDetik > CafeGeofence::FRESH_SECONDS) {
            return false;
        }

        $s = CafeGeofence::settings();
        $jarak = CafeGeofence::distance(
            (float) ($data['latitude'] ?? 0),
            (float) ($data['longitude'] ?? 0),
            $s['latitude'],
            $s['longitude'],
        );

        // Radius bisa diubah admin kapan saja: hitung ulang dari titik tersimpan.
        return CafeGeofence::effectiveDistance($jarak, (float) ($data['accuracy'] ?? 0)) <= $s['radius'];
    }
}
