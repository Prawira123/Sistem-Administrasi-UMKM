<?php

namespace App\Services;

use App\Models\Barang;
use App\Models\NotifikasiStok;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Notifikasi stok minimum (rancangan bagian 3 dan tabel notifikasi_stok).
 *
 * Siklus status: baru → dibaca (dibuka pengguna) → selesai (stok sudah diisi ulang di atas minimum).
 *   - periksa()      : dipanggil setelah stok berkurang (penjualan), membuat notifikasi bila perlu.
 *   - tutupBilaAman(): dipanggil setelah stok bertambah (barang masuk, pembatalan penjualan).
 * Keduanya dipanggil di dalam transaksi yang sama dengan perubahan stok, saat baris barang sudah
 * dikunci (lockForUpdate), sehingga dua transaksi tidak bisa membuat notifikasi ganda untuk barang yang sama.
 */
class NotifikasiStokService
{
    /** Status notifikasi yang masih terbuka. */
    public const STATUS_TERBUKA = ['baru', 'dibaca'];

    /**
     * Bila stok <= stok_minimum dan belum ada notifikasi terbuka (baru/dibaca) untuk barang itu,
     * buat notifikasi baru. Mengembalikan notifikasi yang dibuat, atau null bila tidak perlu.
     */
    public function periksa(Barang $barang): ?NotifikasiStok
    {
        if ($barang->stok > $barang->stok_minimum) {
            return null;
        }

        $sudahAda = NotifikasiStok::where('id_barang', $barang->id_barang)
            ->whereIn('status', self::STATUS_TERBUKA)
            ->exists();

        if ($sudahAda) {
            return null;
        }

        return NotifikasiStok::create([
            'id_barang' => $barang->id_barang,
            'stok_saat_ini' => $barang->stok,
            'stok_minimum' => $barang->stok_minimum,
            'pesan' => mb_substr(
                "Stok {$barang->nama_barang} tinggal {$barang->stok} {$barang->satuan} (minimum {$barang->stok_minimum}).",
                0,
                255,
            ),
            'status' => 'baru',
        ]);
    }

    /**
     * Bila stok > stok_minimum, notifikasi barang itu yang masih baru/dibaca diubah menjadi selesai.
     *
     * @return int jumlah notifikasi yang ditutup
     */
    public function tutupBilaAman(Barang $barang): int
    {
        if ($barang->stok <= $barang->stok_minimum) {
            return 0;
        }

        return NotifikasiStok::where('id_barang', $barang->id_barang)
            ->whereIn('status', self::STATUS_TERBUKA)
            ->update(['status' => 'selesai', 'updated_at' => now()]);
    }

    /** Tandai satu notifikasi berstatus baru sebagai dibaca (isi dibaca_oleh dan dibaca_pada). */
    public function tandaiDibaca(NotifikasiStok $notifikasi, User $user): void
    {
        if ($notifikasi->status !== 'baru') {
            return;
        }

        $notifikasi->update([
            'status' => 'dibaca',
            'dibaca_oleh' => $user->id_user,
            'dibaca_pada' => now(),
        ]);
    }

    /** @return int jumlah notifikasi yang ditandai */
    public function tandaiSemuaDibaca(User $user): int
    {
        return NotifikasiStok::where('status', 'baru')->update([
            'status' => 'dibaca',
            'dibaca_oleh' => $user->id_user,
            'dibaca_pada' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Data lonceng di header: jumlah notifikasi baru dan daftar singkat notifikasi terbuka (baru di atas).
     *
     * @return array{jumlahBaru: int, daftar: Collection<int, NotifikasiStok>}
     */
    public function ringkasan(int $batas = 8): array
    {
        return [
            'jumlahBaru' => NotifikasiStok::where('status', 'baru')->count(),
            'daftar' => NotifikasiStok::with('barang')
                ->whereIn('status', self::STATUS_TERBUKA)
                ->orderByRaw("status = 'baru' DESC")
                ->latest('created_at')
                ->latest('id_notifikasi')
                ->limit($batas)
                ->get(),
        ];
    }
}
