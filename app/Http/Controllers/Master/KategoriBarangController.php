<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\StoreKategoriBarangRequest;
use App\Http\Requests\Master\UpdateKategoriBarangRequest;
use App\Models\KategoriBarang;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Proses 2.0 Kelola Data Master: kategori barang (khusus Administrator).
 */
class KategoriBarangController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('kelola-master');

        $cari = trim((string) $request->query('q'));

        $kategori = KategoriBarang::query()
            ->withCount('barang')
            ->when($cari !== '', fn ($q) => $q->where('nama_kategori', 'like', "%{$cari}%"))
            ->orderBy('nama_kategori')
            ->paginate(10)
            ->withQueryString();

        return view('master.kategori-barang.index', compact('kategori', 'cari'));
    }

    public function create(): View
    {
        Gate::authorize('kelola-master');

        return view('master.kategori-barang.create', ['kategori' => new KategoriBarang]);
    }

    public function store(StoreKategoriBarangRequest $request): RedirectResponse
    {
        KategoriBarang::create($request->validated());

        return redirect()->route('kategori-barang.index')->with('success', 'Kategori barang berhasil ditambahkan.');
    }

    public function edit(KategoriBarang $kategoriBarang): View
    {
        Gate::authorize('kelola-master');

        return view('master.kategori-barang.edit', ['kategori' => $kategoriBarang]);
    }

    public function update(UpdateKategoriBarangRequest $request, KategoriBarang $kategoriBarang): RedirectResponse
    {
        $kategoriBarang->update($request->validated());

        return redirect()->route('kategori-barang.index')->with('success', 'Kategori barang berhasil diperbarui.');
    }

    public function destroy(KategoriBarang $kategoriBarang): RedirectResponse
    {
        Gate::authorize('kelola-master');

        return $this->hapusAtauTolak(
            $kategoriBarang,
            'kategori-barang.index',
            'Kategori barang berhasil dihapus.',
            'Kategori masih dipakai data barang sehingga tidak bisa dihapus. Pindahkan barangnya ke kategori lain terlebih dahulu.',
        );
    }
}
