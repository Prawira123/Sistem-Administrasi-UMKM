# Modul 4: Barang Masuk dan HPP Rata-rata

**DFD:** proses 3.0 Kelola Barang Masuk. **Tabel:** `barang_masuk`, `detail_barang_masuk`, `barang`, `kartu_stok`, `kas`, `notifikasi_stok`.

## Tujuan
Admin atau Kasir mencatat faktur dari supplier. Stok bertambah, HPP rata-rata tertimbang dihitung ulang, kartu stok dan kas tercatat, semuanya dalam satu transaksi database.

## Yang dibuat
- `app/Services/KartuStokService.php`: satu-satunya tempat menulis `kartu_stok`. Method `catat(Barang $barang, string $jenis, int $masuk, int $keluar, array $sumber, string $keterangan, User $user)`. Validasi: `masuk` wajib `id_barang_masuk`, `keluar` wajib `id_penjualan`, `penyesuaian` wajib `id_penjualan` atau `id_stok_opname`. Lempar exception bila tidak cocok.
- `app/Services/KasService.php`: satu-satunya tempat menulis `kas`. Method `catat(...)` dengan validasi rancangan bagian 3: `penjualan` ⇒ `id_penjualan` dan jenis masuk; `pembelian_barang` ⇒ `id_barang_masuk` dan keluar; `operasional` ⇒ `id_pengeluaran` dan keluar; `modal` hanya masuk; `prive` hanya keluar; `jumlah > 0`.
- `app/Services/NomorOtomatisService.php`: `barangMasuk()` → `BM-YYYYMMDD-001`, juga `penjualan()` TRX-YYYYMMDD-0001, `pengeluaran()` OP-YYYYMMDD-01, `stokOpname()` SO-YYYYMM-01. Aman dari tabrakan (ambil nomor terakhir dengan `lockForUpdate` di dalam transaksi).
- `app/Services/BarangMasukService.php`: `simpan(array $data, User $user): BarangMasuk`.
- `BarangMasukController` (index, create, store, show), `StoreBarangMasukRequest`.
- View: daftar barang masuk (filter tanggal, supplier), form dengan baris item dinamis memakai Alpine.js (`x-data`, `x-for`: tambah/hapus baris, pilih barang dengan pencarian, jumlah, harga beli, kedaluwarsa opsional; subtotal dan total dihitung otomatis di browser, tetapi server tetap menghitung ulang), detail faktur.
- Update `BarangSeeder` atau buat `ContohTransaksiSeeder`: satu barang masuk contoh lewat `BarangMasukService` agar stok awal terisi dengan kartu stok yang benar.

## Aturan (wajib dalam `DB::transaction`)
1. Simpan header dan semua detail; `subtotal = jumlah × harga_beli`; `total_pembelian = Σ subtotal`. Minimal 1 item, barang tidak boleh duplikat dalam satu faktur, supplier harus aktif.
2. Per item, kunci baris barang (`lockForUpdate`), lalu:
   `hpp_baru = (stok_lama × hpp_lama + jumlah × harga_beli) ÷ (stok_lama + jumlah)`, dibulatkan 2 desimal.
   Set `stok += jumlah`, `hpp_rata_rata = hpp_baru`, `harga_beli_terakhir = harga_beli`.
3. Tulis kartu stok jenis `masuk` (`stok_sebelum`, `stok_sesudah`, `hpp_satuan = hpp_baru`, `id_barang_masuk`).
4. Bila `stok > stok_minimum`, notifikasi barang itu yang `baru`/`dibaca` diubah menjadi `selesai`.
5. Tulis kas `keluar`, kategori `pembelian_barang`, `jumlah = total_pembelian`.
- Perhitungan uang memakai string/`bcmath` atau integer rupiah, bukan float. Jelaskan pilihan di komentar kode.
- Barang masuk tidak bisa diedit/dihapus setelah disimpan (agar HPP dan kartu stok konsisten). Kesalahan diperbaiki lewat stok opname. Sebutkan ini di halaman detail.

## Contoh hitung untuk test
Stok 10, HPP 3.000. Masuk 20 @ 3.600 → HPP baru = (30.000 + 72.000) ÷ 30 = **3.400**, stok 30.

## Feature test (`tests/Feature/BarangMasukTest.php`)
1. Simpan barang masuk: stok bertambah, HPP sesuai contoh di atas, `harga_beli_terakhir` terisi.
2. Kartu stok jenis masuk tercatat dengan stok sebelum/sesudah benar.
3. Kas keluar kategori `pembelian_barang` sama dengan total.
4. Notifikasi stok minimum yang terbuka menjadi `selesai` bila stok naik di atas minimum.
5. Validasi: tanpa item ditolak, jumlah 0 ditolak, supplier nonaktif ditolak.
6. Kasir boleh mencatat barang masuk.
7. Unit test `KasService` dan `KartuStokService` menolak kombinasi referensi yang salah.

## Prompt untuk Claude Code
```
Baca CLAUDE.md, docs/rancangan.md bagian 3 (Simpan barang masuk, Validasi referensi, Penomoran otomatis)
dan docs/modul/04_barang_masuk.md. Kerjakan Modul 4:
1. KartuStokService, KasService (dengan validasi referensi), NomorOtomatisService.
2. BarangMasukService::simpan dalam satu DB::transaction dengan lockForUpdate, HPP rata-rata tertimbang,
   kartu stok, penutupan notifikasi, dan kas keluar. Jangan pakai float untuk uang.
3. Controller, Form Request, dan view barang masuk (Tailwind + komponen Blade modul 2) dengan baris item
   dinamis memakai Alpine.js; total dihitung ulang di server.
4. Seeder contoh transaksi barang masuk lewat service agar stok awal terisi.
5. Feature test BarangMasukTest dan unit test service sesuai file modul, termasuk contoh hitung HPP 3.400.
Jalankan php artisan migrate:fresh --seed dan php artisan test, centang Modul 4 di CLAUDE.md.
```
