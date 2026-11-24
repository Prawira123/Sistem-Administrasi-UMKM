<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotifikasiStok extends Model
{
    protected $table = 'notifikasi_stok';

    protected $primaryKey = 'id_notifikasi';

    protected $fillable = [
        'id_barang',
        'stok_saat_ini',
        'stok_minimum',
        'pesan',
        'status',
        'dibaca_oleh',
        'dibaca_pada',
    ];

    protected function casts(): array
    {
        return [
            'stok_saat_ini' => 'integer',
            'stok_minimum' => 'integer',
            'dibaca_pada' => 'datetime',
        ];
    }

    public function barang(): BelongsTo
    {
        return $this->belongsTo(Barang::class, 'id_barang', 'id_barang');
    }

    public function pembaca(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibaca_oleh', 'id_user');
    }
}
