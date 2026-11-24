<?php

namespace App\Services;

use App\Models\Barang;
use App\Models\BarangMasuk;
use App\Models\Supplier;
use App\Models\User;
use App\Support\Uang;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Proses 3.0 Kelola Barang Masuk (rancangan bagian 3 "Simpan barang masuk").
 *
 * Semua langkah berada dalam satu DB::transaction: bila satu langkah gagal, tidak ada yang tersimpan.
 * Barang masuk tidak bisa diedit/dihapus setelah disimpan; kesalahan dikoreksi lewat stok opname.
 */
class BarangMasukService
{
    public function __construct(
        private readonly NomorOtomatisService $nomor,
        private readonly KartuStokService $kartuStok,
        private readonly KasService $kas,
        private readonly NotifikasiStokService $notifikasi,
    ) {}

    /**
     * @param  array{
     *     id_supplier: int, tanggal_masuk: string, no_faktur?: ?string, metode_pembayaran?: string, keterangan?: ?string,
     *     items: list<array{id_barang: int, jumlah: int, harga_beli: string|int, tanggal_kadaluarsa?: ?string}>
     * }  $data
     */
    public function simpan(array $data, User $user): BarangMasuk
    {
        return DB::transaction(function () use ($data, $user) {
            $items = $this->validasiItem($data['items'] ?? []);
            $supplier = $this->supplierAktif((int) $data['id_supplier']);
            $tanggal = Carbon::parse($data['tanggal_masuk']);

            // 1. Header (total diisi setelah semua detail dihitung).
            $barangMasuk = BarangMasuk::create([
                'kode_barang_masuk' => $this->nomor->barangMasuk($tanggal),
                'no_faktur' => $data['no_faktur'] ?? null,
                'id_supplier' => $supplier->id_supplier,
                'id_user' => $user->id_user,
                'tanggal_masuk' => $tanggal->toDateString(),
                'total_pembelian' => '0.00',
                'metode_pembayaran' => $data['metode_pembayaran'] ?? 'tunai',
                'keterangan' => $data['keterangan'] ?? null,
            ]);

            // 2. Kunci semua baris barang sekaligus, urut id, agar dua transaksi yang menyentuh
            //    barang yang sama tidak saling menunggu (deadlock).
            $daftarBarang = $this->kunciBarang(array_column($items, 'id_barang'));

            $total = '0.00';

            foreach ($items as $item) {
                $barang = $daftarBarang[$item['id_barang']];
                $jumlah = (int) $item['jumlah'];
                $hargaBeli = Uang::normal($item['harga_beli']);
                $subtotal = Uang::kali($jumlah, $hargaBeli);

                $barangMasuk->detail()->create([
                    'id_barang' => $barang->id_barang,
                    'jumlah' => $jumlah,
                    'harga_beli' => $hargaBeli,
                    'subtotal' => $subtotal,
                    'tanggal_kadaluarsa' => $item['tanggal_kadaluarsa'] ?? null,
                ]);

                // HPP rata-rata tertimbang, lalu stok bertambah. Kolom ini tidak fillable, diisi langsung.
                $barang->hpp_rata_rata = $this->hitungHppRataRata($barang->stok, $barang->hpp_rata_rata, $jumlah, $hargaBeli);
                $barang->harga_beli_terakhir = $hargaBeli;
                $barang->stok += $jumlah;
                $barang->save();

                // 3. Kartu stok jenis masuk (stok sebelum/sesudah dan hpp_satuan = HPP baru).
                $this->kartuStok->catat(
                    $barang,
                    KartuStokService::MASUK,
                    $jumlah,
                    0,
                    ['id_barang_masuk' => $barangMasuk->id_barang_masuk],
                    "Barang masuk {$barangMasuk->kode_barang_masuk} dari {$supplier->nama_supplier}",
                    $user,
                );

                // 4. Stok kembali di atas minimum → notifikasi terbuka ditutup.
                $this->notifikasi->tutupBilaAman($barang);

                $total = Uang::tambah($total, $subtotal);
            }

            if (Uang::banding($total, '0') <= 0) {
                throw ValidationException::withMessages(['items' => 'Total pembelian harus lebih dari Rp0.']);
            }

            $barangMasuk->total_pembelian = $total;
            $barangMasuk->save();

            // 5. Kas keluar kategori pembelian_barang. Tanggal kas = tanggal faktur dengan jam pencatatan.
            $this->kas->catat(
                KasService::KELUAR,
                'pembelian_barang',
                $total,
                ['id_barang_masuk' => $barangMasuk->id_barang_masuk],
                $user,
                $tanggal->copy()->setTimeFrom(now()),
                "Pembelian barang {$barangMasuk->kode_barang_masuk} ({$supplier->nama_supplier})",
            );

            return $barangMasuk->load('detail.barang', 'supplier', 'kas');
        });
    }

    /**
     * hpp_baru = (stok_lama × hpp_lama + jumlah × harga_beli) ÷ (stok_lama + jumlah), dibulatkan 2 desimal.
     * Contoh: stok 10 @ 3.000, masuk 20 @ 3.600 → (30.000 + 72.000) ÷ 30 = 3.400,00.
     */
    public function hitungHppRataRata(int $stokLama, string $hppLama, int $jumlah, string $hargaBeli): string
    {
        $nilaiLama = Uang::kali($stokLama, $hppLama);
        $nilaiMasuk = Uang::kali($jumlah, $hargaBeli);

        return Uang::bagi(Uang::tambah($nilaiLama, $nilaiMasuk), $stokLama + $jumlah);
    }

    /**
     * Pertahanan kedua setelah Form Request: minimal 1 item, jumlah > 0, harga ≥ 0, barang tidak duplikat.
     */
    private function validasiItem(array $items): array
    {
        if ($items === []) {
            throw ValidationException::withMessages(['items' => 'Minimal satu barang harus diisi.']);
        }

        $ids = array_map(fn ($item) => (int) ($item['id_barang'] ?? 0), $items);
        if (count($ids) !== count(array_unique($ids))) {
            throw ValidationException::withMessages(['items' => 'Barang yang sama tidak boleh diinput dua kali dalam satu faktur.']);
        }

        foreach ($items as $item) {
            if ((int) ($item['jumlah'] ?? 0) < 1) {
                throw ValidationException::withMessages(['items' => 'Jumlah setiap barang minimal 1.']);
            }
            if (! is_numeric($item['harga_beli'] ?? null) || Uang::banding(Uang::normal($item['harga_beli']), '0') < 0) {
                throw ValidationException::withMessages(['items' => 'Harga beli tidak boleh negatif.']);
            }
        }

        return array_values($items);
    }

    private function supplierAktif(int $idSupplier): Supplier
    {
        $supplier = Supplier::find($idSupplier);

        if (! $supplier || $supplier->status !== 'aktif') {
            throw ValidationException::withMessages(['id_supplier' => 'Supplier tidak ditemukan atau sudah nonaktif.']);
        }

        return $supplier;
    }

    /**
     * @return \Illuminate\Support\Collection<int, Barang> barang terkunci, key = id_barang
     */
    private function kunciBarang(array $ids)
    {
        $barang = Barang::whereIn('id_barang', $ids)
            ->orderBy('id_barang')
            ->lockForUpdate()
            ->get()
            ->keyBy('id_barang');

        foreach ($ids as $id) {
            if (! isset($barang[$id]) || $barang[$id]->status !== 'aktif') {
                throw ValidationException::withMessages(['items' => 'Ada barang yang tidak ditemukan atau sudah nonaktif.']);
            }
        }

        return $barang;
    }
}
