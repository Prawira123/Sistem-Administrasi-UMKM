<?php

namespace App\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Penomoran otomatis (rancangan bagian 3 "Penomoran otomatis" dan modul 3):
 *   BM-YYYYMMDD-001 (barang masuk), TRX-YYYYMMDD-0001 (penjualan),
 *   OP-YYYYMMDD-01 (pengeluaran), SO-YYYYMM-01 (stok opname),
 *   SUP-001 (supplier), BRG-0001 (barang tanpa barcode).
 *
 * Aman dari tabrakan: bila dipanggil di dalam DB::transaction, baris nomor terakhir dengan prefix
 * yang sama dikunci (SELECT ... FOR UPDATE). InnoDB juga mengunci rentang indeks UNIQUE kolom itu,
 * sehingga transaksi lain yang meminta nomor dengan prefix sama menunggu sampai transaksi ini selesai.
 * Di luar transaksi (mis. pratinjau kode di form) nomor hanya perkiraan dan tidak dikunci.
 */
class NomorOtomatisService
{
    public function barangMasuk(?CarbonInterface $tanggal = null): string
    {
        return $this->berikutnya('barang_masuk', 'kode_barang_masuk', 'BM-'.$this->tgl($tanggal, 'Ymd').'-', 3);
    }

    public function penjualan(?CarbonInterface $tanggal = null): string
    {
        return $this->berikutnya('penjualan', 'no_transaksi', 'TRX-'.$this->tgl($tanggal, 'Ymd').'-', 4);
    }

    public function pengeluaran(?CarbonInterface $tanggal = null): string
    {
        return $this->berikutnya('pengeluaran_operasional', 'kode_pengeluaran', 'OP-'.$this->tgl($tanggal, 'Ymd').'-', 2);
    }

    public function stokOpname(?CarbonInterface $tanggal = null): string
    {
        return $this->berikutnya('stok_opname', 'kode_opname', 'SO-'.$this->tgl($tanggal, 'Ym').'-', 2);
    }

    public function supplier(): string
    {
        return $this->berikutnya('supplier', 'kode_supplier', 'SUP-', 3);
    }

    /** Dipakai bila kode/barcode barang dikosongkan. */
    public function barang(): string
    {
        return $this->berikutnya('barang', 'kode_barang', 'BRG-', 4);
    }

    /**
     * Nomor berikutnya = angka terbesar di belakang prefix + 1, diisi nol di depan sampai $panjangAngka digit.
     * Hanya kode berpola "<prefix><angka>" yang dihitung (barcode lain seperti BRG-ABC diabaikan).
     */
    public function berikutnya(string $tabel, string $kolom, string $prefix, int $panjangAngka): string
    {
        $query = DB::table($tabel)
            ->where($kolom, 'like', $prefix.'%')
            ->whereRaw("{$kolom} REGEXP ?", ['^'.preg_quote($prefix).'[0-9]+$'])
            // Urut panjang dulu agar ...-1000 dianggap lebih besar dari ...-999.
            ->orderByRaw("CHAR_LENGTH({$kolom}) DESC")
            ->orderByDesc($kolom);

        if (DB::transactionLevel() > 0) {
            $query->lockForUpdate();
        }

        $terakhir = $query->value($kolom);
        $nomor = $terakhir ? ((int) substr($terakhir, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $nomor, $panjangAngka, '0', STR_PAD_LEFT);
    }

    private function tgl(?CarbonInterface $tanggal, string $format): string
    {
        return ($tanggal ?? now())->format($format);
    }
}
