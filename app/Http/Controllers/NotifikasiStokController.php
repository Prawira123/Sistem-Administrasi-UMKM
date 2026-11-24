<?php

namespace App\Http\Controllers;

use App\Models\NotifikasiStok;
use App\Services\NotifikasiStokService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Lonceng notifikasi stok minimum di header (Administrator dan Kasir).
 * Notifikasi tidak dihapus; statusnya berubah baru → dibaca, lalu selesai otomatis saat stok diisi ulang.
 */
class NotifikasiStokController extends Controller
{
    public function baca(Request $request, NotifikasiStok $notifikasi, NotifikasiStokService $service): RedirectResponse
    {
        $service->tandaiDibaca($notifikasi, $request->user());

        return back();
    }

    public function bacaSemua(Request $request, NotifikasiStokService $service): RedirectResponse
    {
        $jumlah = $service->tandaiSemuaDibaca($request->user());

        return back()->with('success', $jumlah > 0
            ? "{$jumlah} notifikasi stok ditandai sudah dibaca."
            : 'Tidak ada notifikasi baru.');
    }
}
