<?php

namespace App\Http\Controllers\Keuangan;

use App\Http\Controllers\Controller;
use App\Http\Requests\Keuangan\ModalPriveRequest;
use App\Models\Kas;
use App\Services\KasService;
use App\Support\Uang;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Proses 6.0: buku kas dengan saldo berjalan, input modal (masuk) dan prive (keluar).
 * Semua penulisan lewat KasService; kas transaksi (penjualan, pembelian, operasional, penyesuaian)
 * hanya bisa berubah lewat transaksinya masing-masing.
 */
class KasController extends Controller implements HasMiddleware
{
    public function __construct(private readonly KasService $kasService) {}

    public static function middleware(): array
    {
        return [new Middleware('can:akses-keuangan')];
    }

    public function index(Request $request): View
    {
        $filter = $request->validate([
            'dari' => ['nullable', 'date_format:Y-m-d'],
            'sampai' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:dari'],
            'kategori' => ['nullable', 'in:penjualan,pembelian_barang,operasional,modal,prive,penyesuaian'],
            'jenis' => ['nullable', 'in:masuk,keluar'],
        ]);

        // Default: bulan berjalan.
        $dari = Carbon::parse($filter['dari'] ?? now()->startOfMonth()->toDateString())->startOfDay();
        $sampai = Carbon::parse($filter['sampai'] ?? now()->toDateString())->endOfDay();
        $tersaring = isset($filter['kategori']) || isset($filter['jenis']);

        $saldoAwal = $this->kasService->saldoSebelum($dari);

        $baris = Kas::query()
            ->with(['user', 'penjualan', 'barangMasuk', 'pengeluaran', 'validasiKas'])
            ->whereBetween('tanggal', [$dari, $sampai])
            ->when($filter['kategori'] ?? null, fn ($q, $k) => $q->where('kategori', $k))
            ->when($filter['jenis'] ?? null, fn ($q, $j) => $q->where('jenis', $j))
            ->orderBy('tanggal')
            ->orderBy('id_kas')
            ->get();

        // Saldo berjalan hanya bermakna bila semua kategori ditampilkan.
        $saldo = $saldoAwal;
        $masuk = '0.00';
        $keluar = '0.00';
        foreach ($baris as $k) {
            if ($k->jenis === KasService::MASUK) {
                $masuk = Uang::tambah($masuk, $k->jumlah);
                $saldo = Uang::tambah($saldo, $k->jumlah);
            } else {
                $keluar = Uang::tambah($keluar, $k->jumlah);
                $saldo = Uang::kurang($saldo, $k->jumlah);
            }
            $k->saldo_berjalan = $saldo;
        }

        $perKategori = Kas::whereBetween('tanggal', [$dari, $sampai])
            ->groupBy('jenis', 'kategori')
            ->select('jenis', 'kategori', DB::raw('SUM(jumlah) AS total'))
            ->orderBy('jenis')
            ->orderBy('kategori')
            ->get();

        return view('keuangan.kas.index', [
            'baris' => $baris,
            'filter' => $filter,
            'dari' => $dari,
            'sampai' => $sampai,
            'tersaring' => $tersaring,
            'saldoAwal' => $saldoAwal,
            'totalMasuk' => $masuk,
            'totalKeluar' => $keluar,
            'saldoAkhir' => $tersaring ? null : $saldo,
            'perKategori' => $perKategori,
        ]);
    }

    /** Form modal atau prive: /kas/create?kategori=modal|prive */
    public function create(Request $request): View
    {
        $kategori = $request->query('kategori') === 'prive' ? 'prive' : 'modal';

        return view('keuangan.kas.create', ['kategori' => $kategori]);
    }

    public function store(ModalPriveRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $jenis = KasService::KATEGORI_MANUAL[$data['kategori']];

        $this->kasService->catat(
            $jenis,
            $data['kategori'],
            (string) $data['jumlah'],
            [],
            $request->user(),
            Carbon::parse($data['tanggal'])->setTimeFrom(now()),
            $data['keterangan'],
        );

        $label = $data['kategori'] === 'modal' ? 'Modal (kas masuk)' : 'Prive (kas keluar)';

        return redirect()->route('kas.index')->with('success', "{$label} berhasil dicatat.");
    }

    public function destroy(Kas $kas): RedirectResponse
    {
        if (array_key_exists($kas->kategori, KasService::KATEGORI_MANUAL) && ! $kas->isBulanBerjalan()) {
            return redirect()->route('kas.index')->with('error', KasService::PESAN_BULAN_LALU);
        }

        $this->kasService->hapusManual($kas);

        return redirect()->route('kas.index')->with('success', 'Baris kas '.$kas->kategori.' dihapus.');
    }
}
