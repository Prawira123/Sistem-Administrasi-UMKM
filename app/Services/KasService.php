<?php

namespace App\Services;

use App\Models\Kas;
use App\Models\PengeluaranOperasional;
use App\Models\Penjualan;
use App\Models\User;
use App\Support\Uang;
use Carbon\CarbonInterface;
use InvalidArgumentException;

/**
 * Satu-satunya tempat menulis tabel kas.
 *
 * Validasi referensi (rancangan bagian 3; di aplikasi karena MySQL tidak mengizinkan CHECK
 * pada kolom FK yang memakai CASCADE):
 *   penjualan        ⇒ jenis masuk,  id_penjualan wajib
 *   pembelian_barang ⇒ jenis keluar, id_barang_masuk wajib
 *   operasional      ⇒ jenis keluar, id_pengeluaran wajib
 *   modal            ⇒ jenis masuk,  tanpa referensi
 *   prive            ⇒ jenis keluar, tanpa referensi
 *   penyesuaian      ⇒ masuk/keluar, id_validasi_kas opsional
 *   jumlah selalu > 0 (arah ditentukan kolom jenis).
 */
class KasService
{
    public const MASUK = 'masuk';

    public const KELUAR = 'keluar';

    private const KOLOM_REFERENSI = ['id_penjualan', 'id_barang_masuk', 'id_pengeluaran', 'id_validasi_kas'];

    /**
     * Aturan per kategori: [jenis yang diizinkan, kolom referensi wajib, kolom referensi opsional].
     */
    private const ATURAN = [
        'penjualan' => [[self::MASUK], 'id_penjualan', null],
        'pembelian_barang' => [[self::KELUAR], 'id_barang_masuk', null],
        'operasional' => [[self::KELUAR], 'id_pengeluaran', null],
        'modal' => [[self::MASUK], null, null],
        'prive' => [[self::KELUAR], null, null],
        'penyesuaian' => [[self::MASUK, self::KELUAR], null, 'id_validasi_kas'],
    ];

    /**
     * @param  string  $jumlah  nominal positif sebagai string desimal (bukan float), mis. "102000.00"
     * @param  array{id_penjualan?: int, id_barang_masuk?: int, id_pengeluaran?: int, id_validasi_kas?: int}  $referensi
     */
    public function catat(
        string $jenis,
        string $kategori,
        string $jumlah,
        array $referensi,
        User $user,
        ?CarbonInterface $tanggal = null,
        ?string $keterangan = null,
    ): Kas {
        $referensi = $this->validasi($jenis, $kategori, $jumlah, $referensi);

        return Kas::create($referensi + [
            'tanggal' => $tanggal ?? now(),
            'jenis' => $jenis,
            'kategori' => $kategori,
            'jumlah' => Uang::normal($jumlah),
            'keterangan' => $keterangan !== null ? mb_substr($keterangan, 0, 255) : null,
            'id_user' => $user->id_user,
        ]);
    }

    /** Kategori yang dicatat manual oleh Administrator dari buku kas (modul 7). */
    public const KATEGORI_MANUAL = ['modal' => self::MASUK, 'prive' => self::KELUAR];

    /**
     * Ubah nominal/tanggal/keterangan baris kas yang sudah ada (mis. pengeluaran operasional diedit).
     * Jenis, kategori, dan referensi tidak berubah; aturan validasi yang sama tetap dijalankan.
     */
    public function perbarui(Kas $kas, string $jumlah, ?CarbonInterface $tanggal = null, ?string $keterangan = null): Kas
    {
        $referensi = $kas->only(self::KOLOM_REFERENSI);
        $this->validasi($kas->jenis, $kas->kategori, $jumlah, array_filter($referensi, fn ($id) => $id !== null));

        $kas->jumlah = Uang::normal($jumlah);
        if ($tanggal) {
            $kas->tanggal = $tanggal;
        }
        if ($keterangan !== null) {
            $kas->keterangan = mb_substr($keterangan, 0, 255);
        }
        $kas->save();

        return $kas;
    }

    /** Pengeluaran operasional dihapus: baris kasnya ikut dihapus (eksplisit, selain CASCADE di database). */
    public function hapusMilikPengeluaran(PengeluaranOperasional $pengeluaran): int
    {
        return Kas::where('id_pengeluaran', $pengeluaran->id_pengeluaran)
            ->where('kategori', 'operasional')
            ->delete();
    }

    /** Hanya baris modal/prive (input manual) yang boleh dihapus dari buku kas. */
    public function hapusManual(Kas $kas): void
    {
        if (! array_key_exists($kas->kategori, self::KATEGORI_MANUAL)) {
            throw new InvalidArgumentException('Hanya kas modal atau prive yang bisa dihapus dari buku kas; kas lain mengikuti transaksinya.');
        }

        $kas->delete();
    }

    /** Saldo kas sebelum waktu tertentu: Σ masuk − Σ keluar (saldo awal buku kas dan laporan arus kas). */
    public function saldoSebelum(CarbonInterface $waktu): string
    {
        $baris = Kas::where('tanggal', '<', $waktu)
            ->selectRaw("COALESCE(SUM(CASE WHEN jenis = 'masuk' THEN jumlah ELSE 0 END), 0) AS masuk")
            ->selectRaw("COALESCE(SUM(CASE WHEN jenis = 'keluar' THEN jumlah ELSE 0 END), 0) AS keluar")
            ->first();

        return Uang::kurang(Uang::normal($baris->masuk), Uang::normal($baris->keluar));
    }

    /**
     * Pembatalan penjualan (rancangan bagian 3): baris kas milik penjualan itu dihapus,
     * sehingga saldo kas kembali seperti sebelum transaksi.
     *
     * @return int jumlah baris kas yang dihapus
     */
    public function hapusMilikPenjualan(Penjualan $penjualan): int
    {
        return Kas::where('id_penjualan', $penjualan->id_penjualan)
            ->where('kategori', 'penjualan')
            ->delete();
    }

    /**
     * Kembalikan semua kolom referensi (yang tidak dipakai bernilai null) atau lempar exception.
     */
    public function validasi(string $jenis, string $kategori, string $jumlah, array $referensi): array
    {
        if (! isset(self::ATURAN[$kategori])) {
            throw new InvalidArgumentException("Kategori kas '{$kategori}' tidak valid.");
        }

        [$jenisBoleh, $wajib, $opsional] = self::ATURAN[$kategori];

        if (! in_array($jenis, $jenisBoleh, true)) {
            throw new InvalidArgumentException("Kas kategori '{$kategori}' harus berjenis ".implode('/', $jenisBoleh).'.');
        }

        if (! is_numeric($jumlah) || Uang::banding(Uang::normal($jumlah), '0') <= 0) {
            throw new InvalidArgumentException('Jumlah kas harus lebih dari 0.');
        }

        $tidakDikenal = array_diff(array_keys($referensi), self::KOLOM_REFERENSI);
        if ($tidakDikenal) {
            throw new InvalidArgumentException('Referensi kas tidak dikenal: '.implode(', ', $tidakDikenal).'.');
        }

        $terisi = array_keys(array_filter($referensi, fn ($id) => $id !== null));

        if ($wajib !== null && ! in_array($wajib, $terisi, true)) {
            throw new InvalidArgumentException("Kas kategori '{$kategori}' wajib mengisi {$wajib}.");
        }

        $lain = array_diff($terisi, array_filter([$wajib, $opsional]));
        if ($lain) {
            throw new InvalidArgumentException("Kas kategori '{$kategori}' tidak boleh mengisi ".implode(', ', $lain).'.');
        }

        // $referensi di kiri: operator + mempertahankan nilai sisi kiri, kolom lain diisi null.
        return $referensi + array_fill_keys(self::KOLOM_REFERENSI, null);
    }
}
