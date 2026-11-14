<?php

namespace App\Support;

use App\Http\Controllers\Admin\SettingController;

/**
 * Geofence kedatangan pelanggan.
 *
 * Titik cafe + radius disimpan di Store Settings (storage/app/store-settings.json).
 * Verifikasi GPS disimpan di sesi, jadi tidak perlu kolom/tabel baru.
 */
class CafeGeofence
{
    public const SESSION_KEY = 'cafe_geo';

    /** Verifikasi GPS dianggap basi setelah 30 menit. */
    public const TTL_MINUTES = 30;

    /**
     * Pengecekan ulang realtime: watchdog browser mengirim posisi tiap 60 detik,
     * middleware menolak verifikasi yang lebih tua dari 3 menit.
     */
    public const FRESH_SECONDS = 180;

    /** Interval watchdog di browser (detik). */
    public const WATCH_INTERVAL_SECONDS = 60;

    /** @return array{enabled: bool, latitude: ?float, longitude: ?float, radius: int} */
    public static function settings(): array
    {
        $s = SettingController::load();

        return [
            'enabled' => filter_var($s['geofence_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'latitude' => self::toFloat($s['cafe_latitude'] ?? null),
            'longitude' => self::toFloat($s['cafe_longitude'] ?? null),
            'radius' => max(10, (int) ($s['radius_meter'] ?? 150)),
        ];
    }

    /**
     * Geofence hanya menahan pelanggan jika admin sudah mengaktifkan
     * dan titik cafe sudah diisi.
     */
    public static function active(): bool
    {
        $s = self::settings();

        return $s['enabled'] && $s['latitude'] !== null && $s['longitude'] !== null;
    }

    /**
     * Target akurasi yang dikejar browser sebelum posisi dikirim (meter).
     * Semakin kecil radius, semakin ketat targetnya.
     */
    public static function targetAccuracy(): float
    {
        return round(max(15.0, min(30.0, (float) self::settings()['radius'] * 0.2)), 1);
    }

    /**
     * Batas akurasi GPS yang diterima server (meter).
     * Dihitung dari radius supaya pembacaan kasar tidak bisa dipakai
     * untuk lolos dari radius kecil.
     */
    public static function maxAccuracy(): float
    {
        return round(max(25.0, min(60.0, (float) self::settings()['radius'] * 0.35)), 1);
    }

    /** Jarak dua koordinat dalam meter (haversine). */
    public static function distance(?float $lat1, ?float $lng1, ?float $lat2, ?float $lng2): float
    {
        if ($lat1 === null || $lng1 === null || $lat2 === null || $lng2 === null) {
            return INF;
        }

        $earth = 6371000.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $earth * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /**
     * Jarak efektif: hanya separuh akurasi dipakai sebagai toleransi.
     * Akurasi adalah radius ketidakpastian; memakai nilai penuh membuat
     * pembacaan kasar bisa lolos radius (mis. 200 m dengan akurasi 150 m).
     */
    public static function effectiveDistance(float $distance, float $accuracy): float
    {
        return max(0.0, $distance - ($accuracy / 2));
    }

    private static function toFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (float) $value : null;
    }
}
