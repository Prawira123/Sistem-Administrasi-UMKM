<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HppPeriode extends Model
{
    protected $table = 'hpp_periode';

    protected $primaryKey = 'id_hpp';

    protected $fillable = [
        'id_barang',
        'periode_awal',
        'periode_akhir',
        'qty_persediaan_awal',
        'nilai_persediaan_awal',
        'qty_pembelian',
        'nilai_pembelian',
        'qty_persediaan_akhir',
        'nilai_persediaan_akhir',
        'total_hpp',
        'qty_terjual',
        'hpp_per_unit',
        'id_user',
    ];

    protected function casts(): array
    {
        return [
            'periode_awal' => 'date',
            'periode_akhir' => 'date',
            'qty_persediaan_awal' => 'integer',
            'nilai_persediaan_awal' => 'decimal:2',
            'qty_pembelian' => 'integer',
            'nilai_pembelian' => 'decimal:2',
            'qty_persediaan_akhir' => 'integer',
            'nilai_persediaan_akhir' => 'decimal:2',
            'total_hpp' => 'decimal:2',
            'qty_terjual' => 'integer',
            'hpp_per_unit' => 'decimal:2',
        ];
    }

    public function barang(): BelongsTo
    {
        return $this->belongsTo(Barang::class, 'id_barang', 'id_barang');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_user', 'id_user');
    }
}
