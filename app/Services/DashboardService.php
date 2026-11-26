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
    public function __construct(private readonly LaporanService $laporan) {}

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
            $data['labaBulanIni'] = $this->labaBulanIni();
            $data['terlaris'] = $this->laporan->barangTerlaris(today()->subDays(29), now(), 5);
            $data['grafik'] = [
                '7' => $this->laporan->grafikPenjualan(7),
                '30' => $this->laporan->grafikPenjualan(30),
            ];
        }

        return $data;
    }

    /**
     * Laba bulan berjalan untuk kartu dashboard: laba bersih resmi (hpp_periode) bila HPP sudah dihitung,
     * selain itu estimasi dari HPP rata-rata yang tersimpan di penjualan.
     *
     * @return array{nilai: string, estimasi: bool}
     */
    public function labaBulanIni(): array
    {
        $resmi = $this->laporan->labaRugi(now()->year, now()->month);

        if ($resmi['hpp_dihitung']) {
            return ['nilai' => $resmi['laba_bersih'], 'estimasi' => false];
        }

        return ['nilai' => $this->laporan->estimasiLabaBulan(now()->year, now()->month)['laba_bersih'], 'estimasi' => true];
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
