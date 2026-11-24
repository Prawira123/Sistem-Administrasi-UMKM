<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PengeluaranOperasional extends Model
{
    protected $table = 'pengeluaran_operasional';

    protected $primaryKey = 'id_pengeluaran';

    protected $fillable = [
        'kode_pengeluaran',
        'id_kategori_pengeluaran',
        'id_user',
        'tanggal_pengeluaran',
        'deskripsi',
        'jumlah',
        'metode_pembayaran',
        'bukti',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_pengeluaran' => 'date',
            'jumlah' => 'decimal:2',
        ];
    }

    public function kategori(): BelongsTo
    {
        return $this->belongsTo(
            KategoriPengeluaran::class,
            'id_kategori_pengeluaran',
            'id_kategori_pengeluaran'
        );
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_user', 'id_user');
    }

    public function kas(): HasOne
    {
        return $this->hasOne(Kas::class, 'id_pengeluaran', 'id_pengeluaran');
    }
}
