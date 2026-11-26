<?php

namespace App\Providers;

use App\Models\Penjualan;
use App\Models\StokOpname;
use App\Models\User;
use App\Services\NotifikasiStokService;
use App\Services\StokOpnameService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\RateLimiter;
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

        // Stok opname: draft hanya bisa diubah/diajukan oleh pembuatnya atau Administrator.
        Gate::define('ubah-opname', fn (User $user, StokOpname $opname) => app(StokOpnameService::class)->bolehUbah($opname, $user));

        // AI Assistant: maksimal 10 pertanyaan per menit per Administrator.
        RateLimiter::for('ai-assistant', fn (Request $request) => Limit::perMinute(10)
            ->by((string) $request->user()?->id_user)
            ->response(fn () => back()->withInput()->with('error',
                'Terlalu banyak pertanyaan. Batasnya 10 pertanyaan per menit; tunggu sebentar lalu coba lagi.')));

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
