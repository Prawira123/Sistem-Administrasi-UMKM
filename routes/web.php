<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\BarangMasukController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\AiAssistantController;
use App\Http\Controllers\Keuangan\HppPeriodeController;
use App\Http\Controllers\Keuangan\KasController;
use App\Http\Controllers\Keuangan\KategoriPengeluaranController;
use App\Http\Controllers\Keuangan\PengeluaranController;
use App\Http\Controllers\Keuangan\ValidasiKasController;
use App\Http\Controllers\LaporanController;
use App\Http\Controllers\Master\BarangController;
use App\Http\Controllers\Master\KategoriBarangController;
use App\Http\Controllers\Master\SupplierController;
use App\Http\Controllers\Master\UserController;
use App\Http\Controllers\MonitoringStokController;
use App\Http\Controllers\NotifikasiStokController;
use App\Http\Controllers\PenjualanController;
use App\Http\Controllers\StokOpnameController;
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

        // Proses 5.0 Monitoring Stok: cek stok, peringatan stok minimum, kartu stok per barang
        Route::get('/stok', [MonitoringStokController::class, 'index'])->name('stok.index');
        Route::get('/stok/peringatan', [MonitoringStokController::class, 'peringatan'])->name('stok.peringatan');
        Route::get('/stok/{barang}/kartu', [MonitoringStokController::class, 'kartu'])->name('stok.kartu');

        // Proses 5.0 Stok Opname: draft → diajukan (pembuat); persetujuan di grup Administrator
        Route::resource('stok-opname', StokOpnameController::class)
            ->except('destroy')
            ->parameters(['stok-opname' => 'opname']);
        Route::post('/stok-opname/{opname}/ajukan', [StokOpnameController::class, 'ajukan'])->name('stok-opname.ajukan');

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

        // Proses 6.0 Kelola Keuangan (controller juga memakai middleware can:akses-keuangan)
        Route::resource('kategori-pengeluaran', KategoriPengeluaranController::class)->except('show');
        Route::get('/pengeluaran/{pengeluaran}/bukti', [PengeluaranController::class, 'bukti'])->name('pengeluaran.bukti');
        Route::resource('pengeluaran', PengeluaranController::class)->except('show');
        Route::resource('kas', KasController::class)->only(['index', 'create', 'store', 'destroy'])->parameters(['kas' => 'kas']);
        Route::resource('validasi-kas', ValidasiKasController::class)->only(['index', 'create', 'store', 'show'])
            ->parameters(['validasi-kas' => 'validasi']);
        Route::get('/hpp', [HppPeriodeController::class, 'index'])->name('hpp.index');
        Route::post('/hpp/hitung', [HppPeriodeController::class, 'hitung'])->name('hpp.hitung');

        // Proses 7.0 Buat Laporan (controller juga memakai middleware can:akses-laporan); ?unduh=pdf untuk PDF
        Route::prefix('laporan')->name('laporan.')->controller(LaporanController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/pergerakan-stok', 'pergerakanStok')->name('pergerakan-stok');
            Route::get('/arus-kas', 'arusKas')->name('arus-kas');
            Route::get('/laba-rugi', 'labaRugi')->name('laba-rugi');
            Route::get('/penjualan', 'penjualan')->name('penjualan');
            Route::get('/stok-minimum', 'stokMinimum')->name('stok-minimum');
        });

        // Proses 8.0 AI Assistant (controller juga memakai middleware can:akses-ai); maks 10 pertanyaan/menit
        Route::get('/ai', [AiAssistantController::class, 'index'])->name('ai.index');
        Route::post('/ai', [AiAssistantController::class, 'store'])->middleware('throttle:ai-assistant')->name('ai.tanya');
        Route::get('/ai/riwayat', [AiAssistantController::class, 'riwayat'])->name('ai.riwayat');
        Route::get('/ai/riwayat/{riwayat}', [AiAssistantController::class, 'show'])->name('ai.show');

        // Proses 5.0: persetujuan / penolakan stok opname
        Route::post('/stok-opname/{opname}/setujui', [StokOpnameController::class, 'setujui'])->name('stok-opname.setujui');
        Route::post('/stok-opname/{opname}/tolak', [StokOpnameController::class, 'tolak'])->name('stok-opname.tolak');
        Route::resource('kategori-barang', KategoriBarangController::class)->except('show');
        Route::resource('supplier', SupplierController::class)->except('show');
        Route::resource('pengguna', UserController::class)
            ->except('show')
            ->parameters(['pengguna' => 'user']);
    });
});
