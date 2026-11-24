<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\BarangMasukController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Master\BarangController;
use App\Http\Controllers\Master\KategoriBarangController;
use App\Http\Controllers\Master\SupplierController;
use App\Http\Controllers\Master\UserController;
use App\Http\Controllers\NotifikasiStokController;
use App\Http\Controllers\PenjualanController;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route(Auth::check() ? 'dashboard' : 'login'));

// Proses 1.0 Login
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->name('login.attempt');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

    // Halaman untuk Administrator dan Kasir
    Route::middleware('role:'.User::ROLE_ADMINISTRATOR.','.User::ROLE_KASIR)->group(function () {
        Route::get('/dashboard', DashboardController::class)->name('dashboard');

        // Proses 2.0: Kasir boleh menambah dan mengubah barang (tanpa harga_jual, tanpa hapus)
        Route::get('/barang/cari', [BarangController::class, 'cari'])->name('barang.cari'); // JSON untuk POS
        Route::resource('barang', BarangController::class)->except(['show', 'destroy']);

        // Proses 3.0 Kelola Barang Masuk: tanpa edit/hapus agar HPP dan kartu stok konsisten
        Route::resource('barang-masuk', BarangMasukController::class)->only(['index', 'create', 'store', 'show']);

        // Proses 4.0 Transaksi Penjualan: tanpa edit/hapus; koreksi lewat pembatalan (Administrator)
        Route::get('/penjualan/{penjualan}/nota', [PenjualanController::class, 'nota'])->name('penjualan.nota');
        Route::resource('penjualan', PenjualanController::class)->only(['index', 'create', 'store', 'show']);

        // Lonceng notifikasi stok minimum
        Route::post('/notifikasi-stok/baca-semua', [NotifikasiStokController::class, 'bacaSemua'])->name('notifikasi-stok.baca-semua');
        Route::post('/notifikasi-stok/{notifikasi}/baca', [NotifikasiStokController::class, 'baca'])->name('notifikasi-stok.baca');
    });

    // Halaman khusus Administrator
    Route::middleware('role:'.User::ROLE_ADMINISTRATOR)->group(function () {
        // Proses 2.0 Kelola Data Master
        Route::delete('/barang/{barang}', [BarangController::class, 'destroy'])->name('barang.destroy');

        // Proses 4.0: pembatalan penjualan
        Route::post('/penjualan/{penjualan}/batal', [PenjualanController::class, 'batal'])->name('penjualan.batal');
        Route::resource('kategori-barang', KategoriBarangController::class)->except('show');
        Route::resource('supplier', SupplierController::class)->except('show');
        Route::resource('pengguna', UserController::class)
            ->except('show')
            ->parameters(['pengguna' => 'user']);
    });
});
