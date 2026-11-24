<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KategoriPengeluaran extends Model
{
    protected $table = 'kategori_pengeluaran';

    protected $primaryKey = 'id_kategori_pengeluaran';

    protected $fillable = [
        'nama_kategori',
        'keterangan',
    ];

    public function pengeluaran(): HasMany
    {
        return $this->hasMany(
            PengeluaranOperasional::class,
            'id_kategori_pengeluaran',
            'id_kategori_pengeluaran'
        );
    }
}
