<?php

namespace App\Http\Controllers;

use App\Http\Requests\StokOpname\SimpanHitunganRequest;
use App\Http\Requests\StokOpname\StoreStokOpnameRequest;
use App\Http\Requests\StokOpname\TolakStokOpnameRequest;
use App\Models\KategoriBarang;
use App\Models\StokOpname;
use App\Services\StokOpnameService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Proses 5.0: stok opname. Input hitung dan ajukan: Administrator/Kasir (pembuat draft).
 * Persetujuan dan penolakan: khusus Administrator (gate setujui-opname).
 */
class StokOpnameController extends Controller
{
    public function __construct(private readonly StokOpnameService $service) {}

    public function index(Request $request): View
    {
        $filter = $request->validate(['status' => ['nullable', 'in:draft,diajukan,final']]);

        $opname = StokOpname::query()
            ->with(['user', 'approver'])
            ->withCount([
                'detail',
                'detail as berselisih_count' => fn ($q) => $q->where('selisih', '!=', 0),
            ])
            ->when($filter['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->orderByDesc('tanggal_opname')
            ->orderByDesc('id_stok_opname')
            ->paginate(10)
            ->withQueryString();

        return view('stok-opname.index', compact('opname', 'filter'));
    }

    public function create(): View
    {
        return view('stok-opname.create', [
            'daftarKategori' => KategoriBarang::orderBy('nama_kategori')->pluck('nama_kategori', 'id_kategori'),
        ]);
    }

    public function store(StoreStokOpnameRequest $request): RedirectResponse
    {
        $opname = $this->service->buatDraft(
            $request->user(),
            $request->validated('id_kategori') ? (int) $request->validated('id_kategori') : null,
            $request->validated('keterangan'),
        );

        return redirect()->route('stok-opname.edit', $opname)
            ->with('success', "Draft {$opname->kode_opname} dibuat. Isi stok fisik hasil hitung di rak.");
    }

    public function show(StokOpname $opname): View
    {
        $opname->load(['user', 'approver', 'detail' => fn ($q) => $q->with('barang')->orderByRaw('selisih = 0')->orderBy('id_detail_opname')]);

        return view('stok-opname.show', [
            'opname' => $opname,
            'perubahan' => $opname->isDiajukan() ? $this->service->perubahanSejakDraft($opname) : collect(),
        ]);
    }

    public function edit(StokOpname $opname): View|RedirectResponse
    {
        if (! $opname->isDraft()) {
            return redirect()->route('stok-opname.show', $opname)
                ->with('error', "Stok opname {$opname->kode_opname} sudah {$opname->status} dan tidak bisa diedit.");
        }

        Gate::authorize('ubah-opname', $opname);

        $opname->load(['user', 'detail' => fn ($q) => $q->with('barang')->orderBy('id_detail_opname')]);

        return view('stok-opname.edit', [
            'opname' => $opname,
            'saranKeterangan' => StokOpnameService::KETERANGAN_SELISIH,
        ]);
    }

    /** Simpan hasil hitung; bila tombol "Simpan & Ajukan" dipakai, langsung diajukan. */
    public function update(SimpanHitunganRequest $request, StokOpname $opname): RedirectResponse
    {
        if ($opname->isDraft()) {
            Gate::authorize('ubah-opname', $opname);
        }

        $this->service->simpanHitungan($opname, $request->validated('detail'), $request->user());

        if ($request->boolean('ajukan')) {
            $this->service->ajukan($opname, $request->user());

            return redirect()->route('stok-opname.show', $opname)
                ->with('success', "Stok opname {$opname->kode_opname} diajukan. Stok berubah setelah disetujui Administrator.");
        }

        return redirect()->route('stok-opname.edit', $opname)->with('success', 'Hasil hitung disimpan sebagai draft.');
    }

    public function ajukan(Request $request, StokOpname $opname): RedirectResponse
    {
        if ($opname->isDraft()) {
            Gate::authorize('ubah-opname', $opname);
        }

        $this->service->ajukan($opname, $request->user());

        return redirect()->route('stok-opname.show', $opname)
            ->with('success', "Stok opname {$opname->kode_opname} diajukan. Stok berubah setelah disetujui Administrator.");
    }

    public function setujui(Request $request, StokOpname $opname): RedirectResponse
    {
        Gate::authorize('setujui-opname');

        $this->service->setujui($opname, $request->user());

        return redirect()->route('stok-opname.show', $opname)
            ->with('success', "Stok opname {$opname->kode_opname} disetujui. Stok sudah disesuaikan dan tercatat di kartu stok.");
    }

    public function tolak(TolakStokOpnameRequest $request, StokOpname $opname): RedirectResponse
    {
        $this->service->tolak($opname, $request->validated('catatan'), $request->user());

        return redirect()->route('stok-opname.show', $opname)
            ->with('warning', "Stok opname {$opname->kode_opname} dikembalikan ke draft untuk dihitung ulang.");
    }
}
