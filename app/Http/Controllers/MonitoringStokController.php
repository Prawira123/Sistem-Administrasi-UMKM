<?php

namespace App\Http\Controllers;

use App\Models\Barang;
use App\Models\KategoriBarang;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Proses 5.0 Monitoring Stok (Administrator dan Kasir): cek stok, peringatan stok minimum,
 * dan riwayat kartu stok per barang. Hanya membaca data; stok tidak diubah dari sini.
 */
class MonitoringStokController extends Controller
{
    /** Cek stok real-time barang aktif: filter kategori, status stok (aman/menipis/habis), pencarian. */
    public function index(Request $request): View
    {
        $filter = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'kategori' => ['nullable', 'integer'],
            'status' => ['nullable', 'in:aman,menipis,habis'],
        ]);
        $cari = trim((string) ($filter['q'] ?? ''));

        $barang = Barang::aktif()
            ->with('kategori')
            ->when($cari !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('kode_barang', 'like', '%'.addcslashes($cari, '%_\\').'%')
                ->orWhere('nama_barang', 'like', '%'.addcslashes($cari, '%_\\').'%')))
            ->when($filter['kategori'] ?? null, fn ($q, $id) => $q->where('id_kategori', $id))
            ->when($filter['status'] ?? null, fn ($q, $status) => $q->statusStok($status))
            ->orderBy('nama_barang')
            ->paginate(15)
            ->withQueryString();

        $jumlah = collect(['aman', 'menipis', 'habis'])
            ->mapWithKeys(fn ($status) => [$status => Barang::aktif()->statusStok($status)->count()]);

        return view('stok.index', [
            'barang' => $barang,
            'filter' => $filter + ['q' => $cari],
            'jumlah' => $jumlah,
            'daftarKategori' => KategoriBarang::orderBy('nama_kategori')->pluck('nama_kategori', 'id_kategori'),
        ]);
    }

    /** Peringatan stok minimum (rancangan bagian 4): barang aktif dengan stok <= stok_minimum. */
    public function peringatan(): View
    {
        $barang = Barang::stokMenipis()
            ->with(['kategori', 'notifikasiStok' => fn ($q) => $q->whereIn('status', ['baru', 'dibaca'])->latest()])
            ->orderBy('stok')
            ->orderBy('nama_barang')
            ->get();

        return view('stok.peringatan', compact('barang'));
    }

    /** Riwayat mutasi kartu stok satu barang, urut waktu terbaru di atas. */
    public function kartu(Request $request, Barang $barang): View
    {
        $filter = $request->validate([
            'dari' => ['nullable', 'date_format:Y-m-d'],
            'sampai' => ['nullable', 'date_format:Y-m-d'],
            'jenis' => ['nullable', 'in:masuk,keluar,penyesuaian'],
        ]);

        $mutasi = $barang->kartuStok()
            ->with(['user', 'barangMasuk', 'penjualan', 'stokOpname'])
            ->when($filter['dari'] ?? null, fn ($q, $dari) => $q->whereDate('tanggal', '>=', $dari))
            ->when($filter['sampai'] ?? null, fn ($q, $sampai) => $q->whereDate('tanggal', '<=', $sampai))
            ->when($filter['jenis'] ?? null, fn ($q, $jenis) => $q->where('jenis_mutasi', $jenis))
            ->orderByDesc('tanggal')
            ->orderByDesc('id_kartu_stok')
            ->paginate(20)
            ->withQueryString();

        return view('stok.kartu', [
            'barang' => $barang->load('kategori'),
            'mutasi' => $mutasi,
            'filter' => $filter,
        ]);
    }
}
