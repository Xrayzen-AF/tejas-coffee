<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu pilihan di dalam grup kustomisasi (mis. Oat Milk +Rp 8.000).
 */
class OpsiItem extends Model
{
    protected $table = 'opsi_item';

    protected $primaryKey = 'id_opsi_item';

    protected $fillable = [
        'id_opsi_grup',
        'kode',
        'label',
        'deskripsi',
        'delta_harga',
        'is_default',
        'urutan',
        'aktif',
    ];

    protected function casts(): array
    {
        return [
            'delta_harga' => 'decimal:2',
            'is_default' => 'boolean',
            'aktif' => 'boolean',
            'urutan' => 'integer',
        ];
    }

    /** @param Builder<OpsiItem> $query */
    public function scopeAktif(Builder $query): void
    {
        $query->where('aktif', true);
    }

    public function grup(): BelongsTo
    {
        return $this->belongsTo(OpsiGrup::class, 'id_opsi_grup', 'id_opsi_grup');
    }
}
