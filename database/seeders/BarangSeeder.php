<?php

namespace Database\Seeders;

use App\Models\Barang;
use App\Models\KategoriBarang;
use Illuminate\Database\Seeder;

class BarangSeeder extends Seeder
{
    public function run(): void
    {
        $kategori = KategoriBarang::query()->pluck('id_kategori', 'nama_kategori');

        $data = [
            [
                'kode_barang' => 'BRG-0001',
                'nama_barang' => 'Teh Botol Kotak 250ml',
                'kategori' => 'Minuman',
                'satuan' => 'pcs',
                'harga_jual' => 4000,
                'stok_minimum' => 12,
            ],
            [
                'kode_barang' => 'BRG-0002',
                'nama_barang' => 'Air Mineral 600ml',
                'kategori' => 'Minuman',
                'satuan' => 'pcs',
                'harga_jual' => 3500,
                'stok_minimum' => 24,
            ],
            [
                'kode_barang' => 'BRG-0003',
                'nama_barang' => 'Beras 5kg',
                'kategori' => 'Sembako',
                'satuan' => 'dus',
                'harga_jual' => 75000,
                'stok_minimum' => 5,
            ],
            [
                'kode_barang' => 'BRG-0004',
                'nama_barang' => 'Minyak Goreng 1L',
                'kategori' => 'Sembako',
                'satuan' => 'botol',
                'harga_jual' => 18000,
                'stok_minimum' => 10,
            ],
            [
                'kode_barang' => 'BRG-0005',
                'nama_barang' => 'Biskuit Kaleng 300g',
                'kategori' => 'Makanan Ringan',
                'satuan' => 'pcs',
                'harga_jual' => 25000,
                'stok_minimum' => 6,
            ],
            [
                'kode_barang' => 'BRG-0006',
                'nama_barang' => 'Pulpen Hitam',
                'kategori' => 'Alat Tulis',
                'satuan' => 'pcs',
                'harga_jual' => 3000,
                'stok_minimum' => 20,
            ],
        ];

        // Stok, HPP rata-rata, dan harga beli terakhir memakai default 0;
        // nilainya baru terisi lewat transaksi barang masuk.
        foreach ($data as $row) {
            Barang::updateOrCreate(
                ['kode_barang' => $row['kode_barang']],
                [
                    'id_kategori' => $kategori[$row['kategori']],
                    'nama_barang' => $row['nama_barang'],
                    'satuan' => $row['satuan'],
                    'harga_jual' => $row['harga_jual'],
                    'stok_minimum' => $row['stok_minimum'],
                    'status' => 'aktif',
                ]
            );
        }
    }
}
