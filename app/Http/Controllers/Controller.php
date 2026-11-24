<?php

namespace App\Http\Controllers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;

abstract class Controller
{
    /** Pesan bila data tidak bisa dihapus karena FK ON DELETE RESTRICT. */
    public const PESAN_DIPAKAI_TRANSAKSI = 'Data sudah dipakai transaksi, nonaktifkan saja.';

    /**
     * Hapus data; bila ditolak FK (RESTRICT) karena sudah dipakai tabel lain,
     * kembali dengan pesan ramah, bukan halaman error.
     */
    protected function hapusAtauTolak(
        Model $model,
        string $routeIndex,
        string $pesanSukses,
        string $pesanDitolak = self::PESAN_DIPAKAI_TRANSAKSI,
    ): RedirectResponse {
        try {
            $model->delete();
        } catch (QueryException $e) {
            // SQLSTATE 23000: pelanggaran integrity constraint (MySQL 1451: baris induk masih dirujuk).
            if ($e->getCode() !== '23000') {
                throw $e;
            }

            return back()->with('error', $pesanDitolak);
        }

        return redirect()->route($routeIndex)->with('success', $pesanSukses);
    }
}
