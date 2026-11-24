<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DetailStokOpname extends Model
{
    protected $table = 'detail_stok_opname';

    protected $primaryKey = 'id_detail_opname';

    protected $fillable = [
        'id_stok_opname',
        'id_barang',
        'stok_sistem',
        'stok_fisik',
        'selisih',
        'keterangan',
    ];

    protected function casts(): array
    {
        return [
            'stok_sistem' => 'integer',
            'stok_fisik' => 'integer',
            'selisih' => 'integer',
        ];
    }

    public function stokOpname(): BelongsTo
    {
        return $this->belongsTo(StokOpname::class, 'id_stok_opname', 'id_stok_opname');
    }

    public function barang(): BelongsTo
    {
        return $this->belongsTo(Barang::class, 'id_barang', 'id_barang');
    }
}
