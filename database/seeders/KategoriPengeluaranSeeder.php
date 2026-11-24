<?php

namespace Database\Seeders;

use App\Models\KategoriPengeluaran;
use Illuminate\Database\Seeder;

class KategoriPengeluaranSeeder extends Seeder
{
    public function run(): void
    {
        $data = [
            ['nama_kategori' => 'Listrik', 'keterangan' => 'Tagihan listrik toko'],
            ['nama_kategori' => 'Air', 'keterangan' => 'Tagihan air'],
            ['nama_kategori' => 'Sewa Tempat', 'keterangan' => 'Sewa lapak/kios'],
            ['nama_kategori' => 'Transportasi', 'keterangan' => 'Ongkos belanja/ambil barang'],
            ['nama_kategori' => 'Kemasan dan ATK', 'keterangan' => 'Kantong plastik, kertas struk, alat tulis'],
            ['nama_kategori' => 'Upah', 'keterangan' => 'Upah kasir'],
            ['nama_kategori' => 'Lain-lain', 'keterangan' => 'Pengeluaran operasional lainnya'],
        ];

        foreach ($data as $row) {
            KategoriPengeluaran::updateOrCreate(
                ['nama_kategori' => $row['nama_kategori']],
                $row
            );
        }
    }
}
