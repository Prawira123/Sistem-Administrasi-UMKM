<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBarangMasukRequest;
use App\Models\Barang;
use App\Models\BarangMasuk;
use App\Models\Supplier;
use App\Services\BarangMasukService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Proses 3.0 Kelola Barang Masuk (Administrator dan Kasir).
 * Hanya index, create, store, show: barang masuk tidak bisa diedit/dihapus setelah disimpan.
 */
class BarangMasukController extends Controller
{
    public function index(Request $request): View
    {
        $filter = $request->validate([
            'dari' => ['nullable', 'date_format:Y-m-d'],
            'sampai' => ['nullable', 'date_format:Y-m-d'],
            'supplier' => ['nullable', 'integer'],
        ]);

        $barangMasuk = BarangMasuk::query()
            ->with(['supplier', 'user'])
            ->withCount('detail')
            ->when($filter['dari'] ?? null, fn ($q, $dari) => $q->whereDate('tanggal_masuk', '>=', $dari))
            ->when($filter['sampai'] ?? null, fn ($q, $sampai) => $q->whereDate('tanggal_masuk', '<=', $sampai))
            ->when($filter['supplier'] ?? null, fn ($q, $id) => $q->where('id_supplier', $id))
            ->orderByDesc('tanggal_masuk')
            ->orderByDesc('id_barang_masuk')
            ->paginate(10)
            ->withQueryString();

        return view('barang-masuk.index', [
            'barangMasuk' => $barangMasuk,
            'filter' => $filter,
            'daftarSupplier' => Supplier::orderBy('nama_supplier')->pluck('nama_supplier', 'id_supplier'),
        ]);
    }

    public function create(): View
    {
        return view('barang-masuk.create', [
            'daftarSupplier' => Supplier::where('status', 'aktif')->orderBy('nama_supplier')->pluck('nama_supplier', 'id_supplier'),
            // Data ringkas untuk pencarian barang di browser (Alpine.js).
            'daftarBarang' => Barang::aktif()->orderBy('nama_barang')->get()->map(fn (Barang $b) => [
                'id' => $b->id_barang,
                'kode' => $b->kode_barang,
                'nama' => $b->nama_barang,
                'satuan' => $b->satuan,
                'stok' => $b->stok,
                'harga_beli_terakhir' => $b->harga_beli_terakhir,
            ])->values(),
        ]);
    }

    public function store(StoreBarangMasukRequest $request, BarangMasukService $service): RedirectResponse
    {
        $barangMasuk = $service->simpan($request->validated(), $request->user());

        return redirect()->route('barang-masuk.show', $barangMasuk)
            ->with('success', "Barang masuk {$barangMasuk->kode_barang_masuk} berhasil disimpan. Stok dan HPP sudah diperbarui.");
    }

    public function show(BarangMasuk $barangMasuk): View
    {
        $barangMasuk->load(['supplier', 'user', 'detail.barang', 'kas']);

        return view('barang-masuk.show', compact('barangMasuk'));
    }
}
