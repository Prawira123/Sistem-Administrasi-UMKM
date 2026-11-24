<?php

namespace App\Support;

/**
 * Aritmetika uang memakai bcmath (angka sebagai string desimal), BUKAN float.
 *
 * Alasan: float tidak bisa menyimpan pecahan desimal dengan tepat (0.1 + 0.2 = 0.30000000000000004),
 * sehingga selisih kecil bisa menumpuk di HPP dan laporan. bcmath menghitung digit per digit
 * dengan skala tetap, cocok dengan kolom DECIMAL(15,2) di MySQL dan cast 'decimal:2' di model.
 */
class Uang
{
    public const SKALA = 2;

    /** Skala perantara agar pembulatan akhir ke 2 desimal tetap akurat. */
    private const SKALA_ANTARA = 6;

    /** Normalisasi input (int/string) ke string 2 desimal, mis. "3600" → "3600.00". */
    public static function normal(int|string|null $nilai): string
    {
        return self::bulat((string) ($nilai ?? '0'));
    }

    public static function tambah(string $a, string $b): string
    {
        return bcadd($a, $b, self::SKALA);
    }

    public static function kurang(string $a, string $b): string
    {
        return bcsub($a, $b, self::SKALA);
    }

    public static function kali(int|string $a, int|string $b): string
    {
        return self::bulat(bcmul((string) $a, (string) $b, self::SKALA_ANTARA));
    }

    public static function bagi(int|string $a, int|string $b): string
    {
        return self::bulat(bcdiv((string) $a, (string) $b, self::SKALA_ANTARA));
    }

    /** -1 bila a < b, 0 bila sama, 1 bila a > b. */
    public static function banding(string $a, string $b): int
    {
        return bccomp($a, $b, self::SKALA);
    }

    /**
     * Pembulatan setengah ke atas (menjauhi nol) ke 2 desimal: 3400.005 → 3400.01.
     * bcmath sendiri hanya memotong, jadi ditambah/dikurangi 0.005 sebelum dipotong.
     */
    public static function bulat(string $nilai): string
    {
        $setengah = '0.'.str_repeat('0', self::SKALA).'5';

        return bccomp($nilai, '0', self::SKALA_ANTARA) >= 0
            ? bcadd($nilai, $setengah, self::SKALA)
            : bcsub($nilai, $setengah, self::SKALA);
    }
}
