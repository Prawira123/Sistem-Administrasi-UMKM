<?php

namespace App\Http\Controllers\Keuangan;

use App\Http\Controllers\Controller;
use App\Http\Requests\Keuangan\KategoriPengeluaranRequest;
use App\Models\KategoriPengeluaran;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;

/** Proses 6.0: kategori beban operasional (rincian beban di laporan laba rugi). Khusus Administrator. */
class KategoriPengeluaranController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [new Middleware('can:akses-keuangan')];
    }

    public function index(Request $request): View
    {
        $cari = trim((string) $request->query('q'));

        $kategori = KategoriPengeluaran::query()
            ->withCount('pengeluaran')
            ->when($cari !== '', fn ($q) => $q->where('nama_kategori', 'like', '%'.addcslashes($cari, '%_\\').'%'))
            ->orderBy('nama_kategori')
            ->paginate(15)
            ->withQueryString();

        return view('keuangan.kategori-pengeluaran.index', compact('kategori', 'cari'));
    }

    public function create(): View
    {
        return view('keuangan.kategori-pengeluaran.form', ['kategori' => new KategoriPengeluaran]);
    }

    public function store(KategoriPengeluaranRequest $request): RedirectResponse
    {
        KategoriPengeluaran::create($request->validated());

        return redirect()->route('kategori-pengeluaran.index')->with('success', 'Kategori pengeluaran berhasil ditambahkan.');
    }

    public function edit(KategoriPengeluaran $kategoriPengeluaran): View
    {
        return view('keuangan.kategori-pengeluaran.form', ['kategori' => $kategoriPengeluaran]);
    }

    public function update(KategoriPengeluaranRequest $request, KategoriPengeluaran $kategoriPengeluaran): RedirectResponse
    {
        $kategoriPengeluaran->update($request->validated());

        return redirect()->route('kategori-pengeluaran.index')->with('success', 'Kategori pengeluaran berhasil diperbarui.');
    }

    public function destroy(KategoriPengeluaran $kategoriPengeluaran): RedirectResponse
    {
        return $this->hapusAtauTolak(
            $kategoriPengeluaran,
            'kategori-pengeluaran.index',
            'Kategori pengeluaran berhasil dihapus.',
            'Kategori masih dipakai data pengeluaran sehingga tidak bisa dihapus.',
        );
    }
}
