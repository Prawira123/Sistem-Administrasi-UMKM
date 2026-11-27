<?php

namespace App\Http\Controllers\Keuangan;

use App\Http\Controllers\Controller;
use App\Http\Requests\Keuangan\PengeluaranRequest;
use App\Models\KategoriPengeluaran;
use App\Models\PengeluaranOperasional;
use App\Services\PengeluaranService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Proses 6.0: pengeluaran operasional (beban). Kas keluar operasional ditulis oleh PengeluaranService. */
class PengeluaranController extends Controller implements HasMiddleware
{
    public function __construct(private readonly PengeluaranService $service) {}

    public static function middleware(): array
    {
        return [new Middleware('can:akses-keuangan')];
    }

    public function index(Request $request): View
    {
        $filter = $request->validate([
            'dari' => ['nullable', 'date_format:Y-m-d'],
            'sampai' => ['nullable', 'date_format:Y-m-d'],
            'kategori' => ['nullable', 'integer'],
        ]);

        $query = PengeluaranOperasional::query()
            ->when($filter['dari'] ?? null, fn ($q, $dari) => $q->whereDate('tanggal_pengeluaran', '>=', $dari))
            ->when($filter['sampai'] ?? null, fn ($q, $sampai) => $q->whereDate('tanggal_pengeluaran', '<=', $sampai))
            ->when($filter['kategori'] ?? null, fn ($q, $id) => $q->where('id_kategori_pengeluaran', $id));

        return view('keuangan.pengeluaran.index', [
            'total' => (string) (clone $query)->sum('jumlah'),
            'pengeluaran' => $query->with(['kategori', 'user'])
                ->orderByDesc('tanggal_pengeluaran')
                ->orderByDesc('id_pengeluaran')
                ->paginate(15)
                ->withQueryString(),
            'filter' => $filter,
            'daftarKategori' => $this->daftarKategori(),
        ]);
    }

    public function create(): View
    {
        return view('keuangan.pengeluaran.form', [
            'pengeluaran' => new PengeluaranOperasional(['tanggal_pengeluaran' => today(), 'metode_pembayaran' => 'tunai']),
            'daftarKategori' => $this->daftarKategori(),
        ]);
    }

    public function store(PengeluaranRequest $request): RedirectResponse
    {
        $pengeluaran = $this->service->simpan($request->safe()->except(['bukti', 'hapus_bukti']), $request->file('bukti'), $request->user());

        return redirect()->route('pengeluaran.index')
            ->with('success', "Pengeluaran {$pengeluaran->kode_pengeluaran} disimpan dan tercatat sebagai kas keluar.");
    }

    public function edit(PengeluaranOperasional $pengeluaran): View|RedirectResponse
    {
        if (! $pengeluaran->isBulanBerjalan()) {
            return $this->tolakBulanLalu();
        }

        return view('keuangan.pengeluaran.form', [
            'pengeluaran' => $pengeluaran,
            'daftarKategori' => $this->daftarKategori(),
        ]);
    }

    public function update(PengeluaranRequest $request, PengeluaranOperasional $pengeluaran): RedirectResponse
    {
        if (! $pengeluaran->isBulanBerjalan()) {
            return $this->tolakBulanLalu();
        }

        $this->service->perbarui(
            $pengeluaran,
            $request->safe()->except(['bukti', 'hapus_bukti']),
            $request->file('bukti'),
            $request->boolean('hapus_bukti'),
        );

        return redirect()->route('pengeluaran.index')
            ->with('success', "Pengeluaran {$pengeluaran->kode_pengeluaran} diperbarui; kas keluarnya ikut disesuaikan.");
    }

    public function destroy(PengeluaranOperasional $pengeluaran): RedirectResponse
    {
        if (! $pengeluaran->isBulanBerjalan()) {
            return $this->tolakBulanLalu();
        }

        $this->service->hapus($pengeluaran);

        return redirect()->route('pengeluaran.index')
            ->with('success', "Pengeluaran {$pengeluaran->kode_pengeluaran} dan kas keluarnya dihapus.");
    }

    /** Tampilkan file bukti (hanya Administrator; tidak lewat link publik /storage). */
    public function bukti(PengeluaranOperasional $pengeluaran): StreamedResponse
    {
        abort_unless($pengeluaran->bukti && Storage::disk(PengeluaranService::DISK)->exists($pengeluaran->bukti), 404);

        return Storage::disk(PengeluaranService::DISK)->response($pengeluaran->bukti);
    }

    /** Pengeluaran bulan sebelumnya terkunci (dicek juga di PengeluaranService). */
    private function tolakBulanLalu(): RedirectResponse
    {
        return redirect()->route('pengeluaran.index')->with('error', PengeluaranService::PESAN_BULAN_LALU);
    }

    private function daftarKategori()
    {
        return KategoriPengeluaran::orderBy('nama_kategori')->pluck('nama_kategori', 'id_kategori_pengeluaran');
    }
}
