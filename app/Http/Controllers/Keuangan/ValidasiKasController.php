<?php

namespace App\Http\Controllers\Keuangan;

use App\Http\Controllers\Controller;
use App\Http\Requests\Keuangan\StoreValidasiKasRequest;
use App\Models\ValidasiKas;
use App\Services\ValidasiKasService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/** Proses 6.0: validasi kas harian (uang tunai fisik vs penjualan tunai di sistem). Khusus Administrator. */
class ValidasiKasController extends Controller implements HasMiddleware
{
    public function __construct(private readonly ValidasiKasService $service) {}

    public static function middleware(): array
    {
        return [new Middleware('can:akses-keuangan')];
    }

    public function index(): View
    {
        $validasi = ValidasiKas::with(['user', 'kas'])
            ->orderByDesc('tanggal')
            ->paginate(15);

        $sudahHariIni = ValidasiKas::whereDate('tanggal', today())->exists();

        return view('keuangan.validasi-kas.index', compact('validasi', 'sudahHariIni'));
    }

    /** /validasi-kas/create?tanggal=Y-m-d (default hari ini): menampilkan total tunai sistem untuk tanggal itu. */
    public function create(Request $request): View|RedirectResponse
    {
        $awalBulan = now()->startOfMonth();
        $request->validate([
            'tanggal' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:'.$awalBulan->toDateString(), 'before_or_equal:today'],
        ], [
            'tanggal.after_or_equal' => 'Validasi kas hanya untuk tanggal di bulan berjalan (mulai '.$awalBulan->format('d/m/Y').').',
            'tanggal.before_or_equal' => 'Validasi kas tidak bisa dibuat untuk tanggal yang akan datang.',
        ]);

        $tanggal = Carbon::parse($request->query('tanggal', today()->toDateString()));

        if ($ada = ValidasiKas::whereDate('tanggal', $tanggal)->first()) {
            return redirect()->route('validasi-kas.show', $ada)
                ->with('warning', 'Kas tanggal ini sudah divalidasi. Validasi hanya satu kali per tanggal.');
        }

        return view('keuangan.validasi-kas.create', [
            'tanggal' => $tanggal,
            'totalTunaiSistem' => $this->service->totalTunaiSistem($tanggal),
        ]);
    }

    public function store(StoreValidasiKasRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $validasi = $this->service->simpan($data['tanggal'], (string) $data['uang_fisik'], $data['catatan'] ?? null, $request->user());

        return redirect()->route('validasi-kas.show', $validasi)->with('success', $validasi->status === 'sesuai'
            ? 'Validasi kas disimpan: uang fisik sesuai dengan sistem.'
            : 'Validasi kas disimpan dengan selisih; kas penyesuaian sudah dicatat.');
    }

    public function show(ValidasiKas $validasi): View
    {
        return view('keuangan.validasi-kas.show', ['validasi' => $validasi->load(['user', 'kas'])]);
    }
}
