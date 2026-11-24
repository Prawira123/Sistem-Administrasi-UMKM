# Modul 5: Transaksi Penjualan

**DFD:** proses 4.0 Transaksi Penjualan. **Tabel:** `penjualan`, `detail_penjualan`, `barang`, `kartu_stok`, `kas`, `notifikasi_stok`.

## Tujuan
Halaman kasir (POS) untuk mencari/memindai barang, menyimpan transaksi, mencetak nota, dan pembatalan oleh Administrator.

## Yang dibuat
- `app/Services/PenjualanService.php`: `simpan(array $items, string $metode, string $jumlahBayar, User $kasir): Penjualan` dan `batalkan(Penjualan $p, string $alasan, User $admin)`.
- `app/Services/NotifikasiStokService.php`: `periksa(Barang $barang)` untuk membuat notifikasi bila perlu, dan `tutupBilaAman(Barang $barang)` (pindahkan logika penutupan dari modul 4 ke sini dan pakai di kedua tempat).
- `PenjualanController`: `create` (halaman POS dua kolom dengan Tailwind: kiri pencarian/scan dan hasil, kanan keranjang dan pembayaran; state keranjang memakai Alpine.js, input scan selalu fokus, tombol cepat nominal bayar), `store`, `index` (riwayat, filter tanggal/kasir/status), `show`, `nota` (tampilan cetak ukuran struk 58/80 mm memakai `@page` di CSS dan varian `print:` Tailwind untuk menyembunyikan elemen non-nota), `batal` (admin).
- Endpoint pencarian barang JSON: `GET /barang/cari?q=` berdasarkan kode/barcode atau nama, hanya barang aktif dengan stok > 0.
- `StorePenjualanRequest`, `BatalPenjualanRequest` (alasan wajib).
- Lonceng notifikasi di header layout: jumlah notifikasi `baru`, daftar singkat, tandai dibaca (isi `dibaca_oleh`, `dibaca_pada`).

## Aturan simpan (dalam `DB::transaction`)
1. Kunci baris barang (`lockForUpdate`), urutkan berdasarkan `id_barang` agar tidak deadlock. Tolak bila barang nonaktif atau `jumlah > stok` dengan pesan "Stok {nama} tidak cukup (sisa {stok})."
2. Simpan header dan detail. Salin `harga_jual` dan `hpp_rata_rata` saat itu ke `detail_penjualan.harga_jual` dan `hpp_satuan`. `subtotal = jumlah × harga_jual`, `subtotal_hpp = jumlah × hpp_satuan`; header berisi `total_item`, `total_harga`, `total_hpp`.
3. Metode `tunai`: `jumlah_bayar >= total_harga`, `kembalian = jumlah_bayar − total_harga`. Metode `qris`/`transfer`: `jumlah_bayar = total_harga`, `kembalian = 0`.
4. Kurangi stok, tulis kartu stok `keluar` (`id_penjualan`).
5. Bila `stok <= stok_minimum` dan belum ada notifikasi `baru`/`dibaca` untuk barang itu, buat notifikasi: "Stok {nama} tinggal {stok} {satuan} (minimum {stok_minimum})."
6. Kas `masuk`, kategori `penjualan`, `jumlah = total_harga`.
7. Nomor `TRX-YYYYMMDD-0001` dari `NomorOtomatisService`; `tanggal_penjualan = now()` (WITA).

## Aturan batal (Admin, dalam `DB::transaction`)
1. Hanya transaksi `selesai`. Set `status = batal`, `keterangan = alasan`.
2. Kembalikan stok tiap item; kartu stok `penyesuaian` dengan `id_penjualan`, keterangan "Pembatalan {no_transaksi}". HPP rata-rata tidak diubah.
3. Hapus baris kas milik penjualan itu.
4. Jalankan `tutupBilaAman` untuk barang yang stoknya naik.

## Feature test (`tests/Feature/PenjualanTest.php`)
1. Penjualan mengurangi stok, kartu stok keluar tercatat, kas masuk sama dengan total.
2. Harga jual dan HPP tersalin ke detail; mengubah harga barang setelahnya tidak mengubah detail lama.
3. Jumlah melebihi stok ditolak dan tidak ada data yang tersimpan (rollback).
4. Tunai: bayar kurang ditolak; kembalian benar.
5. Notifikasi dibuat saat stok mencapai minimum, dan tidak dobel pada penjualan berikutnya.
6. Admin membatalkan: stok kembali, kas terhapus, status batal. Kasir membatalkan → 403.
7. Nomor transaksi berurutan per hari.

## Prompt untuk Claude Code
```
Baca CLAUDE.md, docs/rancangan.md bagian 3 (Simpan penjualan, Batalkan penjualan) dan
docs/modul/05_penjualan.md. Kerjakan Modul 5:
1. PenjualanService (simpan dan batalkan) dalam DB::transaction dengan lockForUpdate terurut,
   memakai KartuStokService, KasService, NomorOtomatisService dari modul 4.
2. NotifikasiStokService untuk membuat dan menutup notifikasi; refaktor BarangMasukService agar memakainya.
3. Halaman POS dengan Tailwind + Alpine.js: pencarian/scan barang (endpoint JSON), keranjang, hitung kembalian,
   nota cetak 58/80 mm (varian print: dan @page).
4. Riwayat penjualan dan pembatalan khusus Administrator dengan alasan wajib.
5. Lonceng notifikasi stok minimum di header layout (dropdown Alpine.js).
6. Feature test PenjualanTest dengan 7 skenario di file modul.
Jalankan php artisan test, centang Modul 5 di CLAUDE.md.
```
