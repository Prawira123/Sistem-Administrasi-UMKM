<?php

namespace Database\Seeders;

use App\Models\KategoriBarang;
use Illuminate\Database\Seeder;

class KategoriBarangSeeder extends Seeder
{
    public function run(): void
    {
        $data = [
            ['nama_kategori' => 'Makanan Ringan', 'keterangan' => 'Snack, biskuit, wafer'],
            ['nama_kategori' => 'Minuman', 'keterangan' => 'Air mineral, teh, kopi kemasan'],
            ['nama_kategori' => 'Sembako', 'keterangan' => 'Beras, gula, minyak goreng'],
            ['nama_kategori' => 'Alat Tulis', 'keterangan' => 'Pulpen, buku, kertas'],
            ['nama_kategori' => 'Kebersihan', 'keterangan' => 'Sabun, tisu, deterjen'],
        ];

        foreach ($data as $row) {
            KategoriBarang::updateOrCreate(
                ['nama_kategori' => $row['nama_kategori']],
                $row
            );
        }
    }
}
