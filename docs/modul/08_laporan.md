# Modul 8: Laporan

**DFD:** proses 7.0 Buat Laporan. **Tabel:** `kartu_stok`, `kas`, `penjualan`, `pengeluaran_operasional`, `hpp_periode`. **Hanya Administrator.**

## Tujuan
Laporan langsung dari data transaksi sesuai periode, bisa dicetak dan diunduh PDF. Ini menjawab masalah di Bab I (laporan manual butuh lebih dari tiga hari).

## Yang dibuat
- `app/Services/LaporanService.php` dengan method per laporan, mengembalikan array/DTO yang dipakai view, PDF, dan nanti AI Assistant (modul 9).
- `LaporanController`: halaman per laporan dengan filter periode, tombol Cetak (view print CSS) dan Unduh PDF (`barryvdh/laravel-dompdf`; sebutkan penambahan paket ini di CLAUDE.md).
- Kop laporan: nama toko, alamat, judul, periode, tanggal cetak, nama pencetak.
- **Catatan Tailwind:** halaman laporan di browser memakai Tailwind (dengan varian `print:` untuk tombol Cetak). Dompdf **tidak** mendukung Tailwind v4 (CSS modern), jadi view PDF (`resources/views/laporan/pdf/*.blade.php`) dibuat terpisah dengan CSS biasa yang ditulis inline di `<style>`, berbagi data dari LaporanService yang sama.

## Laporan
1. **Pergerakan Stok:** per barang (atau semua) dan periode; kolom tanggal, jenis, sumber (nomor BM/TRX/SO), masuk, keluar, saldo, HPP. Baris pembuka: stok awal periode.
2. **Arus Kas:** saldo awal (Σ masuk − Σ keluar sebelum periode), kas masuk per kategori, kas keluar per kategori, saldo akhir. Opsi rincian per transaksi.
3. **Laba Rugi bulanan (multi-step):**
   - Pendapatan penjualan = Σ `penjualan.total_harga` status selesai.
   - HPP = Σ `hpp_periode.total_hpp` bulan itu. Bila HPP periodik belum dihitung, tampilkan peringatan dan tautan ke halaman HPP (jangan diam-diam memakai angka lain).
   - Laba kotor = pendapatan − HPP.
   - Beban operasional per kategori pengeluaran.
   - Laba bersih = laba kotor − total beban.
   - Pembelian barang dan prive **tidak** masuk laba rugi.
4. **Penjualan:** rekap per hari dan per barang (terlaris), per metode pembayaran.
5. **Stok Minimum:** daftar barang aktif dengan stok ≤ minimum.
- Dashboard admin: grafik penjualan 7/30 hari (Chart.js dipasang lewat npm dan diimpor di `resources/js/app.js`, bukan CDN), laba bulan ini, saldo kas, 5 barang terlaris, jumlah stok menipis.

## Feature test (`tests/Feature/LaporanTest.php`)
1. Arus kas: saldo awal + masuk − keluar = saldo akhir dengan data uji.
2. Laba rugi memakai hpp_periode, mengabaikan penjualan batal, pembelian barang, dan prive.
3. Laba rugi menampilkan peringatan bila HPP bulan itu belum dihitung.
4. Pergerakan stok: saldo akhir sama dengan `barang.stok`.
5. PDF bisa diunduh (status 200, content-type application/pdf).
6. Kasir → 403.

## Prompt untuk Claude Code
```
Baca CLAUDE.md, docs/rancangan.md bagian 4, dan docs/modul/08_laporan.md. Kerjakan Modul 8:
1. LaporanService untuk pergerakan stok, arus kas, laba rugi bulanan multi-step, penjualan, dan stok minimum.
2. Halaman laporan (Tailwind) dengan filter periode, cetak (varian print:), dan unduh PDF
   (barryvdh/laravel-dompdf, catat di CLAUDE.md). View PDF terpisah dengan CSS inline biasa karena dompdf tidak mendukung Tailwind.
3. Laba rugi memakai hpp_periode dan memberi peringatan bila HPP belum dihitung.
4. Dashboard admin dengan grafik penjualan (Chart.js lewat npm) dan kartu ringkasan Tailwind.
5. Feature test LaporanTest dengan 6 skenario.
Jalankan php artisan test, centang Modul 8 di CLAUDE.md.
```
