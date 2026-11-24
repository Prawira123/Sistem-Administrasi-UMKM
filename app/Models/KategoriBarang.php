<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KategoriBarang extends Model
{
    protected $table = 'kategori_barang';

    protected $primaryKey = 'id_kategori';

    protected $fillable = [
        'nama_kategori',
        'keterangan',
    ];

    public function barang(): HasMany
    {
        return $this->hasMany(Barang::class, 'id_kategori', 'id_kategori');
    }
}
