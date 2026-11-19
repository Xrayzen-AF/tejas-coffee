<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Notifikasi internal admin (lonceng di panel pengelola).
 * Notifikasi dibuat otomatis oleh NotifikasiStok saat stok menu/bahan habis.
 */
class Notifikasi extends Model
{
    public const TIPE_STOK_PRODUK = 'stok_produk_habis';

    public const TIPE_STOK_BAHAN = 'stok_bahan_habis';

    protected $table = 'notifikasi';

    protected $primaryKey = 'id_notifikasi';

    protected $fillable = [
        'tipe',
        'id_produk',
        'id_stok_biji',
        'judul',
        'pesan',
        'url',
        'dibaca',
    ];

    protected function casts(): array
    {
        return [
            'dibaca' => 'boolean',
        ];
    }

    /** @param Builder<Notifikasi> $query */
    public function scopeBelumDibaca(Builder $query): void
    {
        $query->where('dibaca', false);
    }

    public function produk(): BelongsTo
    {
        return $this->belongsTo(Produk::class, 'id_produk', 'id_produk');
    }

    public function stokBiji(): BelongsTo
    {
        return $this->belongsTo(StokBiji::class, 'id_stok_biji', 'id_stok_biji');
    }

    public function tandaiDibaca(): void
    {
        if (! $this->dibaca) {
            $this->update(['dibaca' => true]);
        }
    }
}
