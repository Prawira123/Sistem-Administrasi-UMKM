<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\StoreSupplierRequest;
use App\Http\Requests\Master\UpdateSupplierRequest;
use App\Models\Supplier;
use App\Services\NomorOtomatisService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Proses 2.0 Kelola Data Master: supplier (khusus Administrator).
 * Supplier yang sudah dipakai barang masuk tidak bisa dihapus, cukup dinonaktifkan.
 */
class SupplierController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('kelola-master');

        $cari = trim((string) $request->query('q'));

        $supplier = Supplier::query()
            ->when($cari !== '', fn ($q) => $q->where(function ($q) use ($cari) {
                $q->where('kode_supplier', 'like', "%{$cari}%")
                    ->orWhere('nama_supplier', 'like', "%{$cari}%")
                    ->orWhere('nama_kontak', 'like', "%{$cari}%");
            }))
            ->orderBy('kode_supplier')
            ->paginate(10)
            ->withQueryString();

        return view('master.supplier.index', compact('supplier', 'cari'));
    }

    public function create(NomorOtomatisService $nomor): View
    {
        Gate::authorize('kelola-master');

        return view('master.supplier.create', [
            'supplier' => new Supplier(['status' => 'aktif']),
            'kodeBerikutnya' => $nomor->supplier(), // pratinjau; nomor final ditentukan saat simpan
        ]);
    }

    public function store(StoreSupplierRequest $request, NomorOtomatisService $nomor): RedirectResponse
    {
        // Di dalam transaksi agar nomor SUP-xxx dikunci dan tidak bentrok.
        $supplier = DB::transaction(function () use ($request, $nomor) {
            $supplier = new Supplier($request->validated());
            $supplier->kode_supplier = $nomor->supplier();
            $supplier->save();

            return $supplier;
        });

        return redirect()->route('supplier.index')
            ->with('success', "Supplier {$supplier->kode_supplier} berhasil ditambahkan.");
    }

    public function edit(Supplier $supplier): View
    {
        Gate::authorize('kelola-master');

        return view('master.supplier.edit', compact('supplier'));
    }

    public function update(UpdateSupplierRequest $request, Supplier $supplier): RedirectResponse
    {
        $supplier->update($request->validated());

        return redirect()->route('supplier.index')->with('success', 'Data supplier berhasil diperbarui.');
    }

    public function destroy(Supplier $supplier): RedirectResponse
    {
        Gate::authorize('kelola-master');

        return $this->hapusAtauTolak($supplier, 'supplier.index', 'Supplier berhasil dihapus.');
    }
}
