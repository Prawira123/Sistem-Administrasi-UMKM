<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KartuStok extends Model
{
    protected $table = 'kartu_stok';

    protected $primaryKey = 'id_kartu_stok';

    protected $fillable = [
        'id_barang',
        'tanggal',
        'jenis_mutasi',
        'id_barang_masuk',
        'id_penjualan',
        'id_stok_opname',
        'jumlah_masuk',
        'jumlah_keluar',
        'stok_sebelum',
        'stok_sesudah',
        'hpp_satuan',
        'keterangan',
        'id_user',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'datetime',
            'jumlah_masuk' => 'integer',
            'jumlah_keluar' => 'integer',
            'stok_sebelum' => 'integer',
            'stok_sesudah' => 'integer',
            'hpp_satuan' => 'decimal:2',
        ];
    }

    public function barang(): BelongsTo
    {
        return $this->belongsTo(Barang::class, 'id_barang', 'id_barang');
    }

    public function barangMasuk(): BelongsTo
    {
        return $this->belongsTo(BarangMasuk::class, 'id_barang_masuk', 'id_barang_masuk');
    }

    public function penjualan(): BelongsTo
    {
        return $this->belongsTo(Penjualan::class, 'id_penjualan', 'id_penjualan');
    }

    public function stokOpname(): BelongsTo
    {
        return $this->belongsTo(StokOpname::class, 'id_stok_opname', 'id_stok_opname');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_user', 'id_user');
    }
}
