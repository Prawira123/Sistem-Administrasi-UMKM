<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DetailBarangMasuk extends Model
{
    protected $table = 'detail_barang_masuk';

    protected $primaryKey = 'id_detail_masuk';

    protected $fillable = [
        'id_barang_masuk',
        'id_barang',
        'jumlah',
        'harga_beli',
        'subtotal',
        'tanggal_kadaluarsa',
    ];

    protected function casts(): array
    {
        return [
            'jumlah' => 'integer',
            'harga_beli' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'tanggal_kadaluarsa' => 'date',
        ];
    }

    public function barangMasuk(): BelongsTo
    {
        return $this->belongsTo(BarangMasuk::class, 'id_barang_masuk', 'id_barang_masuk');
    }

    public function barang(): BelongsTo
    {
        return $this->belongsTo(Barang::class, 'id_barang', 'id_barang');
    }
}
