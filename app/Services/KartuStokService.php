<?php

namespace App\Services;

use App\Models\Barang;
use App\Models\KartuStok;
use App\Models\User;
use InvalidArgumentException;
use LogicException;

/**
 * Satu-satunya tempat menulis tabel kartu_stok.
 *
 * Cara pakai (di dalam DB::transaction yang sama dengan perubahan stok):
 *   1. ubah lalu simpan stok barang: $barang->stok += $jumlah; $barang->save();
 *   2. panggil catat(): stok_sesudah = $barang->stok, stok_sebelum dihitung mundur dari jumlah masuk/keluar,
 *      hpp_satuan = $barang->hpp_rata_rata saat ini.
 *
 * Validasi referensi (rancangan bagian 3, dilakukan di aplikasi karena bukan CHECK MySQL):
 *   masuk       ⇒ id_barang_masuk wajib
 *   keluar      ⇒ id_penjualan wajib
 *   penyesuaian ⇒ id_penjualan (pembatalan) ATAU id_stok_opname (opname final)
 */
class KartuStokService
{
    public const MASUK = 'masuk';

    public const KELUAR = 'keluar';

    public const PENYESUAIAN = 'penyesuaian';

    private const KOLOM_SUMBER = ['id_barang_masuk', 'id_penjualan', 'id_stok_opname'];

    /**
     * @param  array{id_barang_masuk?: int, id_penjualan?: int, id_stok_opname?: int}  $sumber
     */
    public function catat(
        Barang $barang,
        string $jenis,
        int $masuk,
        int $keluar,
        array $sumber,
        string $keterangan,
        User $user,
    ): KartuStok {
        $sumber = $this->validasiSumber($jenis, $sumber);
        $this->validasiJumlah($jenis, $masuk, $keluar);

        if (! $barang->exists || $barang->isDirty('stok')) {
            throw new LogicException('Simpan perubahan stok barang terlebih dahulu sebelum menulis kartu stok.');
        }

        $stokSesudah = $barang->stok;
        $stokSebelum = $stokSesudah - $masuk + $keluar;

        if ($stokSebelum < 0) {
            throw new InvalidArgumentException('Stok sebelum mutasi tidak boleh negatif; periksa urutan pemanggilan.');
        }

        return KartuStok::create($sumber + [
            'id_barang' => $barang->id_barang,
            'tanggal' => now(),
            'jenis_mutasi' => $jenis,
            'jumlah_masuk' => $masuk,
            'jumlah_keluar' => $keluar,
            'stok_sebelum' => $stokSebelum,
            'stok_sesudah' => $stokSesudah,
            'hpp_satuan' => $barang->hpp_rata_rata,
            'keterangan' => mb_substr($keterangan, 0, 255),
            'id_user' => $user->id_user,
        ]);
    }

    /**
     * Kembalikan semua kolom sumber (yang tidak dipakai bernilai null) atau lempar exception.
     */
    private function validasiSumber(string $jenis, array $sumber): array
    {
        $tidakDikenal = array_diff(array_keys($sumber), self::KOLOM_SUMBER);
        if ($tidakDikenal) {
            throw new InvalidArgumentException('Sumber kartu stok tidak dikenal: '.implode(', ', $tidakDikenal).'.');
        }

        $terisi = array_keys(array_filter($sumber, fn ($id) => $id !== null));

        $boleh = match ($jenis) {
            self::MASUK => [['id_barang_masuk']],
            self::KELUAR => [['id_penjualan']],
            self::PENYESUAIAN => [['id_penjualan'], ['id_stok_opname']],
            default => throw new InvalidArgumentException("Jenis mutasi '{$jenis}' tidak valid."),
        };

        if (! in_array($terisi, $boleh, true)) {
            $harus = implode(' atau ', array_map(fn ($k) => $k[0], $boleh));
            throw new InvalidArgumentException("Kartu stok jenis '{$jenis}' harus merujuk tepat satu sumber: {$harus}.");
        }

        // $sumber di kiri: operator + mempertahankan nilai sisi kiri, kolom lain diisi null.
        return $sumber + array_fill_keys(self::KOLOM_SUMBER, null);
    }

    private function validasiJumlah(string $jenis, int $masuk, int $keluar): void
    {
        $valid = match ($jenis) {
            self::MASUK => $masuk > 0 && $keluar === 0,
            self::KELUAR => $keluar > 0 && $masuk === 0,
            self::PENYESUAIAN => $masuk >= 0 && $keluar >= 0 && ($masuk > 0) !== ($keluar > 0),
        };

        if (! $valid) {
            throw new InvalidArgumentException("Jumlah masuk/keluar tidak sesuai untuk mutasi '{$jenis}'.");
        }
    }
}
