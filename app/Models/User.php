<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_ADMINISTRATOR = 'administrator';

    public const ROLE_KASIR = 'kasir';

    public const STATUS_AKTIF = 'aktif';

    public const STATUS_NONAKTIF = 'nonaktif';

    protected $table = 'users';

    protected $primaryKey = 'id_user';

    protected $fillable = [
        'nama',
        'username',
        'email',
        'password',
        'role',
        'status',
        'terakhir_login',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'terakhir_login' => 'datetime',
        ];
    }

    public function isAdministrator(): bool
    {
        return $this->role === self::ROLE_ADMINISTRATOR;
    }

    public function isKasir(): bool
    {
        return $this->role === self::ROLE_KASIR;
    }

    public function isAktif(): bool
    {
        return $this->status === self::STATUS_AKTIF;
    }

    /**
     * Label role untuk tampilan (header, tabel pengguna).
     */
    public function labelRole(): string
    {
        return $this->isAdministrator() ? 'Administrator' : 'Kasir';
    }

    public function barangMasuk(): HasMany
    {
        return $this->hasMany(BarangMasuk::class, 'id_user', 'id_user');
    }

    public function penjualan(): HasMany
    {
        return $this->hasMany(Penjualan::class, 'id_user', 'id_user');
    }

    public function stokOpname(): HasMany
    {
        return $this->hasMany(StokOpname::class, 'id_user', 'id_user');
    }

    public function stokOpnameDisetujui(): HasMany
    {
        return $this->hasMany(StokOpname::class, 'disetujui_oleh', 'id_user');
    }

    public function kartuStok(): HasMany
    {
        return $this->hasMany(KartuStok::class, 'id_user', 'id_user');
    }

    public function pengeluaranOperasional(): HasMany
    {
        return $this->hasMany(PengeluaranOperasional::class, 'id_user', 'id_user');
    }

    public function validasiKas(): HasMany
    {
        return $this->hasMany(ValidasiKas::class, 'id_user', 'id_user');
    }

    public function kas(): HasMany
    {
        return $this->hasMany(Kas::class, 'id_user', 'id_user');
    }

    public function hppPeriode(): HasMany
    {
        return $this->hasMany(HppPeriode::class, 'id_user', 'id_user');
    }

    public function riwayatAi(): HasMany
    {
        return $this->hasMany(RiwayatAi::class, 'id_user', 'id_user');
    }
}
