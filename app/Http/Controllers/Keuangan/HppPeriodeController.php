<?php

namespace App\Http\Controllers\Keuangan;

use App\Http\Controllers\Controller;
use App\Models\HppPeriode;
use App\Services\HppService;
use App\Support\Uang;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/** Proses 6.0: perhitungan HPP periodik per bulan. Khusus Administrator. */
class HppPeriodeController extends Controller implements HasMiddleware
{
    public function __construct(private readonly HppService $service) {}

    public static function middleware(): array
    {
        return [new Middleware('can:akses-keuangan')];
    }

    /** /hpp?bulan=YYYY-MM (default bulan berjalan). */
    public function index(Request $request): View
    {
        $request->validate(['bulan' => ['nullable', 'date_format:Y-m']]);
        $bulan = Carbon::createFromFormat('Y-m-d', ($request->query('bulan') ?? now()->format('Y-m')).'-01');
        [$awal, $akhir] = $this->service->periode($bulan->year, $bulan->month);

        $hasil = HppPeriode::with(['barang', 'user'])
            ->whereDate('periode_awal', $awal)
            ->whereDate('periode_akhir', $akhir)
            ->get()
            ->sortBy(fn ($h) => $h->barang->nama_barang)
            ->values();

        $jumlah = fn (string $kolom) => $hasil->reduce(fn ($t, $h) => Uang::tambah($t, $h->{$kolom}), '0.00');

        return view('keuangan.hpp.index', [
            'bulan' => $bulan,
            'hasil' => $hasil,
            'total' => [
                'nilai_persediaan_awal' => $jumlah('nilai_persediaan_awal'),
                'nilai_pembelian' => $jumlah('nilai_pembelian'),
                'nilai_persediaan_akhir' => $jumlah('nilai_persediaan_akhir'),
                'total_hpp' => $jumlah('total_hpp'),
                'qty_terjual' => $hasil->sum('qty_terjual'),
            ],
            'dihitungPada' => $hasil->max('updated_at'),
        ]);
    }

    public function hitung(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'bulan' => ['required', 'date_format:Y-m'],
            'hitung_ulang' => ['nullable', 'boolean'],
        ], ['bulan.required' => 'Bulan wajib dipilih.']);

        $bulan = Carbon::createFromFormat('Y-m-d', $data['bulan'].'-01');
        $hasil = $this->service->hitungBulan($bulan->year, $bulan->month, $request->user(), $request->boolean('hitung_ulang'));

        return redirect()->route('hpp.index', ['bulan' => $data['bulan']])
            ->with('success', "HPP periode {$bulan->translatedFormat('F Y')} dihitung untuk {$hasil->count()} barang.");
    }
}
