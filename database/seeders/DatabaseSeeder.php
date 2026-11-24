<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            KategoriBarangSeeder::class,
            KategoriPengeluaranSeeder::class,
            SupplierSeeder::class,
            BarangSeeder::class,
            ContohTransaksiSeeder::class, // barang masuk contoh lewat service (stok awal + kartu stok + kas)
        ]);
    }
}
