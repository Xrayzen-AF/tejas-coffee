<?php

namespace App\Services;

use App\Models\Notifikasi;
use App\Models\Produk;
use App\Models\StokBiji;

/**
 * Deteksi stok habis dan pembuatan notifikasi admin.
 *
 * Notifikasi hanya dibuat sekali per item selama belum dibaca, sehingga
 * lonceng admin tidak dibanjiri pesan berulang setiap ada pesanan baru.
 * Saat stok diisi kembali, notifikasi lama otomatis ditandai sudah dibaca.
 */
class NotifikasiStok
{
    /**
     * Periksa stok satu menu; null bila stok masih tersedia.
     *
     * $harian dipakai penyapuan berkala: cukup satu notifikasi per produk
     * per hari, supaya notifikasi tidak muncul lagi setelah admin membacanya
     * padahal stok memang masih kosong.
     */
    public function periksaProduk(Produk $produk, bool $harian = false): ?Notifikasi
    {
        if ((int) $produk->stok > 0) {
            $this->tandaiProdukTersedia($produk);

            return null;
        }

        $sudahAda = Notifikasi::query()
            ->where('tipe', Notifikasi::TIPE_STOK_PRODUK)
            ->where('id_produk', $produk->id_produk)
            ->when($harian, fn ($q) => $q->whereDate('created_at', today()), fn ($q) => $q->belumDibaca())
            ->exists();

        if ($sudahAda) {
            return null;
        }

        return Notifikasi::create([
            'tipe' => Notifikasi::TIPE_STOK_PRODUK,
            'id_produk' => $produk->id_produk,
            'judul' => "Stok habis: {$produk->nama_produk}",
            'pesan' => "Menu {$produk->nama_produk} ({$produk->kode_produk}) sudah habis. Isi ulang stok di Menu atau nonaktifkan sementara.",
            'url' => route('admin.master.produk.index'),
            'dibaca' => false,
        ]);
    }

    /** Periksa stok satu bahan baku; null bila masih ada sisa. */
    public function periksaBahan(StokBiji $bahan, bool $harian = false): ?Notifikasi
    {
        if ((float) $bahan->sisa_kg > 0) {
            $this->tandaiBahanTersedia($bahan);

            return null;
        }

        $sudahAda = Notifikasi::query()
            ->where('tipe', Notifikasi::TIPE_STOK_BAHAN)
            ->where('id_stok_biji', $bahan->id_stok_biji)
            ->when($harian, fn ($q) => $q->whereDate('created_at', today()), fn ($q) => $q->belumDibaca())
            ->exists();

        if ($sudahAda) {
            return null;
        }

        return Notifikasi::create([
            'tipe' => Notifikasi::TIPE_STOK_BAHAN,
            'id_stok_biji' => $bahan->id_stok_biji,
            'judul' => "Stok bahan habis: {$bahan->nama_biji}",
            'pesan' => "Bahan {$bahan->nama_biji} sudah habis (0 {$bahan->satuan}). Lakukan pesan ulang ke pemasok.",
            'url' => route('admin.inventory.index'),
            'dibaca' => false,
        ]);
    }

    /**
     * Sapu semua stok yang SUDAH habis (termasuk yang menjadi 0 sebelum fitur
     * notifikasi ada) dan buat notifikasi yang belum pernah dibuat hari ini.
     * Dipanggil saat admin membuka panel/lonceng, jadi tidak bergantung pada
     * perubahan stok berikutnya.
     */
    public function periksaSemua(): int
    {
        $baru = 0;

        Produk::query()
            ->where('stok', '<=', 0)
            ->orderBy('id_produk')
            ->chunk(200, function ($daftar) use (&$baru) {
                foreach ($daftar as $produk) {
                    if ($this->periksaProduk($produk, true)) {
                        $baru++;
                    }
                }
            });

        StokBiji::query()
            ->where('sisa_kg', '<=', 0)
            ->orderBy('id_stok_biji')
            ->chunk(200, function ($daftar) use (&$baru) {
                foreach ($daftar as $bahan) {
                    if ($this->periksaBahan($bahan, true)) {
                        $baru++;
                    }
                }
            });

        return $baru;
    }

    /** Stok kembali terisi: notifikasi "habis" untuk menu ini tidak relevan lagi. */
    public function tandaiProdukTersedia(Produk $produk): void
    {
        Notifikasi::query()
            ->belumDibaca()
            ->where('tipe', Notifikasi::TIPE_STOK_PRODUK)
            ->where('id_produk', $produk->id_produk)
            ->update(['dibaca' => true]);
    }

    /** Stok bahan kembali terisi: tutup notifikasi "habis" terkait. */
    public function tandaiBahanTersedia(StokBiji $bahan): void
    {
        Notifikasi::query()
            ->belumDibaca()
            ->where('tipe', Notifikasi::TIPE_STOK_BAHAN)
            ->where('id_stok_biji', $bahan->id_stok_biji)
            ->update(['dibaca' => true]);
    }

    /** Data lonceng admin: jumlah belum dibaca + daftar terbaru. */
    public function ringkasan(int $limit = 6): array
    {
        $belumDibaca = Notifikasi::query()->belumDibaca();

        return [
            'jumlah' => (clone $belumDibaca)->count(),
            'items' => (clone $belumDibaca)
                ->latest('id_notifikasi')
                ->limit($limit)
                ->get()
                ->map(fn (Notifikasi $n) => [
                    'id' => $n->id_notifikasi,
                    'judul' => $n->judul,
                    'pesan' => $n->pesan,
                    'url' => $n->url,
                    'waktu' => $n->created_at?->diffForHumans(),
                ])
                ->values()
                ->all(),
        ];
    }
}
