<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Grup kustomisasi menu (mis. Pilihan Ukuran, Level Es, Topping).
 * Dikelola admin dan dipasang per produk lewat tabel produk_opsi_grup.
 */
class OpsiGrup extends Model
{
    protected $table = 'opsi_grup';

    protected $primaryKey = 'id_opsi_grup';

    protected $fillable = [
        'kode',
        'nama',
        'tipe',
        'wajib',
        'urutan',
        'aktif',
    ];

    protected function casts(): array
    {
        return [
            'wajib' => 'boolean',
            'aktif' => 'boolean',
            'urutan' => 'integer',
        ];
    }

    /** @param Builder<OpsiGrup> $query */
    public function scopeAktif(Builder $query): void
    {
        $query->where('aktif', true);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OpsiItem::class, 'id_opsi_grup', 'id_opsi_grup')
            ->orderBy('urutan')
            ->orderBy('id_opsi_item');
    }

    public function produk(): BelongsToMany
    {
        return $this->belongsToMany(
            Produk::class,
            'produk_opsi_grup',
            'id_opsi_grup',
            'id_produk',
            'id_opsi_grup',
            'id_produk',
        );
    }
}
