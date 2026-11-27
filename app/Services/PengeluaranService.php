<?php

namespace App\Services;

use App\Models\PengeluaranOperasional;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Proses 6.0: pengeluaran operasional (beban). Setiap pengeluaran punya tepat satu baris kas
 * keluar kategori operasional (id_pengeluaran), ditulis/diubah/dihapus lewat KasService.
 *
 * Bukti (foto/PDF nota) disimpan di disk "public" folder "bukti" (storage/app/public/bukti),
 * ditampilkan lewat route khusus Administrator, bukan link publik.
 */
class PengeluaranService
{
    public const DISK = 'public';

    public const FOLDER_BUKTI = 'bukti';

    public const PESAN_BULAN_LALU = 'Pengeluaran bulan sebelumnya tidak dapat diubah atau dihapus karena laporan periode tersebut sudah berjalan.';

    public function __construct(
        private readonly NomorOtomatisService $nomor,
        private readonly KasService $kas,
    ) {}

    /**
     * @param  array{id_kategori_pengeluaran: int, tanggal_pengeluaran: string, deskripsi: string, jumlah: string, metode_pembayaran: string}  $data
     */
    public function simpan(array $data, ?UploadedFile $bukti, User $admin): PengeluaranOperasional
    {
        $path = $bukti?->store(self::FOLDER_BUKTI, self::DISK);

        try {
            return DB::transaction(function () use ($data, $path, $admin) {
                $tanggal = Carbon::parse($data['tanggal_pengeluaran']);

                $pengeluaran = PengeluaranOperasional::create([
                    'kode_pengeluaran' => $this->nomor->pengeluaran($tanggal),
                    'id_kategori_pengeluaran' => $data['id_kategori_pengeluaran'],
                    'id_user' => $admin->id_user,
                    'tanggal_pengeluaran' => $tanggal->toDateString(),
                    'deskripsi' => $data['deskripsi'],
                    'jumlah' => $data['jumlah'],
                    'metode_pembayaran' => $data['metode_pembayaran'],
                    'bukti' => $path,
                ]);

                $this->kas->catat(
                    KasService::KELUAR,
                    'operasional',
                    (string) $data['jumlah'],
                    ['id_pengeluaran' => $pengeluaran->id_pengeluaran],
                    $admin,
                    $tanggal->copy()->setTimeFrom(now()),
                    $this->keteranganKas($pengeluaran),
                );

                return $pengeluaran->load('kas', 'kategori');
            });
        } catch (\Throwable $e) {
            // Transaksi gagal → file yang sudah terunggah dibuang agar tidak menjadi sampah.
            if ($path) {
                Storage::disk(self::DISK)->delete($path);
            }
            throw $e;
        }
    }

    /**
     * Ubah pengeluaran; baris kasnya ikut diperbarui (jumlah, tanggal, keterangan) lewat KasService.
     * $bukti baru menggantikan file lama; $hapusBukti = true membuang bukti tanpa pengganti.
     */
    public function perbarui(PengeluaranOperasional $pengeluaran, array $data, ?UploadedFile $bukti, bool $hapusBukti = false): PengeluaranOperasional
    {
        $this->pastikanBulanBerjalan($pengeluaran); // dicek dari tanggal lama, sebelum diisi data baru
        $lama = $pengeluaran->bukti;
        $baru = $bukti?->store(self::FOLDER_BUKTI, self::DISK);

        try {
            DB::transaction(function () use ($pengeluaran, $data, $baru, $hapusBukti, $lama) {
                $tanggal = Carbon::parse($data['tanggal_pengeluaran']);

                $pengeluaran->fill([
                    'id_kategori_pengeluaran' => $data['id_kategori_pengeluaran'],
                    'tanggal_pengeluaran' => $tanggal->toDateString(),
                    'deskripsi' => $data['deskripsi'],
                    'jumlah' => $data['jumlah'],
                    'metode_pembayaran' => $data['metode_pembayaran'],
                    'bukti' => $baru ?? ($hapusBukti ? null : $lama),
                ])->save();

                $kas = $pengeluaran->kas()->firstOrFail();
                $this->kas->perbarui(
                    $kas,
                    (string) $data['jumlah'],
                    $tanggal->copy()->setTimeFrom($kas->tanggal),
                    $this->keteranganKas($pengeluaran->load('kategori')),
                );
            });
        } catch (\Throwable $e) {
            if ($baru) {
                Storage::disk(self::DISK)->delete($baru);
            }
            throw $e;
        }

        // File lama dihapus setelah transaksi berhasil.
        if ($lama && $lama !== $pengeluaran->bukti) {
            Storage::disk(self::DISK)->delete($lama);
        }

        return $pengeluaran->load('kas', 'kategori');
    }

    public function hapus(PengeluaranOperasional $pengeluaran): void
    {
        $this->pastikanBulanBerjalan($pengeluaran);
        $bukti = $pengeluaran->bukti;

        DB::transaction(function () use ($pengeluaran) {
            $this->kas->hapusMilikPengeluaran($pengeluaran);
            $pengeluaran->delete();
        });

        if ($bukti) {
            Storage::disk(self::DISK)->delete($bukti);
        }
    }

    /** Pengeluaran dengan tanggal sebelum awal bulan berjalan terkunci: laporan periode itu sudah berjalan. */
    private function pastikanBulanBerjalan(PengeluaranOperasional $pengeluaran): void
    {
        if (! $pengeluaran->isBulanBerjalan()) {
            throw ValidationException::withMessages(['tanggal_pengeluaran' => self::PESAN_BULAN_LALU]);
        }
    }

    private function keteranganKas(PengeluaranOperasional $p): string
    {
        return "Pengeluaran {$p->kode_pengeluaran} ({$p->kategori?->nama_kategori}): {$p->deskripsi}";
    }
}
