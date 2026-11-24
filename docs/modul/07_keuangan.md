# Modul 7: Pengelolaan Keuangan

**DFD:** proses 6.0 Kelola Keuangan. **Tabel:** `kategori_pengeluaran`, `pengeluaran_operasional`, `kas`, `validasi_kas`, `hpp_periode`. **Hanya Administrator.**

## Tujuan
Mencatat beban operasional, modal dan prive, memvalidasi kas harian, dan menghitung HPP periodik per bulan.

## Yang dibuat
- `KategoriPengeluaranController` (CRUD sederhana).
- `PengeluaranController` + `PengeluaranService`: simpan pengeluaran (`OP-YYYYMMDD-01`), upload bukti foto opsional ke `storage/app/public/bukti` (jpg/png/pdf, maks 2 MB), lalu kas `keluar` kategori `operasional` dengan `id_pengeluaran`. Ubah/hapus pengeluaran ikut memperbarui/menghapus baris kas (CASCADE untuk hapus; update jumlah lewat service).
- `KasController`: buku kas (filter periode, kategori), form modal (kas masuk) dan prive (kas keluar), saldo berjalan.
- `ValidasiKasController` + `ValidasiKasService`: satu per tanggal. `total_tunai_sistem = Σ penjualan.total_harga` dengan metode `tunai` dan status `selesai` pada tanggal itu; admin input `uang_fisik`; `selisih = uang_fisik − total_tunai_sistem`; status `sesuai` bila 0, selain itu `selisih` + catatan wajib dan kas `penyesuaian` (masuk bila lebih, keluar bila kurang, `jumlah = |selisih|`, `id_validasi_kas`).
- `HppPeriodeController` + `HppService::hitungBulan(int $tahun, int $bulan, User $admin)`:
  - Untuk setiap barang: persediaan awal = `stok_sesudah` dan `hpp_satuan` dari mutasi kartu stok terakhir **sebelum** tanggal 1; nilai = qty × hpp. Bila belum ada mutasi, 0.
  - Pembelian = Σ `detail_barang_masuk` (jumlah, subtotal) dalam periode.
  - Persediaan akhir = mutasi terakhir **sampai** akhir bulan.
  - `total_hpp = nilai_awal + nilai_pembelian − nilai_akhir`; `qty_terjual` = Σ detail penjualan berstatus selesai; `hpp_per_unit = total_hpp ÷ qty_terjual` (0 bila tidak ada penjualan).
  - Simpan ke `hpp_periode` (updateOrCreate per barang + periode). Bulan yang sudah dihitung bisa dihitung ulang dengan konfirmasi.
- Halaman HPP: pilih bulan, tombol hitung, tabel hasil per barang + total.
- Semua halaman memakai Tailwind dan komponen Blade modul 2; nominal rata kanan dengan format Rupiah, kas masuk hijau dan keluar merah, konfirmasi hapus memakai x-modal.

## Aturan
- Kas selalu ditulis lewat `KasService` dari modul 4.
- Pembelian barang dan prive bukan beban (hanya memengaruhi kas, tidak masuk laba rugi).
- Validasi kas tidak bisa dibuat untuk tanggal yang akan datang.

## Contoh hitung HPP periodik untuk test
Awal: 10 unit × 3.000 = 30.000. Pembelian: 20 unit, 72.000. Akhir: 12 unit × 3.400 = 40.800.
HPP = 30.000 + 72.000 − 40.800 = **61.200**; terjual 18 → 3.400 per unit.

## Feature test (`tests/Feature/KeuanganTest.php`)
1. Pengeluaran membuat kas keluar kategori operasional; hapus pengeluaran menghapus kas.
2. Modal tercatat sebagai kas masuk, prive kas keluar; KasService menolak prive jenis masuk.
3. Validasi kas: sesuai bila sama; selisih membuat kas penyesuaian dengan arah benar; tanggal ganda ditolak.
4. HPP periodik sesuai contoh hitung 61.200.
5. Kasir membuka halaman keuangan → 403.

## Prompt untuk Claude Code
```
Baca CLAUDE.md, docs/rancangan.md bagian 3 (Keuangan) dan bagian 4, serta docs/modul/07_keuangan.md.
Kerjakan Modul 7 (hanya Administrator):
1. Kategori pengeluaran dan pengeluaran operasional (upload bukti opsional) yang otomatis menulis kas.
2. Buku kas dengan saldo berjalan, input modal dan prive.
3. Validasi kas harian (satu per tanggal) dengan kas penyesuaian bila ada selisih.
4. HppService::hitungBulan dengan rumus Persediaan Awal + Pembelian − Persediaan Akhir dari kartu stok,
   disimpan ke hpp_periode.
5. Semua kas lewat KasService. Feature test KeuanganTest sesuai file modul, termasuk contoh HPP 61.200.
Jalankan php artisan test, centang Modul 7 di CLAUDE.md.
```
