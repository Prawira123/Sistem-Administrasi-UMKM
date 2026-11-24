<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DetailPenjualan extends Model
{
    protected $table = 'detail_penjualan';

    protected $primaryKey = 'id_detail_penjualan';

    protected $fillable = [
        'id_penjualan',
        'id_barang',
        'jumlah',
        'harga_jual',
        'hpp_satuan',
        'subtotal',
        'subtotal_hpp',
    ];

    protected function casts(): array
    {
        return [
            'jumlah' => 'integer',
            'harga_jual' => 'decimal:2',
            'hpp_satuan' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'subtotal_hpp' => 'decimal:2',
        ];
    }

    public function penjualan(): BelongsTo
    {
        return $this->belongsTo(Penjualan::class, 'id_penjualan', 'id_penjualan');
    }

    public function barang(): BelongsTo
    {
        return $this->belongsTo(Barang::class, 'id_barang', 'id_barang');
    }
}
