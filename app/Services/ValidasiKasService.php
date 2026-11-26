<?php

namespace App\Services;

use App\Models\Penjualan;
use App\Models\User;
use App\Models\ValidasiKas;
use App\Support\Format;
use App\Support\Uang;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Proses 6.0: validasi kas harian (rancangan bagian 3 "Keuangan").
 *
 *   total_tunai_sistem = Σ penjualan.total_harga, metode tunai, status selesai, pada tanggal itu
 *   selisih            = uang_fisik − total_tunai_sistem
 *   status             = sesuai (selisih 0) / selisih (catatan wajib)
 * Bila ada selisih, dibuat kas penyesuaian lewat KasService: masuk bila uang lebih, keluar bila kurang,
 * jumlah = |selisih|, id_validasi_kas terisi. Satu validasi per tanggal; tanggal yang akan datang ditolak.
 */
class ValidasiKasService
{
    public function __construct(private readonly KasService $kas) {}

    public function totalTunaiSistem(CarbonInterface $tanggal): string
    {
        return Uang::normal((string) Penjualan::whereDate('tanggal_penjualan', $tanggal->toDateString())
            ->where('metode_pembayaran', 'tunai')
            ->where('status', 'selesai')
            ->sum('total_harga'));
    }

    public function simpan(string $tanggal, string $uangFisik, ?string $catatan, User $admin): ValidasiKas
    {
        $tanggal = Carbon::parse($tanggal)->startOfDay();

        if ($tanggal->isAfter(today())) {
            throw ValidationException::withMessages(['tanggal' => 'Validasi kas tidak bisa dibuat untuk tanggal yang akan datang.']);
        }

        try {
            return DB::transaction(function () use ($tanggal, $uangFisik, $catatan, $admin) {
                if (ValidasiKas::whereDate('tanggal', $tanggal)->lockForUpdate()->exists()) {
                    throw $this->sudahAda($tanggal);
                }

                $sistem = $this->totalTunaiSistem($tanggal);
                $fisik = Uang::normal($uangFisik);
                $selisih = Uang::kurang($fisik, $sistem);
                $arah = Uang::banding($selisih, '0');
                $catatan = trim((string) $catatan);

                if ($arah !== 0 && $catatan === '') {
                    throw ValidationException::withMessages(['catatan' => 'Catatan wajib diisi karena ada selisih kas.']);
                }

                $validasi = ValidasiKas::create([
                    'tanggal' => $tanggal->toDateString(),
                    'total_tunai_sistem' => $sistem,
                    'uang_fisik' => $fisik,
                    'selisih' => $selisih,
                    'status' => $arah === 0 ? 'sesuai' : 'selisih',
                    'catatan' => $catatan === '' ? null : mb_substr($catatan, 0, 255),
                    'id_user' => $admin->id_user,
                ]);

                if ($arah !== 0) {
                    $this->kas->catat(
                        $arah > 0 ? KasService::MASUK : KasService::KELUAR,
                        'penyesuaian',
                        ltrim($selisih, '-'), // |selisih|
                        ['id_validasi_kas' => $validasi->id_validasi_kas],
                        $admin,
                        $tanggal->copy()->setTimeFrom(now()),
                        'Selisih validasi kas '.Format::tanggal($tanggal).': '.$validasi->catatan,
                    );
                }

                return $validasi->load('kas');
            });
        } catch (QueryException $e) {
            // Dua admin menyimpan tanggal yang sama bersamaan: UNIQUE(tanggal) menolak yang kedua.
            if ($e->getCode() === '23000') {
                throw $this->sudahAda($tanggal);
            }
            throw $e;
        }
    }

    private function sudahAda(CarbonInterface $tanggal): ValidationException
    {
        return ValidationException::withMessages([
            'tanggal' => 'Kas tanggal '.Format::tanggal($tanggal).' sudah divalidasi. Validasi hanya satu kali per tanggal.',
        ]);
    }
}
