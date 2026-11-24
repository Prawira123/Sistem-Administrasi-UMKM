<?php

namespace App\Models;

use Database\Factories\SupplierFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    /** @use HasFactory<SupplierFactory> */
    use HasFactory;

    protected $table = 'supplier';

    protected $primaryKey = 'id_supplier';

    protected $fillable = [
        'kode_supplier',
        'nama_supplier',
        'nama_kontak',
        'no_telepon',
        'alamat',
        'status',
    ];

    public function barangMasuk(): HasMany
    {
        return $this->hasMany(BarangMasuk::class, 'id_supplier', 'id_supplier');
    }
}
