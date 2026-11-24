<?php

namespace Tests\Unit;

use App\Models\Barang;
use App\Models\User;
use App\Services\KartuStokService;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Validasi referensi kartu stok (rancangan bagian 3). Semua kasus ditolak sebelum menulis ke database.
 */
class KartuStokServiceTest extends TestCase
{
    public static function kombinasiSalah(): array
    {
        return [
            'masuk tanpa id_barang_masuk' => ['masuk', 5, 0, []],
            'masuk dengan id_penjualan' => ['masuk', 5, 0, ['id_penjualan' => 1]],
            'masuk dengan dua sumber' => ['masuk', 5, 0, ['id_barang_masuk' => 1, 'id_penjualan' => 2]],
            'keluar tanpa id_penjualan' => ['keluar', 0, 5, []],
            'keluar dengan id_barang_masuk' => ['keluar', 0, 5, ['id_barang_masuk' => 1]],
            'penyesuaian tanpa sumber' => ['penyesuaian', 5, 0, []],
            'penyesuaian dari barang masuk' => ['penyesuaian', 5, 0, ['id_barang_masuk' => 1]],
            'penyesuaian dua sumber' => ['penyesuaian', 5, 0, ['id_penjualan' => 1, 'id_stok_opname' => 2]],
            'jenis tidak dikenal' => ['retur', 5, 0, ['id_barang_masuk' => 1]],
            'sumber tidak dikenal' => ['masuk', 5, 0, ['id_barang_masuk' => 1, 'id_supplier' => 1]],
            'masuk dengan jumlah keluar' => ['masuk', 5, 1, ['id_barang_masuk' => 1]],
            'masuk jumlah nol' => ['masuk', 0, 0, ['id_barang_masuk' => 1]],
            'keluar dengan jumlah masuk' => ['keluar', 1, 5, ['id_penjualan' => 1]],
            'penyesuaian masuk dan keluar sekaligus' => ['penyesuaian', 2, 3, ['id_stok_opname' => 1]],
        ];
    }

    #[DataProvider('kombinasiSalah')]
    public function test_menolak_kombinasi_referensi_yang_salah(string $jenis, int $masuk, int $keluar, array $sumber): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new KartuStokService)->catat($this->barangTersimpan(10), $jenis, $masuk, $keluar, $sumber, 'uji', new User);
    }

    public function test_menolak_bila_stok_barang_belum_disimpan(): void
    {
        $barang = $this->barangTersimpan(10);
        $barang->stok = 15; // belum save()

        $this->expectException(LogicException::class);

        (new KartuStokService)->catat($barang, 'masuk', 5, 0, ['id_barang_masuk' => 1], 'uji', new User);
    }

    public function test_menolak_bila_stok_sebelum_menjadi_negatif(): void
    {
        $this->expectException(InvalidArgumentException::class);

        // stok sesudah 3, masuk 5 → stok sebelum -2 (urutan pemanggilan salah)
        (new KartuStokService)->catat($this->barangTersimpan(3), 'masuk', 5, 0, ['id_barang_masuk' => 1], 'uji', new User);
    }

    /** Barang seolah sudah tersimpan (exists, tanpa perubahan), tanpa menyentuh database. */
    private function barangTersimpan(int $stok): Barang
    {
        $barang = (new Barang)->forceFill(['id_barang' => 1, 'stok' => $stok, 'hpp_rata_rata' => '1000.00']);
        $barang->exists = true;
        $barang->syncOriginal();

        return $barang;
    }
}
