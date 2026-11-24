<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StokOpname extends Model
{
    protected $table = 'stok_opname';

    protected $primaryKey = 'id_stok_opname';

    protected $fillable = [
        'kode_opname',
        'id_user',
        'tanggal_opname',
        'status',
        'disetujui_oleh',
        'disetujui_pada',
        'keterangan',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_opname' => 'date',
            'disetujui_pada' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_user', 'id_user');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disetujui_oleh', 'id_user');
    }

    public function detail(): HasMany
    {
        return $this->hasMany(DetailStokOpname::class, 'id_stok_opname', 'id_stok_opname');
    }

    public function kartuStok(): HasMany
    {
        return $this->hasMany(KartuStok::class, 'id_stok_opname', 'id_stok_opname');
    }
}
