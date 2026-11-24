# Modul 3: Data Master

**DFD:** proses 2.0 Kelola Data Master. **Tabel:** `kategori_barang`, `supplier`, `barang`, `users`.

## Tujuan
CRUD data master dengan hak akses berbeda per role, plus dashboard ringkas.

## Hak akses
| Data | Administrator | Kasir |
|---|---|---|
| Kategori barang | CRUD | Tidak |
| Supplier | CRUD (nonaktifkan, bukan hapus, bila sudah dipakai) | Tidak |
| Barang | CRUD | Tambah dan ubah, **tanpa** `harga_jual` dan tanpa hapus |
| Pengguna | CRUD, reset password, aktif/nonaktif | Tidak |

## Yang dibuat
- Controller resource: `KategoriBarangController`, `SupplierController`, `BarangController`, `UserController` (folder `app/Http/Controllers/Master/`).
- Form Request untuk store dan update masing-masing (pesan bahasa Indonesia), misalnya `StoreBarangRequest`, `UpdateBarangRequest`.
- Policy `BarangPolicy` (`update`, `delete`, `ubahHargaJual`) atau pakai Gate dari modul 2.
- View (Tailwind, memakai komponen Blade dari modul 2; paginasi `links()` Tailwind bawaan Laravel): index (tabel, pencarian, paginasi 10), create, edit. Barang menampilkan stok, stok minimum, HPP rata-rata, harga jual, dan badge "Stok menipis" bila `stok <= stok_minimum`.
- Dashboard: jumlah barang aktif, jumlah barang stok menipis, (admin) penjualan hari ini dan saldo kas. Angka penjualan/kas boleh 0 dulu, query diisi di modul 5 dan 7.

## Aturan
- **Stok tidak ada di form barang.** Barang baru stok 0, `hpp_rata_rata` 0. Stok hanya berubah lewat barang masuk, penjualan, pembatalan, dan opname final.
- Kasir: input `harga_jual` tidak tampil dan diabaikan di server walaupun dikirim (validasi server, bukan hanya disembunyikan di view). Barang baru dari kasir memakai `harga_jual = 0` dan muncul tanda "Harga belum diatur" untuk admin.
- Kode otomatis: `kode_supplier` SUP-001, SUP-002, ...; `kode_barang` boleh diisi barcode, bila kosong otomatis BRG-0001.
- Hapus barang/supplier/kategori yang sudah dipakai transaksi akan ditolak FK (RESTRICT). Tangkap `QueryException`, tampilkan "Data sudah dipakai transaksi, nonaktifkan saja."
- Pengguna: admin tidak bisa menonaktifkan atau menghapus akunnya sendiri; username unik; password minimal 8 karakter, di-hash lewat cast `hashed`.

## Penyesuaian dengan repo
- Hapus `stok`, `hpp_rata_rata`, dan `harga_beli_terakhir` dari `$fillable` model `Barang`. Service (modul 4 dan seterusnya) mengisinya langsung, misalnya `$barang->stok = $baru; $barang->save();`. Factory tetap bisa mengisinya karena factory tidak terikat `$fillable`.
- Buat `KategoriBarangFactory`, `SupplierFactory`, dan `BarangFactory` (stok default 0, dengan state `stok(int $stok, ?float $hpp)` untuk test), lalu tambahkan `use HasFactory` di model terkait.
- Controller memakai `$request->validated()`, jangan `$request->all()`.

## Feature test (`tests/Feature/MasterDataTest.php`)
1. Admin bisa membuat kategori, supplier, barang, pengguna.
2. Kasir bisa menambah barang, tapi `harga_jual` yang dikirim diabaikan (tetap 0).
3. Kasir mengubah barang: `harga_jual` tidak berubah.
4. Kasir tidak bisa menghapus barang (403) dan tidak bisa membuka halaman supplier (403).
5. Mengirim `stok` lewat form barang tidak mengubah stok.
6. Hapus kategori yang masih punya barang ditolak dengan pesan.

## Prompt untuk Claude Code
```
Baca CLAUDE.md, docs/rancangan.md (bagian 1 dan struktur tabel barang, supplier, kategori_barang, users),
dan docs/modul/03_master_data.md. Kerjakan Modul 3:
1. CRUD kategori barang, supplier, barang, dan pengguna dengan Form Request dan pesan bahasa Indonesia.
2. Hak akses persis tabel di file modul; batasan kasir (harga_jual, hapus) ditegakkan di server.
3. Stok tidak boleh ada di form dan tidak boleh berubah lewat CRUD.
4. Kode otomatis SUP-001 dan BRG-0001, penanganan FK RESTRICT dengan pesan ramah.
5. Dashboard ringkas (kartu statistik Tailwind) dan badge stok menipis memakai komponen x-badge.
   Semua view memakai komponen Blade dari modul 2, bukan class Tailwind yang ditulis ulang.
6. Bagian "Penyesuaian dengan repo": keluarkan stok/hpp_rata_rata/harga_beli_terakhir dari $fillable Barang,
   buat factory KategoriBarang, Supplier, Barang.
7. Feature test MasterDataTest dengan 6 skenario di file modul.
Jalankan php artisan test, centang Modul 3 di CLAUDE.md, lalu ringkas perubahan.
```
