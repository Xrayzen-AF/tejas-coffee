<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Model tabel `produk` sesuai ERD (PK: id_produk).
 */
class Produk extends Model
{
    protected $table = 'produk';

    protected $primaryKey = 'id_produk';

    public $timestamps = false;

    protected $fillable = [
        'id_kategori',
        'kode_produk',
        'nama_produk',
        'deskripsi',
        'harga',
        'stok',
        'status_tersedia',
        'url_gambar',
    ];

    protected function casts(): array
    {
        return [
            'harga' => 'decimal:2',
            'stok' => 'integer',
            'status_tersedia' => 'boolean',
        ];
    }

    /**
     * Status jual sebenarnya: produk hanya tersedia bila flag aktif DAN stok > 0.
     * Stok 0 otomatis terbaca "Habis" tanpa menghapus produk dari daftar menu,
     * sehingga item tetap tampil (dengan label Habis) alih-alih hilang.
     */
    protected function tersedia(): Attribute
    {
        return Attribute::get(
            fn (): bool => (bool) $this->status_tersedia && (int) $this->stok > 0,
        );
    }

    public function kategori(): BelongsTo
    {
        return $this->belongsTo(Kategori::class, 'id_kategori', 'id_kategori');
    }

    public function detailPesanan(): HasMany
    {
        return $this->hasMany(DetailPesanan::class, 'id_produk', 'id_produk');
    }

    /** Grup kustomisasi yang berlaku untuk produk ini (diatur admin). */
    public function opsiGrup(): BelongsToMany
    {
        return $this->belongsToMany(
            OpsiGrup::class,
            'produk_opsi_grup',
            'id_produk',
            'id_opsi_grup',
            'id_produk',
            'id_opsi_grup',
        );
    }
}
