<?php

namespace App\Support;

use Carbon\CarbonInterface;
use DateTimeInterface;
use Illuminate\Support\Carbon;

/**
 * Helper format tampilan: Rupiah dan tanggal (zona waktu Asia/Makassar).
 */
class Format
{
    public const ZONA_WAKTU = 'Asia/Makassar';

    /**
     * 15000 → "Rp15.000"; -2500 → "-Rp2.500".
     */
    public static function rupiah(int|float|string|null $nilai, int $desimal = 0): string
    {
        $angka = (float) ($nilai ?? 0);
        $hasil = 'Rp'.number_format(abs($angka), $desimal, ',', '.');

        return $angka < 0 ? '-'.$hasil : $hasil;
    }

    /**
     * Tanggal → "24/11/2026".
     */
    public static function tanggal(DateTimeInterface|string|null $tanggal, string $format = 'd/m/Y'): string
    {
        if ($tanggal === null || $tanggal === '') {
            return '-';
        }

        return self::keCarbon($tanggal)->format($format);
    }

    /**
     * Tanggal dan jam → "24/11/2026 14:30".
     */
    public static function tanggalWaktu(DateTimeInterface|string|null $tanggal): string
    {
        return self::tanggal($tanggal, 'd/m/Y H:i');
    }

    private static function keCarbon(DateTimeInterface|string $tanggal): CarbonInterface
    {
        $carbon = $tanggal instanceof DateTimeInterface
            ? Carbon::instance($tanggal)
            : Carbon::parse($tanggal, self::ZONA_WAKTU);

        return $carbon->setTimezone(self::ZONA_WAKTU);
    }
}
