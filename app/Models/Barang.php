<?php

namespace App\Models;

use Database\Factories\BarangFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Barang extends Model
{
    /** @use HasFactory<BarangFactory> */
    use HasFactory;

    protected $table = 'barang';

    protected $primaryKey = 'id_barang';

    /**
     * stok, hpp_rata_rata, dan harga_beli_terakhir sengaja TIDAK fillable:
     * kolom itu hanya diisi service transaksi (barang masuk, penjualan, pembatalan, opname final),
     * misalnya $barang->stok = $baru; $barang->save();
     */
    protected $fillable = [
        'kode_barang',
        'id_kategori',
        'nama_barang',
        'satuan',
        'harga_jual',
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

    /**
     * Status stok untuk badge: habis (0), menipis (<= stok minimum), aman.
     */
    public function statusStok(): string
    {
        if ($this->stok <= 0) {
            return 'habis';
        }

        return $this->stok <= $this->stok_minimum ? 'menipis' : 'aman';
    }

    public function isStokMenipis(): bool
    {
        return $this->stok <= $this->stok_minimum;
    }

    /**
     * Data ringkas untuk halaman kasir (endpoint barang.cari dan isi keranjang). Tanpa HPP.
     */
    public function dataKasir(): array
    {
        return [
            'id' => $this->id_barang,
            'kode' => $this->kode_barang,
            'nama' => $this->nama_barang,
            'satuan' => $this->satuan,
            'stok' => $this->stok,
            'harga_jual' => $this->harga_jual,
        ];
    }

    public function scopeAktif(Builder $query): Builder
    {
        return $query->where('status', 'aktif');
    }

    /**
     * Peringatan stok minimum (rancangan bagian 4): barang aktif dengan stok <= stok_minimum.
     */
    public function scopeStokMenipis(Builder $query): Builder
    {
        return $query->aktif()->whereColumn('stok', '<=', 'stok_minimum');
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
