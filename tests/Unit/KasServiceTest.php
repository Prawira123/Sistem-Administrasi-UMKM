<?php

namespace Tests\Unit;

use App\Services\KasService;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Validasi referensi kas (rancangan bagian 3). Validasi berjalan sebelum menulis ke database.
 */
class KasServiceTest extends TestCase
{
    public static function kombinasiSalah(): array
    {
        return [
            'penjualan tanpa id_penjualan' => ['masuk', 'penjualan', '1000', []],
            'penjualan berjenis keluar' => ['keluar', 'penjualan', '1000', ['id_penjualan' => 1]],
            'pembelian tanpa id_barang_masuk' => ['keluar', 'pembelian_barang', '1000', ['id_penjualan' => 1]],
            'pembelian berjenis masuk' => ['masuk', 'pembelian_barang', '1000', ['id_barang_masuk' => 1]],
            'operasional tanpa id_pengeluaran' => ['keluar', 'operasional', '1000', []],
            'modal berjenis keluar' => ['keluar', 'modal', '1000', []],
            'prive berjenis masuk' => ['masuk', 'prive', '1000', []],
            'modal dengan referensi penjualan' => ['masuk', 'modal', '1000', ['id_penjualan' => 1]],
            'pembelian dengan dua referensi' => ['keluar', 'pembelian_barang', '1000', ['id_barang_masuk' => 1, 'id_pengeluaran' => 2]],
            'penyesuaian dengan id_penjualan' => ['masuk', 'penyesuaian', '1000', ['id_penjualan' => 1]],
            'jumlah nol' => ['masuk', 'modal', '0', []],
            'jumlah negatif' => ['keluar', 'prive', '-500', []],
            'jumlah bukan angka' => ['masuk', 'modal', 'abc', []],
            'kategori tidak dikenal' => ['masuk', 'hibah', '1000', []],
            'referensi tidak dikenal' => ['masuk', 'modal', '1000', ['id_barang' => 1]],
        ];
    }

    #[DataProvider('kombinasiSalah')]
    public function test_menolak_kombinasi_referensi_yang_salah(string $jenis, string $kategori, string $jumlah, array $referensi): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new KasService)->validasi($jenis, $kategori, $jumlah, $referensi);
    }

    public function test_menerima_kombinasi_yang_benar(): void
    {
        $kas = new KasService;

        $hasil = $kas->validasi('keluar', 'pembelian_barang', '72000.00', ['id_barang_masuk' => 7]);
        $this->assertSame(7, $hasil['id_barang_masuk']);
        $this->assertNull($hasil['id_penjualan']);

        $kas->validasi('masuk', 'penjualan', '15000', ['id_penjualan' => 1]);
        $kas->validasi('keluar', 'operasional', '50000', ['id_pengeluaran' => 1]);
        $kas->validasi('masuk', 'modal', '5000000', []);
        $kas->validasi('keluar', 'prive', '100000', []);
        $kas->validasi('keluar', 'penyesuaian', '2000', ['id_validasi_kas' => 1]);
        $kas->validasi('masuk', 'penyesuaian', '2000', []);

        $this->addToAssertionCount(6);
    }
}
