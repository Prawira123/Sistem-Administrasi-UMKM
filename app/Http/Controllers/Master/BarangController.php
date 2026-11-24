<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\StoreBarangRequest;
use App\Http\Requests\Master\UpdateBarangRequest;
use App\Models\Barang;
use App\Models\KategoriBarang;
use App\Services\NomorOtomatisService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Proses 2.0 Kelola Data Master: barang.
 * Administrator: CRUD penuh. Kasir: tambah dan ubah, tanpa harga_jual dan tanpa hapus.
 * Stok, HPP rata-rata, dan harga beli terakhir tidak pernah diubah dari sini.
 */
class BarangController extends Controller
{
    public function index(Request $request): View
    {
        $cari = trim((string) $request->query('q'));
        $idKategori = $request->query('kategori');
        $filterStok = $request->query('stok');

        $barang = Barang::query()
            ->with('kategori')
            ->when($cari !== '', fn ($q) => $q->where(function ($q) use ($cari) {
                $q->where('kode_barang', 'like', "%{$cari}%")
                    ->orWhere('nama_barang', 'like', "%{$cari}%");
            }))
            ->when($idKategori, fn ($q) => $q->where('id_kategori', $idKategori))
            ->when($filterStok === 'menipis', fn ($q) => $q->stokMenipis())
            ->orderBy('nama_barang')
            ->paginate(10)
            ->withQueryString();

        return view('master.barang.index', [
            'barang' => $barang,
            'cari' => $cari,
            'idKategori' => $idKategori,
            'filterStok' => $filterStok,
            'daftarKategori' => $this->daftarKategori(),
        ]);
    }

    /**
     * Endpoint JSON untuk halaman kasir: GET /barang/cari?q=
     * Cocok dengan kode/barcode atau nama; hanya barang aktif dengan stok > 0.
     * Kode yang sama persis (hasil scan barcode) diletakkan paling atas dan ditandai "tepat".
     */
    public function cari(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        if ($q === '') {
            return response()->json(['data' => []]);
        }

        $pola = '%'.addcslashes($q, '%_\\').'%';

        $hasil = Barang::aktif()
            ->where('stok', '>', 0)
            ->where(fn ($w) => $w->where('kode_barang', 'like', $pola)->orWhere('nama_barang', 'like', $pola))
            ->orderByRaw('kode_barang = ? DESC', [$q])
            ->orderBy('nama_barang')
            ->limit(20)
            ->get()
            ->map(fn (Barang $b) => $b->dataKasir() + [
                'tepat' => strcasecmp($b->kode_barang, $q) === 0,
            ]);

        return response()->json(['data' => $hasil]);
    }

    public function create(): View
    {
        return view('master.barang.create', [
            'barang' => new Barang(['satuan' => 'pcs', 'stok_minimum' => 5, 'status' => 'aktif']),
            'daftarKategori' => $this->daftarKategori(),
        ]);
    }

    public function store(StoreBarangRequest $request, NomorOtomatisService $nomor): RedirectResponse
    {
        $data = $request->validated();

        // Barang baru: stok 0, HPP 0 (default kolom). harga_jual hanya ada bila diisi Administrator;
        // barang dari Kasir memakai harga_jual 0 sampai diatur Administrator.
        $barang = DB::transaction(function () use ($data, $nomor) {
            if (blank($data['kode_barang'] ?? null)) {
                $data['kode_barang'] = $nomor->barang(); // BRG-0001, dikunci di dalam transaksi
            }

            return Barang::create($data);
        });

        return redirect()->route('barang.index')
            ->with('success', "Barang {$barang->kode_barang} berhasil ditambahkan.");
    }

    public function edit(Barang $barang): View
    {
        return view('master.barang.edit', [
            'barang' => $barang,
            'daftarKategori' => $this->daftarKategori(),
        ]);
    }

    public function update(UpdateBarangRequest $request, Barang $barang): RedirectResponse
    {
        // validated() tidak pernah berisi stok/HPP, dan berisi harga_jual hanya untuk Administrator.
        $barang->update($request->validated());

        return redirect()->route('barang.index')->with('success', 'Data barang berhasil diperbarui.');
    }

    public function destroy(Barang $barang): RedirectResponse
    {
        Gate::authorize('hapus-barang');

        return $this->hapusAtauTolak($barang, 'barang.index', 'Barang berhasil dihapus.');
    }

    private function daftarKategori()
    {
        return KategoriBarang::orderBy('nama_kategori')->pluck('nama_kategori', 'id_kategori');
    }
}
