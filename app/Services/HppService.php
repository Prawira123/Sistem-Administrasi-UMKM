<?php

namespace App\Services;

use App\Models\Barang;
use App\Models\HppPeriode;
use App\Models\KartuStok;
use App\Models\User;
use App\Support\Uang;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Proses 6.0: HPP periodik per barang per bulan (rancangan bagian 3 "Keuangan", Bab 2.6):
 *
 *   total_hpp = nilai_persediaan_awal + nilai_pembelian − nilai_persediaan_akhir
 *
 *   - Persediaan awal  = stok_sesudah × hpp_satuan mutasi kartu stok terakhir SEBELUM tanggal 1 (0 bila belum ada).
 *   - Pembelian        = Σ detail_barang_masuk (jumlah, subtotal) dengan tanggal_masuk di bulan itu.
 *   - Persediaan akhir = stok_sesudah × hpp_satuan mutasi terakhir SAMPAI akhir bulan.
 *   - qty_terjual      = Σ detail_penjualan berstatus selesai di bulan itu; hpp_per_unit = total_hpp ÷ qty_terjual.
 *
 * Contoh: awal 10 × 3.000 = 30.000; beli 20 = 72.000; akhir 12 × 3.400 = 40.800 → HPP 61.200; terjual 18 → 3.400/unit.
 * Selisih stok opname (rusak/hilang) ikut masuk HPP karena mengurangi persediaan akhir tanpa penjualan.
 */
class HppService
{
    /**
     * Hitung dan simpan HPP satu bulan untuk semua barang yang punya persediaan/pembelian/penjualan.
     * Bila bulan itu sudah pernah dihitung, $hitungUlang harus true (konfirmasi dari Admin).
     *
     * @return Collection<int, HppPeriode>
     */
    public function hitungBulan(int $tahun, int $bulan, User $admin, bool $hitungUlang = false): Collection
    {
        [$awal, $akhir] = $this->periode($tahun, $bulan);

        if ($awal->isAfter(today())) {
            throw ValidationException::withMessages(['periode' => 'HPP tidak bisa dihitung untuk bulan yang akan datang.']);
        }

        return DB::transaction(function () use ($awal, $akhir, $admin, $hitungUlang) {
            $lama = HppPeriode::whereDate('periode_awal', $awal)->whereDate('periode_akhir', $akhir)->lockForUpdate();

            if ($lama->exists() && ! $hitungUlang) {
                throw ValidationException::withMessages([
                    'periode' => 'HPP bulan ini sudah pernah dihitung. Centang konfirmasi untuk menghitung ulang.',
                ]);
            }

            $pembelian = $this->pembelian($awal, $akhir);
            $terjual = $this->terjual($awal, $akhir);
            $hasil = collect();

            foreach (Barang::orderBy('nama_barang')->get() as $barang) {
                [$qtyAwal, $nilaiAwal] = $this->persediaan($barang->id_barang, $awal, '<');
                [$qtyAkhir, $nilaiAkhir] = $this->persediaan($barang->id_barang, $akhir->copy()->endOfDay(), '<=');
                $qtyBeli = (int) ($pembelian[$barang->id_barang]->qty ?? 0);
                $nilaiBeli = Uang::normal($pembelian[$barang->id_barang]->nilai ?? '0');
                $qtyJual = (int) ($terjual[$barang->id_barang] ?? 0);

                if ($qtyAwal === 0 && $qtyAkhir === 0 && $qtyBeli === 0 && $qtyJual === 0) {
                    continue; // barang tidak bergerak dan tidak punya persediaan pada bulan ini
                }

                $totalHpp = Uang::kurang(Uang::tambah($nilaiAwal, $nilaiBeli), $nilaiAkhir);

                $hasil->push(HppPeriode::updateOrCreate(
                    ['id_barang' => $barang->id_barang, 'periode_awal' => $awal->toDateString(), 'periode_akhir' => $akhir->toDateString()],
                    [
                        'qty_persediaan_awal' => $qtyAwal,
                        'nilai_persediaan_awal' => $nilaiAwal,
                        'qty_pembelian' => $qtyBeli,
                        'nilai_pembelian' => $nilaiBeli,
                        'qty_persediaan_akhir' => $qtyAkhir,
                        'nilai_persediaan_akhir' => $nilaiAkhir,
                        'total_hpp' => $totalHpp,
                        'qty_terjual' => $qtyJual,
                        'hpp_per_unit' => $qtyJual > 0 ? Uang::bagi($totalHpp, $qtyJual) : '0.00',
                        'id_user' => $admin->id_user,
                    ],
                ));
            }

            // Hitung ulang: buang baris barang yang kini tidak lagi punya data di periode ini.
            HppPeriode::whereDate('periode_awal', $awal)->whereDate('periode_akhir', $akhir)
                ->whereNotIn('id_hpp', $hasil->pluck('id_hpp'))
                ->delete();

            return $hasil;
        });
    }

    /** @return array{0: Carbon, 1: Carbon} tanggal 1 dan tanggal terakhir bulan */
    public function periode(int $tahun, int $bulan): array
    {
        $awal = Carbon::create($tahun, $bulan, 1)->startOfDay();

        return [$awal, $awal->copy()->endOfMonth()->startOfDay()];
    }

    /**
     * Persediaan dari mutasi kartu stok terakhir sebelum/sampai suatu waktu.
     *
     * @return array{0: int, 1: string} [qty, nilai]
     */
    private function persediaan(int $idBarang, CarbonInterface $batas, string $operator): array
    {
        $mutasi = KartuStok::where('id_barang', $idBarang)
            ->where('tanggal', $operator, $batas)
            ->orderByDesc('tanggal')
            ->orderByDesc('id_kartu_stok')
            ->first(['stok_sesudah', 'hpp_satuan']);

        if (! $mutasi) {
            return [0, '0.00'];
        }

        return [$mutasi->stok_sesudah, Uang::kali($mutasi->stok_sesudah, $mutasi->hpp_satuan)];
    }

    /** @return Collection<int, object{qty: string, nilai: string}> key = id_barang */
    private function pembelian(CarbonInterface $awal, CarbonInterface $akhir): Collection
    {
        return DB::table('detail_barang_masuk as d')
            ->join('barang_masuk as bm', 'bm.id_barang_masuk', '=', 'd.id_barang_masuk')
            ->whereBetween('bm.tanggal_masuk', [$awal->toDateString(), $akhir->toDateString()])
            ->groupBy('d.id_barang')
            ->select('d.id_barang', DB::raw('SUM(d.jumlah) AS qty'), DB::raw('SUM(d.subtotal) AS nilai'))
            ->get()
            ->keyBy('id_barang');
    }

    /** @return Collection<int, string> id_barang => qty terjual (status selesai) */
    private function terjual(CarbonInterface $awal, CarbonInterface $akhir): Collection
    {
        return DB::table('detail_penjualan as d')
            ->join('penjualan as p', 'p.id_penjualan', '=', 'd.id_penjualan')
            ->where('p.status', 'selesai')
            ->whereBetween('p.tanggal_penjualan', [$awal->copy()->startOfDay(), $akhir->copy()->endOfDay()])
            ->groupBy('d.id_barang')
            ->select('d.id_barang', DB::raw('SUM(d.jumlah) AS qty'))
            ->pluck('qty', 'id_barang');
    }
}
