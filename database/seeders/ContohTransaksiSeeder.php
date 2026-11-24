<?php

namespace Database\Seeders;

use App\Models\Barang;
use App\Models\Supplier;
use App\Models\User;
use App\Services\BarangMasukService;
use App\Services\KasService;
use App\Services\PenjualanService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Contoh transaksi lewat service (bukan insert langsung), agar stok awal, HPP rata-rata,
 * kartu stok, dan kas terisi dengan cara yang sama seperti aplikasi.
 *
 * Hasil: semua barang punya stok; Beras 5kg dan Pulpen Hitam sengaja di bawah stok minimum
 * (untuk contoh peringatan stok menipis). Teh Botol dan Air Mineral dibeli dua kali dengan
 * harga berbeda sehingga HPP rata-ratanya tertimbang. Dua contoh penjualan (tunai dan QRIS) mengisi
 * riwayat penjualan dan notifikasi stok minimum.
 */
class ContohTransaksiSeeder extends Seeder
{
    public function run(BarangMasukService $barangMasuk, KasService $kas, PenjualanService $penjualan): void
    {
        $admin = User::where('username', 'admin')->firstOrFail();
        $kasir = User::where('username', 'kasir')->firstOrFail();
        $barang = Barang::pluck('id_barang', 'kode_barang');
        $supplier = Supplier::pluck('id_supplier', 'kode_supplier');

        // Barang masuk hanya boleh di bulan berjalan: kemarin, kecuali hari ini tanggal 1.
        $hariPertama = now()->subDay()->isSameMonth(now()) ? now()->subDay() : now();

        // Modal awal usaha (kas masuk kategori modal), dicatat sebelum pembelian.
        DB::transaction(fn () => $kas->catat(
            KasService::MASUK, 'modal', '5000000.00', [], $admin, $hariPertama->copy()->setTime(8, 0), 'Modal awal usaha'
        ));

        $barangMasuk->simpan([
            'id_supplier' => $supplier['SUP-001'],
            'tanggal_masuk' => $hariPertama->toDateString(),
            'no_faktur' => 'GSB/0001',
            'metode_pembayaran' => 'tunai',
            'keterangan' => 'Stok awal toko',
            'items' => [
                ['id_barang' => $barang['BRG-0001'], 'jumlah' => 24, 'harga_beli' => '3000'],
                ['id_barang' => $barang['BRG-0002'], 'jumlah' => 48, 'harga_beli' => '2500'],
                ['id_barang' => $barang['BRG-0003'], 'jumlah' => 4, 'harga_beli' => '68000'],
                ['id_barang' => $barang['BRG-0004'], 'jumlah' => 24, 'harga_beli' => '15500', 'tanggal_kadaluarsa' => now()->addYear()->toDateString()],
                ['id_barang' => $barang['BRG-0005'], 'jumlah' => 12, 'harga_beli' => '21000', 'tanggal_kadaluarsa' => now()->addMonths(8)->toDateString()],
                ['id_barang' => $barang['BRG-0006'], 'jumlah' => 10, 'harga_beli' => '2000'],
            ],
        ], $admin);

        // Pembelian kedua dengan harga berbeda → HPP rata-rata tertimbang (dicatat oleh Kasir).
        $barangMasuk->simpan([
            'id_supplier' => $supplier['SUP-002'],
            'tanggal_masuk' => now()->toDateString(),
            'no_faktur' => 'CVM-778',
            'metode_pembayaran' => 'transfer',
            'items' => [
                ['id_barang' => $barang['BRG-0001'], 'jumlah' => 24, 'harga_beli' => '3200'],
                ['id_barang' => $barang['BRG-0002'], 'jumlah' => 24, 'harga_beli' => '2650'],
            ],
        ], $kasir);

        // Contoh penjualan oleh Kasir (lewat PenjualanService): stok berkurang, kartu stok keluar, kas masuk.
        // Beras (sisa 3, minimum 5) dan Pulpen (sisa 8, minimum 20) memicu notifikasi stok minimum.
        $penjualan->simpan([
            ['id_barang' => $barang['BRG-0002'], 'jumlah' => 3],
            ['id_barang' => $barang['BRG-0003'], 'jumlah' => 1],
        ], 'tunai', '100000', $kasir);

        $penjualan->simpan([
            ['id_barang' => $barang['BRG-0006'], 'jumlah' => 2],
            ['id_barang' => $barang['BRG-0005'], 'jumlah' => 1],
        ], 'qris', '0', $kasir);
    }
}
