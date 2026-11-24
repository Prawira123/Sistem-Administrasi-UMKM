<?php

namespace App\Http\Controllers;

use App\Http\Requests\BatalPenjualanRequest;
use App\Http\Requests\StorePenjualanRequest;
use App\Models\Barang;
use App\Models\Penjualan;
use App\Models\User;
use App\Services\PenjualanService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Proses 4.0 Transaksi Penjualan.
 * Administrator dan Kasir: POS, simpan, riwayat, detail, nota. Pembatalan khusus Administrator.
 */
class PenjualanController extends Controller
{
    public function index(Request $request): View
    {
        $filter = $request->validate([
            'dari' => ['nullable', 'date_format:Y-m-d'],
            'sampai' => ['nullable', 'date_format:Y-m-d'],
            'kasir' => ['nullable', 'integer'],
            'status' => ['nullable', 'in:selesai,batal'],
        ]);

        $user = $request->user();

        // Kasir hanya melihat transaksi miliknya; filter kasir hanya berlaku untuk Administrator.
        if (! $user->isAdministrator()) {
            $filter['kasir'] = $user->id_user;
        }

        $penjualan = Penjualan::query()
            ->with('user')
            ->when($filter['dari'] ?? null, fn ($q, $dari) => $q->whereDate('tanggal_penjualan', '>=', $dari))
            ->when($filter['sampai'] ?? null, fn ($q, $sampai) => $q->whereDate('tanggal_penjualan', '<=', $sampai))
            ->when($filter['kasir'] ?? null, fn ($q, $id) => $q->where('id_user', $id))
            ->when($filter['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->orderByDesc('tanggal_penjualan')
            ->orderByDesc('id_penjualan')
            ->paginate(15)
            ->withQueryString();

        return view('penjualan.index', [
            'penjualan' => $penjualan,
            'filter' => $filter,
            'daftarKasir' => $user->isAdministrator() ? User::orderBy('nama')->pluck('nama', 'id_user') : collect(),
        ]);
    }

    /** Halaman kasir (POS). Barang dicari lewat endpoint JSON barang.cari. */
    public function create(Request $request): View
    {
        // Bila validasi gagal, isi keranjang dikembalikan dari old input (data barang diambil ulang dari DB).
        $lama = collect($request->old('items', []))->filter(fn ($item) => is_array($item));
        $barang = Barang::whereIn('id_barang', $lama->pluck('id_barang')->filter()->all())->get()->keyBy('id_barang');

        $keranjang = $lama
            ->filter(fn ($item) => isset($barang[$item['id_barang'] ?? null]))
            ->map(fn ($item) => [
                ...$barang[$item['id_barang']]->dataKasir(),
                'jumlah' => max(1, (int) ($item['jumlah'] ?? 1)),
            ])
            ->values();

        return view('penjualan.create', ['keranjang' => $keranjang]);
    }

    public function store(StorePenjualanRequest $request, PenjualanService $service): RedirectResponse
    {
        $data = $request->validated();

        $penjualan = $service->simpan(
            $data['items'],
            $data['metode_pembayaran'],
            (string) ($data['jumlah_bayar'] ?? '0'),
            $request->user(),
        );

        return redirect()->route('penjualan.show', $penjualan)
            ->with('success', "Transaksi {$penjualan->no_transaksi} berhasil disimpan.");
    }

    public function show(Penjualan $penjualan): View
    {
        Gate::authorize('lihat-penjualan', $penjualan);

        $penjualan->load(['user', 'detail.barang', 'kas']);

        return view('penjualan.show', compact('penjualan'));
    }

    /** Nota cetak ukuran struk 58 mm atau 80 mm (?ukuran=58|80). */
    public function nota(Request $request, Penjualan $penjualan): View
    {
        Gate::authorize('lihat-penjualan', $penjualan);

        $penjualan->load(['user', 'detail.barang']);
        $ukuran = $request->query('ukuran') === '58' ? 58 : 80;

        return view('penjualan.nota', compact('penjualan', 'ukuran'));
    }

    public function batal(BatalPenjualanRequest $request, Penjualan $penjualan, PenjualanService $service): RedirectResponse
    {
        Gate::authorize('batalkan-penjualan');

        $service->batalkan($penjualan, $request->validated('alasan'), $request->user());

        return redirect()->route('penjualan.show', $penjualan)
            ->with('success', "Transaksi {$penjualan->no_transaksi} dibatalkan. Stok dikembalikan dan kas penjualan dihapus.");
    }
}
