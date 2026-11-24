<?php

namespace App\Services;

use App\Models\Barang;
use App\Models\Kas;
use App\Models\Penjualan;
use App\Models\User;

/**
 * Angka ringkas untuk dashboard.
 */
class DashboardService
{
    public function ringkasan(User $user): array
    {
        $data = [
            'jumlahBarangAktif' => Barang::aktif()->count(),
            'jumlahStokMenipis' => Barang::stokMenipis()->count(),
            'barangMenipis' => Barang::stokMenipis()
                ->with('kategori')
                ->orderBy('stok')
                ->limit(5)
                ->get(),
        ];

        if ($user->isAdministrator()) {
            $data['penjualanHariIni'] = $this->penjualanHariIni();
            $data['saldoKas'] = $this->saldoKas();
        }

        return $data;
    }

    /**
     * Σ total_harga penjualan berstatus selesai pada hari ini (WITA).
     * Data penjualan baru terisi setelah modul 5.
     */
    public function penjualanHariIni(): string
    {
        return (string) Penjualan::whereDate('tanggal_penjualan', today())
            ->where('status', 'selesai')
            ->sum('total_harga');
    }

    /**
     * Saldo kas = Σ kas masuk − Σ kas keluar. Data kas baru terisi setelah modul 4, 5, dan 7.
     */
    public function saldoKas(): string
    {
        $masuk = Kas::where('jenis', 'masuk')->sum('jumlah');
        $keluar = Kas::where('jenis', 'keluar')->sum('jumlah');

        return bcsub((string) $masuk, (string) $keluar, 2);
    }
}
