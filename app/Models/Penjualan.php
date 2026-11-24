<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Penjualan extends Model
{
    protected $table = 'penjualan';

    protected $primaryKey = 'id_penjualan';

    protected $fillable = [
        'no_transaksi',
        'id_user',
        'tanggal_penjualan',
        'total_item',
        'total_harga',
        'total_hpp',
        'metode_pembayaran',
        'jumlah_bayar',
        'kembalian',
        'status',
        'keterangan',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_penjualan' => 'datetime',
            'total_item' => 'integer',
            'total_harga' => 'decimal:2',
            'total_hpp' => 'decimal:2',
            'jumlah_bayar' => 'decimal:2',
            'kembalian' => 'decimal:2',
        ];
    }

    /**
     * Pembatalan hanya untuk transaksi selesai di bulan berjalan: laporan dan HPP periode
     * sebelumnya sudah berjalan, sehingga tidak boleh berubah karena pembatalan.
     */
    public function isBulanBerjalan(): bool
    {
        return $this->tanggal_penjualan->isSameMonth(now());
    }

    public function bisaDibatalkan(): bool
    {
        return $this->status === 'selesai' && $this->isBulanBerjalan();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_user', 'id_user');
    }

    public function detail(): HasMany
    {
        return $this->hasMany(DetailPenjualan::class, 'id_penjualan', 'id_penjualan');
    }

    public function kartuStok(): HasMany
    {
        return $this->hasMany(KartuStok::class, 'id_penjualan', 'id_penjualan');
    }

    public function kas(): HasOne
    {
        return $this->hasOne(Kas::class, 'id_penjualan', 'id_penjualan');
    }
}
