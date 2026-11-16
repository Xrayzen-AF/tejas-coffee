<?php

namespace App\Services;

use App\Models\Meja;
use App\Models\Pengguna;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Kode sekali pakai per meja.
 *
 * QR meja hanya identitas meja — siapa pun yang memfoto QR bisa membukanya
 * dari luar cafe. Karena itu kehadiran tamu dibuktikan dengan PIN 6 digit
 * yang hanya bisa diterbitkan staf yang sedang melayani meja tersebut:
 *
 * - berlaku 3 menit, satu kode aktif per meja (terbit ulang membatalkan lama);
 * - disimpan sebagai HMAC (bukan plaintext) di cache, jadi bocornya cache
 *   tidak langsung membocorkan kode;
 * - sekali pakai (dihapus dalam lock setelah verifikasi berhasil);
 * - maksimal 5 percobaan agar tidak bisa ditebak.
 */
class MejaKodeService
{
    /** Masa berlaku kode (detik). */
    public const TTL_DETIK = 180;

    /** Maksimal percobaan salah per kode. */
    public const MAKS_PERCOBAAN = 5;

    /** Lama sesi "sudah verifikasi kode" sebelum tamu harus minta kode lagi. */
    public const SESI_MENIT = 120;

    public const SESSION_KEY = 'meja_kode';

    /** Permintaan PIN dari pelanggan tetap dianggap aktif selama 5 menit. */
    public const PERMINTAAN_TTL_DETIK = 300;

    /** PIN hanya ditampilkan ke pelanggan selama 1 menit. */
    public const KIRIM_TTL_DETIK = 60;

    /** Jeda minimal antar-permintaan agar kasir tidak dibanjiri. */
    public const JEDA_MINTA_DETIK = 15;

    public const SESSION_PERMINTAAN = 'meja_pin_permintaan';

    /**
     * Terbitkan kode baru untuk satu meja. Mengembalikan kode plaintext
     * satu-satunya kali; yang disimpan hanya hash-nya.
     *
     * @return array{kode: string, kedaluwarsa_detik: int}
     */
    public function terbitkan(Meja $meja, Pengguna $staf): array
    {
        $kode = (string) random_int(100000, 999999);

        Cache::put($this->kunciKode($meja), [
            'hash' => $this->hash($kode),
            'id_meja' => (int) $meja->id_meja,
            'id_penerbit' => (int) $staf->id_pengguna,
            'kedaluwarsa' => now()->addSeconds(self::TTL_DETIK)->timestamp,
        ], self::TTL_DETIK);

        Cache::forget($this->kunciPercobaan($meja));

        // Ada pelanggan yang menekan "Minta PIN"? Kirim PIN-nya ke halaman
        // pelanggan tersebut selama 1 menit. Disimpan terenkripsi agar isi
        // cache tidak membocorkan PIN. Permintaan dianggap terpenuhi.
        if ($permintaan = $this->permintaanAktif($meja)) {
            Cache::put($this->kunciKirim($meja), [
                'id' => $permintaan['id'],
                'kode' => Crypt::encryptString($kode),
                'kedaluwarsa' => now()->addSeconds(self::KIRIM_TTL_DETIK)->timestamp,
            ], self::KIRIM_TTL_DETIK);

            Cache::forget($this->kunciPermintaan($meja));
        } else {
            // Tidak ada permintaan tertunda: PIN yang masih terpampang di layar
            // pelanggan sudah basi (hash-nya baru saja diganti), jangan ditampilkan.
            Cache::forget($this->kunciKirim($meja));
        }

        return ['kode' => $kode, 'kedaluwarsa_detik' => self::TTL_DETIK];
    }

    /**
     * Pelanggan meminta PIN untuk meja ini. Melempar ValidationException
     * bila permintaan sebelumnya masih terlalu baru / PIN belum kedaluwarsa.
     *
     * @return array{kedaluwarsa_detik: int}
     */
    public function minta(Meja $meja, Request $request): array
    {
        $lock = Cache::lock($this->kunciLock($meja), 5);
        $lock->block(3);

        try {
            $kirim = Cache::get($this->kunciKirim($meja));
            if (is_array($kirim) && (int) ($kirim['kedaluwarsa'] ?? 0) > now()->timestamp) {
                throw ValidationException::withMessages([
                    'pin' => 'PIN untuk meja ini baru saja dikirim. Masukkan PIN yang tampil di halaman Anda.',
                ]);
            }

            $permintaan = $this->permintaanAktif($meja);
            if ($permintaan && $permintaan['umur_detik'] < self::JEDA_MINTA_DETIK) {
                throw ValidationException::withMessages([
                    'pin' => 'Permintaan sudah terkirim ke kasir. Tunggu sebentar, kasir sedang menyiapkan PIN.',
                ]);
            }

            $id = (string) Str::uuid();

            Cache::put($this->kunciPermintaan($meja), [
                'id' => $id,
                'diminta_pada' => now()->timestamp,
                'kedaluwarsa' => now()->addSeconds(self::PERMINTAAN_TTL_DETIK)->timestamp,
            ], self::PERMINTAAN_TTL_DETIK);

            $request->session()->put(self::SESSION_PERMINTAAN.'.'.$meja->id_meja, $id);

            return ['kedaluwarsa_detik' => self::PERMINTAAN_TTL_DETIK];
        } finally {
            $lock->release();
        }
    }

    /**
     * Permintaan PIN yang masih menunggu diterbitkan kasir (untuk layar kasir).
     *
     * @return array{id: string, umur_detik: int}|null
     */
    public function permintaanAktif(Meja $meja): ?array
    {
        $data = Cache::get($this->kunciPermintaan($meja));

        if (! is_array($data) || (int) ($data['kedaluwarsa'] ?? 0) <= now()->timestamp) {
            return null;
        }

        return [
            'id' => (string) ($data['id'] ?? ''),
            'umur_detik' => max(0, now()->timestamp - (int) ($data['diminta_pada'] ?? now()->timestamp)),
        ];
    }

    /**
     * PIN yang baru dikirim kasir ke pelanggan peminta (hanya 1 menit dan
     * hanya untuk sesi pelanggan yang memintanya).
     *
     * @return array{kode: string, sisa_detik: int}|null
     */
    public function pinTerkirim(Meja $meja, Request $request): ?array
    {
        $data = Cache::get($this->kunciKirim($meja));

        if (! is_array($data)) {
            return null;
        }

        $idSesi = $request->session()->get(self::SESSION_PERMINTAAN.'.'.$meja->id_meja);

        if (! $idSesi || ! hash_equals((string) $data['id'], (string) $idSesi)) {
            return null;
        }

        $sisa = (int) ($data['kedaluwarsa'] ?? 0) - now()->timestamp;

        if ($sisa <= 0) {
            return null;
        }

        try {
            $kode = Crypt::decryptString((string) $data['kode']);
        } catch (\Throwable) {
            return null;
        }

        return ['kode' => $kode, 'sisa_detik' => $sisa];
    }

    /** Batalkan kode aktif (mis. tamu sudah pergi atau salah meja). */
    public function batalkan(Meja $meja): void
    {
        Cache::forget($this->kunciKode($meja));
        Cache::forget($this->kunciPercobaan($meja));
        Cache::forget($this->kunciPermintaan($meja));
        Cache::forget($this->kunciKirim($meja));
    }

    /** Sisa waktu kode aktif (detik), atau null bila tidak ada kode. */
    public function sisaDetik(Meja $meja): ?int
    {
        $data = Cache::get($this->kunciKode($meja));

        if (! is_array($data)) {
            return null;
        }

        $sisa = (int) ($data['kedaluwarsa'] ?? 0) - now()->timestamp;

        return $sisa > 0 ? $sisa : null;
    }

    /**
     * Verifikasi kode tamu. Melempar ValidationException bila gagal.
     * Kode dihapus setelah berhasil sehingga tidak bisa dipakai dua kali.
     */
    public function verifikasi(Meja $meja, string $kode): void
    {
        $kode = preg_replace('/\D/', '', $kode) ?? '';

        if (strlen($kode) !== 6) {
            throw ValidationException::withMessages([
                'kode_meja' => 'Kode terdiri dari 6 angka. Minta kode terbaru ke kasir.',
            ]);
        }

        $lock = Cache::lock($this->kunciLock($meja), 5);
        $lock->block(3);

        try {
            $data = Cache::get($this->kunciKode($meja));

            if (! is_array($data) || (int) ($data['kedaluwarsa'] ?? 0) <= now()->timestamp) {
                throw ValidationException::withMessages([
                    'kode_meja' => 'Kode sudah kedaluwarsa. Minta kasir menerbitkan kode baru.',
                ]);
            }

            $percobaan = (int) Cache::get($this->kunciPercobaan($meja), 0) + 1;

            if ($percobaan > self::MAKS_PERCOBAAN) {
                throw ValidationException::withMessages([
                    'kode_meja' => 'Terlalu banyak percobaan. Minta kasir menerbitkan kode baru.',
                ]);
            }

            Cache::put(
                $this->kunciPercobaan($meja),
                $percobaan,
                max(1, (int) $data['kedaluwarsa'] - now()->timestamp),
            );

            if (! hash_equals((string) $data['hash'], $this->hash($kode))) {
                throw ValidationException::withMessages([
                    'kode_meja' => 'Kode tidak cocok. Periksa kembali atau minta kode baru ke kasir.',
                ]);
            }

            // Sekali pakai: buang kode & penghitung percobaan.
            Cache::forget($this->kunciKode($meja));
            Cache::forget($this->kunciPercobaan($meja));
        } finally {
            $lock->release();
        }
    }

    /** Simpan bukti verifikasi di sesi (terikat ke akun & meja). */
    public function simpanSesi(Request $request, Meja $meja, ?Pengguna $pengguna): void
    {
        $request->session()->put(self::SESSION_KEY, [
            'id_meja' => (int) $meja->id_meja,
            // 0 = tamu tanpa akun; identitasnya cukup sesi perangkat ini.
            'user_id' => $pengguna ? (int) $pengguna->id_pengguna : 0,
            'verified_at' => now()->timestamp,
        ]);
    }

    /**
     * Meja yang terverifikasi pada sesi ini, baik lewat akun pelanggan
     * maupun tamu tanpa akun (kode kasir). Null bila belum/tidak berlaku.
     */
    public function mejaTerverifikasi(Request $request, ?int $userId = null): ?int
    {
        $data = $request->session()->get(self::SESSION_KEY);

        if (! is_array($data) || empty($data['id_meja'])) {
            return null;
        }

        // Verifikasi milik akun: bila perangkat ini dipakai akun lain
        // (mis. logout lalu login sebagai member lain), sesi meja lama
        // tidak boleh ikut terbawa.
        if ($userId !== null && (int) ($data['user_id'] ?? 0) !== $userId) {
            return null;
        }

        $umur = now()->timestamp - (int) ($data['verified_at'] ?? 0);

        if ($umur < 0 || $umur > self::SESI_MENIT * 60) {
            return null;
        }

        return (int) $data['id_meja'];
    }

    /** Apakah sesi ini punya kode meja yang masih berlaku untuk akun tsb. */
    public function sesiValid(Request $request, int $userId): bool
    {
        $data = $request->session()->get(self::SESSION_KEY);

        if (! is_array($data) || (int) ($data['user_id'] ?? 0) !== $userId) {
            return false;
        }

        $umur = now()->timestamp - (int) ($data['verified_at'] ?? 0);

        return $umur >= 0 && $umur <= self::SESI_MENIT * 60;
    }

    /** Sisa masa berlaku sesi kode (detik), atau null bila tidak ada. */
    public function sisaSesi(Request $request, int $userId): ?int
    {
        if (! $this->sesiValid($request, $userId)) {
            return null;
        }

        $data = $request->session()->get(self::SESSION_KEY);
        $sisa = self::SESI_MENIT * 60 - (now()->timestamp - (int) $data['verified_at']);

        return max(0, $sisa);
    }

    public function lupakanSesi(Request $request): void
    {
        $request->session()->forget(self::SESSION_KEY);
    }

    /** Hapus PIN yang sudah terkirim ke layar pelanggan (mis. setelah dipakai). */
    public function lupakanPengiriman(Meja $meja, Request $request): void
    {
        Cache::forget($this->kunciKirim($meja));
        $request->session()->forget(self::SESSION_PERMINTAAN.'.'.$meja->id_meja);
    }

    private function hash(string $kode): string
    {
        return hash_hmac('sha256', $kode, (string) config('app.key'));
    }

    private function kunciKode(Meja $meja): string
    {
        return 'meja-kode:'.$meja->id_meja;
    }

    private function kunciPercobaan(Meja $meja): string
    {
        return 'meja-kode-percobaan:'.$meja->id_meja;
    }

    private function kunciLock(Meja $meja): string
    {
        return 'meja-kode-lock:'.$meja->id_meja;
    }

    private function kunciPermintaan(Meja $meja): string
    {
        return 'meja-pin-permintaan:'.$meja->id_meja;
    }

    private function kunciKirim(Meja $meja): string
    {
        return 'meja-pin-kirim:'.$meja->id_meja;
    }
}
