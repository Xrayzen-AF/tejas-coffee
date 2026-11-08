<?php

namespace App\Policies;

use App\Models\Pengguna;
use App\Models\Pesanan;

/**
 * S3-01: otorisasi kepemilikan data pesanan (anti-IDOR).
 *
 * Staf (admin & kasir) boleh melihat semua pesanan; pelanggan hanya boleh
 * melihat pesanan miliknya sendiri. Dipakai lewat $this->authorize('view', $pesanan)
 * sehingga pelanggaran otomatis menghasilkan 403.
 */
class PesananPolicy
{
    /**
     * S3-01: Menentukan apakah pengguna boleh melihat rincian/invoice pesanan tertentu.
     */
    public function view(Pengguna $pengguna, Pesanan $pesanan): bool
    {
        // 1. Admin & Kasir diizinkan melihat seluruh data
        if (in_array($pengguna->peran, ['admin', 'kasir'], true)) {
            return true;
        }

        // 2. Pelanggan HANYA diizinkan melihat pesanan miliknya sendiri (Anti-IDOR)
        if ((int) $pesanan->id_pengguna === (int) $pengguna->id_pengguna) {
            return true;
        }

        // 3. Pesanan bisa tercatat lewat data member (id_pelanggan), bukan id_pengguna,
        //    sehingga dicocokkan lewat nomor telepon akun.
        return (bool) $pengguna->nomor_telepon
            && $pesanan->pelanggan
            && $pesanan->pelanggan->nomor_telepon === $pengguna->nomor_telepon;
    }
}
