<?php

namespace App\Providers;

use App\Models\Penjualan;
use App\Models\User;
use App\Services\NotifikasiStokService;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Hak akses yang khusus Administrator (docs/rancangan.md bagian 1).
     */
    public const GATE_ADMINISTRATOR = [
        'kelola-master',      // supplier, kategori barang, pengguna
        'ubah-harga-jual',
        'hapus-barang',
        'setujui-opname',
        'batalkan-penjualan',
        'akses-keuangan',
        'akses-laporan',
        'akses-ai',
    ];

    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        foreach (self::GATE_ADMINISTRATOR as $ability) {
            Gate::define($ability, fn (User $user) => $user->isAdministrator());
        }

        // Riwayat penjualan: Administrator melihat semua, Kasir hanya transaksi miliknya (detail dan nota).
        Gate::define('lihat-penjualan', fn (User $user, Penjualan $penjualan) => $user->isAdministrator()
            || $penjualan->id_user === $user->id_user);

        // Layout di resources/views/layouts dipakai sebagai komponen: <x-layouts::app>, <x-layouts::guest>.
        Blade::anonymousComponentPath(resource_path('views/layouts'), 'layouts');

        // Lonceng notifikasi stok minimum di header layout (modul 5).
        View::composer('layouts.partials.notifikasi-stok', fn ($view) => $view->with(
            app(NotifikasiStokService::class)->ringkasan()
        ));

        Blade::directive('rupiah', fn (string $ekspresi) => "<?php echo e(\\App\\Support\\Format::rupiah($ekspresi)); ?>");
        Blade::directive('tanggal', fn (string $ekspresi) => "<?php echo e(\\App\\Support\\Format::tanggal($ekspresi)); ?>");
    }
}
