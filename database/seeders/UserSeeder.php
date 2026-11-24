<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['username' => 'admin'],
            [
                'nama' => 'Administrator Toko',
                'email' => 'admin@toko.test',
                'password' => Hash::make('password'),
                'role' => 'administrator',
                'status' => 'aktif',
            ]
        );

        User::updateOrCreate(
            ['username' => 'kasir'],
            [
                'nama' => 'Kasir Toko',
                'email' => 'kasir@toko.test',
                'password' => Hash::make('password'),
                'role' => 'kasir',
                'status' => 'aktif',
            ]
        );
    }
}
