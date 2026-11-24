<?php

namespace Database\Seeders;

use App\Models\Supplier;
use Illuminate\Database\Seeder;

class SupplierSeeder extends Seeder
{
    public function run(): void
    {
        $data = [
            [
                'kode_supplier' => 'SUP-001',
                'nama_supplier' => 'Grosir Sembako Badung',
                'nama_kontak' => 'Pak Made',
                'no_telepon' => '081338123456',
                'alamat' => 'Jl. Raya Puspem, Mangupura, Badung',
                'status' => 'aktif',
            ],
            [
                'kode_supplier' => 'SUP-002',
                'nama_supplier' => 'CV Mitra Minuman',
                'nama_kontak' => 'Ibu Komang',
                'no_telepon' => '081239998877',
                'alamat' => 'Jl. Gatot Subroto Timur, Denpasar',
                'status' => 'aktif',
            ],
        ];

        foreach ($data as $row) {
            Supplier::updateOrCreate(
                ['kode_supplier' => $row['kode_supplier']],
                $row
            );
        }
    }
}
