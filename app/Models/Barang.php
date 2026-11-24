<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Barang extends Model
{
    protected $table = 'barang';

    protected $primaryKey = 'id_barang';

    protected $fillable = [
        'kode_barang',
        'id_kategori',
        'nama_barang',
        'satuan',
        'harga_beli_terakhir',
        'hpp_rata_rata',
        'harga_jual',
        'stok',
        'stok_minimum',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'harga_beli_terakhir' => 'decimal:2',
            'hpp_rata_rata' => 'decimal:2',
            'harga_jual' => 'decimal:2',
            'stok' => 'integer',
            'stok_minimum' => 'integer',
        ];
    }

    public function kategori(): BelongsTo
    {
        return $this->belongsTo(KategoriBarang::class, 'id_kategori', 'id_kategori');
    }

    public function detailBarangMasuk(): HasMany
    {
        return $this->hasMany(DetailBarangMasuk::class, 'id_barang', 'id_barang');
    }

    public function detailPenjualan(): HasMany
    {
        return $this->hasMany(DetailPenjualan::class, 'id_barang', 'id_barang');
    }

    public function detailStokOpname(): HasMany
    {
        return $this->hasMany(DetailStokOpname::class, 'id_barang', 'id_barang');
    }

    public function kartuStok(): HasMany
    {
        return $this->hasMany(KartuStok::class, 'id_barang', 'id_barang');
    }

    public function notifikasiStok(): HasMany
    {
        return $this->hasMany(NotifikasiStok::class, 'id_barang', 'id_barang');
    }

    public function hppPeriode(): HasMany
    {
        return $this->hasMany(HppPeriode::class, 'id_barang', 'id_barang');
    }
}
