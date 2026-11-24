<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class BarangMasuk extends Model
{
    protected $table = 'barang_masuk';

    protected $primaryKey = 'id_barang_masuk';

    protected $fillable = [
        'kode_barang_masuk',
        'no_faktur',
        'id_supplier',
        'id_user',
        'tanggal_masuk',
        'total_pembelian',
        'metode_pembayaran',
        'keterangan',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_masuk' => 'date',
            'total_pembelian' => 'decimal:2',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'id_supplier', 'id_supplier');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_user', 'id_user');
    }

    public function detail(): HasMany
    {
        return $this->hasMany(DetailBarangMasuk::class, 'id_barang_masuk', 'id_barang_masuk');
    }

    public function kartuStok(): HasMany
    {
        return $this->hasMany(KartuStok::class, 'id_barang_masuk', 'id_barang_masuk');
    }

    public function kas(): HasOne
    {
        return $this->hasOne(Kas::class, 'id_barang_masuk', 'id_barang_masuk');
    }
}
