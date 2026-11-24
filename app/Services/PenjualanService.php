<?php

namespace App\Services;

use App\Models\Barang;
use App\Models\Penjualan;
use App\Models\User;
use App\Support\Format;
use App\Support\Uang;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Proses 4.0 Transaksi Penjualan (rancangan bagian 3 "Simpan penjualan" dan "Batalkan penjualan").
 *
 * Semua langkah berada dalam satu DB::transaction: bila satu langkah gagal (mis. stok tidak cukup),
 * tidak ada yang tersimpan. Baris barang dikunci (lockForUpdate) dan diurutkan berdasarkan id_barang
 * agar dua kasir yang menjual barang yang sama tidak bisa membuat stok negatif dan tidak saling deadlock.
 */
class PenjualanService
{
    public const METODE = ['tunai', 'qris', 'transfer'];

    public const PESAN_BULAN_LALU = 'Transaksi bulan sebelumnya tidak dapat dibatalkan karena laporan periode tersebut sudah berjalan.';

    public function __construct(
        private readonly NomorOtomatisService $nomor,
        private readonly KartuStokService $kartuStok,
        private readonly KasService $kas,
        private readonly NotifikasiStokService $notifikasi,
    ) {}

    /**
     * @param  list<array{id_barang: int, jumlah: int}>  $items
     * @param  string  $jumlahBayar  uang dari pelanggan (string desimal); diabaikan untuk qris/transfer
     */
    public function simpan(array $items, string $metode, string $jumlahBayar, User $kasir): Penjualan
    {
        if (! in_array($metode, self::METODE, true)) {
            throw ValidationException::withMessages(['metode_pembayaran' => 'Metode pembayaran tidak valid.']);
        }

        $items = $this->gabungItem($items);

        return DB::transaction(function () use ($items, $metode, $jumlahBayar, $kasir) {
            // 1. Kunci baris barang (urut id) lalu tolak bila nonaktif atau jumlah > stok.
            $daftarBarang = $this->kunciBarang(array_keys($items));

            foreach ($items as $idBarang => $jumlah) {
                $barang = $daftarBarang[$idBarang];
                if ($barang->status !== 'aktif') {
                    throw ValidationException::withMessages(['items' => "Barang {$barang->nama_barang} sudah nonaktif."]);
                }
                if ($jumlah > $barang->stok) {
                    throw ValidationException::withMessages(['items' => "Stok {$barang->nama_barang} tidak cukup (sisa {$barang->stok})."]);
                }
            }

            // 2. Hitung total dari harga jual dan HPP rata-rata SAAT INI (nanti disalin ke detail).
            $totalItem = 0;
            $totalHarga = '0.00';
            $totalHpp = '0.00';
            $rincian = [];

            foreach ($items as $idBarang => $jumlah) {
                $barang = $daftarBarang[$idBarang];
                $hargaJual = Uang::normal($barang->harga_jual);
                $hppSatuan = Uang::normal($barang->hpp_rata_rata);
                $subtotal = Uang::kali($jumlah, $hargaJual);
                $subtotalHpp = Uang::kali($jumlah, $hppSatuan);

                $rincian[] = [
                    'barang' => $barang,
                    'jumlah' => $jumlah,
                    'harga_jual' => $hargaJual,
                    'hpp_satuan' => $hppSatuan,
                    'subtotal' => $subtotal,
                    'subtotal_hpp' => $subtotalHpp,
                ];

                $totalItem += $jumlah;
                $totalHarga = Uang::tambah($totalHarga, $subtotal);
                $totalHpp = Uang::tambah($totalHpp, $subtotalHpp);
            }

            if (Uang::banding($totalHarga, '0') <= 0) {
                throw ValidationException::withMessages(['items' => 'Total penjualan harus lebih dari Rp0.']);
            }

            // 3. Pembayaran: tunai boleh lebih (ada kembalian), qris/transfer selalu pas.
            [$bayar, $kembalian] = $this->hitungPembayaran($metode, $jumlahBayar, $totalHarga);

            // 7. Nomor TRX-YYYYMMDD-0001 (dikunci di dalam transaksi), tanggal = sekarang (WITA).
            $waktu = now();
            $penjualan = Penjualan::create([
                'no_transaksi' => $this->nomor->penjualan($waktu),
                'id_user' => $kasir->id_user,
                'tanggal_penjualan' => $waktu,
                'total_item' => $totalItem,
                'total_harga' => $totalHarga,
                'total_hpp' => $totalHpp,
                'metode_pembayaran' => $metode,
                'jumlah_bayar' => $bayar,
                'kembalian' => $kembalian,
                'status' => 'selesai',
            ]);

            foreach ($rincian as $baris) {
                /** @var Barang $barang */
                $barang = $baris['barang'];

                $penjualan->detail()->create([
                    'id_barang' => $barang->id_barang,
                    'jumlah' => $baris['jumlah'],
                    'harga_jual' => $baris['harga_jual'],
                    'hpp_satuan' => $baris['hpp_satuan'],
                    'subtotal' => $baris['subtotal'],
                    'subtotal_hpp' => $baris['subtotal_hpp'],
                ]);

                // 4. Kurangi stok lalu tulis kartu stok jenis keluar.
                $barang->stok -= $baris['jumlah'];
                $barang->save();

                $this->kartuStok->catat(
                    $barang,
                    KartuStokService::KELUAR,
                    0,
                    $baris['jumlah'],
                    ['id_penjualan' => $penjualan->id_penjualan],
                    "Penjualan {$penjualan->no_transaksi}",
                    $kasir,
                );

                // 5. Stok sampai batas minimum → notifikasi (tidak dobel bila masih ada yang terbuka).
                $this->notifikasi->periksa($barang);
            }

            // 6. Kas masuk kategori penjualan sebesar total_harga.
            $this->kas->catat(
                KasService::MASUK,
                'penjualan',
                $totalHarga,
                ['id_penjualan' => $penjualan->id_penjualan],
                $kasir,
                $waktu,
                "Penjualan {$penjualan->no_transaksi} ({$metode})",
            );

            return $penjualan->load('detail.barang', 'user', 'kas');
        });
    }

    /**
     * Pembatalan oleh Administrator: status batal, stok dikembalikan (kartu stok penyesuaian),
     * baris kas dihapus, notifikasi ditutup bila stok sudah aman. HPP rata-rata tidak diubah.
     */
    public function batalkan(Penjualan $penjualan, string $alasan, User $admin): Penjualan
    {
        if (! $admin->isAdministrator()) {
            throw new AuthorizationException('Hanya Administrator yang dapat membatalkan penjualan.');
        }

        $alasan = trim($alasan);
        if ($alasan === '') {
            throw ValidationException::withMessages(['alasan' => 'Alasan pembatalan wajib diisi.']);
        }

        return DB::transaction(function () use ($penjualan, $alasan, $admin) {
            // Kunci header agar transaksi yang sama tidak dibatalkan dua kali secara bersamaan.
            $penjualan = Penjualan::whereKey($penjualan->getKey())->lockForUpdate()->firstOrFail();

            // 1. Hanya transaksi selesai.
            if ($penjualan->status !== 'selesai') {
                throw ValidationException::withMessages(['alasan' => "Transaksi {$penjualan->no_transaksi} sudah dibatalkan sebelumnya."]);
            }

            // Hanya transaksi bulan berjalan (laporan periode sebelumnya tidak boleh berubah).
            if (! $penjualan->isBulanBerjalan()) {
                throw ValidationException::withMessages([
                    'alasan' => self::PESAN_BULAN_LALU,
                ]);
            }

            $penjualan->status = 'batal';
            $penjualan->keterangan = mb_substr($alasan, 0, 255);
            $penjualan->save();

            $detail = $penjualan->detail()->get();
            $daftarBarang = $this->kunciBarang($detail->pluck('id_barang')->unique()->all());

            foreach ($detail as $baris) {
                $barang = $daftarBarang[$baris->id_barang];

                // 2. Kembalikan stok; kartu stok penyesuaian dengan id_penjualan.
                $barang->stok += $baris->jumlah;
                $barang->save();

                $this->kartuStok->catat(
                    $barang,
                    KartuStokService::PENYESUAIAN,
                    $baris->jumlah,
                    0,
                    ['id_penjualan' => $penjualan->id_penjualan],
                    "Pembatalan {$penjualan->no_transaksi}",
                    $admin,
                );

                // 4. Stok naik di atas minimum → notifikasi terbuka ditutup.
                $this->notifikasi->tutupBilaAman($barang);
            }

            // 3. Hapus baris kas milik penjualan itu.
            $this->kas->hapusMilikPenjualan($penjualan);

            return $penjualan->load('detail.barang', 'user');
        });
    }

    /**
     * @return array{0: string, 1: string} [jumlah_bayar, kembalian]
     */
    public function hitungPembayaran(string $metode, string $jumlahBayar, string $totalHarga): array
    {
        if ($metode !== 'tunai') {
            return [$totalHarga, '0.00'];
        }

        if (! is_numeric($jumlahBayar)) {
            throw ValidationException::withMessages(['jumlah_bayar' => 'Jumlah bayar harus berupa angka.']);
        }

        $bayar = Uang::normal($jumlahBayar);

        if (Uang::banding($bayar, $totalHarga) < 0) {
            throw ValidationException::withMessages([
                'jumlah_bayar' => 'Jumlah bayar kurang dari total belanja ('.Format::rupiah($totalHarga).').',
            ]);
        }

        return [$bayar, Uang::kurang($bayar, $totalHarga)];
    }

    /**
     * Item dengan barang yang sama digabung (mis. barang dipindai dua kali). Jumlah harus bilangan bulat > 0.
     *
     * @return array<int, int> id_barang => jumlah
     */
    private function gabungItem(array $items): array
    {
        if ($items === []) {
            throw ValidationException::withMessages(['items' => 'Keranjang masih kosong.']);
        }

        $hasil = [];
        foreach ($items as $item) {
            $id = (int) ($item['id_barang'] ?? 0);
            $jumlah = filter_var($item['jumlah'] ?? null, FILTER_VALIDATE_INT);

            if ($id < 1 || $jumlah === false || $jumlah < 1) {
                throw ValidationException::withMessages(['items' => 'Setiap barang harus dipilih dengan jumlah minimal 1.']);
            }

            $hasil[$id] = ($hasil[$id] ?? 0) + $jumlah;
        }

        return $hasil;
    }

    /**
     * @return Collection<int, Barang> barang terkunci, key = id_barang
     */
    private function kunciBarang(array $ids): Collection
    {
        $barang = Barang::whereIn('id_barang', $ids)
            ->orderBy('id_barang')
            ->lockForUpdate()
            ->get()
            ->keyBy('id_barang');

        foreach ($ids as $id) {
            if (! isset($barang[$id])) {
                throw ValidationException::withMessages(['items' => 'Ada barang yang tidak ditemukan.']);
            }
        }

        return $barang;
    }
}
