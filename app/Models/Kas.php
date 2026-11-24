<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Kas extends Model
{
    protected $table = 'kas';

    protected $primaryKey = 'id_kas';

    protected $fillable = [
        'tanggal',
        'jenis',
        'kategori',
        'id_penjualan',
        'id_barang_masuk',
        'id_pengeluaran',
        'id_validasi_kas',
        'jumlah',
        'keterangan',
        'id_user',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'datetime',
            'jumlah' => 'decimal:2',
        ];
    }

    public function penjualan(): BelongsTo
    {
        return $this->belongsTo(Penjualan::class, 'id_penjualan', 'id_penjualan');
    }

    public function barangMasuk(): BelongsTo
    {
        return $this->belongsTo(BarangMasuk::class, 'id_barang_masuk', 'id_barang_masuk');
    }

    public function pengeluaran(): BelongsTo
    {
        return $this->belongsTo(PengeluaranOperasional::class, 'id_pengeluaran', 'id_pengeluaran');
    }

    public function validasiKas(): BelongsTo
    {
        return $this->belongsTo(ValidasiKas::class, 'id_validasi_kas', 'id_validasi_kas');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_user', 'id_user');
    }
}
