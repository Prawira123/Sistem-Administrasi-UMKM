<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ValidasiKas extends Model
{
    protected $table = 'validasi_kas';

    protected $primaryKey = 'id_validasi_kas';

    protected $fillable = [
        'tanggal',
        'total_tunai_sistem',
        'uang_fisik',
        'selisih',
        'status',
        'catatan',
        'id_user',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'total_tunai_sistem' => 'decimal:2',
            'uang_fisik' => 'decimal:2',
            'selisih' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_user', 'id_user');
    }

    public function kas(): HasOne
    {
        return $this->hasOne(Kas::class, 'id_validasi_kas', 'id_validasi_kas');
    }
}
