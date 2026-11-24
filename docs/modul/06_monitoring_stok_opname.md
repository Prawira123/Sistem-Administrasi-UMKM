# Modul 6: Monitoring Stok dan Stok Opname

**DFD:** proses 5.0 Monitoring Stok. **Tabel:** `barang`, `notifikasi_stok`, `stok_opname`, `detail_stok_opname`, `kartu_stok`.

## Tujuan
Admin dan Kasir bisa mengecek stok real-time, melihat peringatan stok minimum, dan menginput stok opname. Penyesuaian stok baru berlaku setelah disetujui Administrator.

## Yang dibuat
- `MonitoringStokController`: halaman cek stok (filter kategori, status stok: aman/menipis/habis, pencarian), halaman peringatan stok minimum (`barang.status='aktif' AND stok <= stok_minimum`), dan riwayat kartu stok per barang.
- `app/Services/StokOpnameService.php`: `buatDraft`, `simpanHitungan`, `ajukan`, `setujui`, `tolak` (opsional, kembali ke draft dengan catatan).
- `StokOpnameController`: index, create (pilih semua barang aktif atau per kategori), edit (input stok fisik), show, ajukan, setujui (admin).
- Form Request untuk input hitungan.
- UI Tailwind dengan komponen Blade modul 2: badge warna status stok (aman hijau, menipis kuning, habis merah), form opname berupa tabel dengan input stok fisik per baris dan kolom selisih yang langsung terhitung (Alpine.js), baris berselisih diberi warna.

## Aturan
1. Draft dibuat oleh Kasir/Admin, `kode_opname` SO-YYYYMM-01. Saat draft dibuat, `stok_sistem` diambil dari stok terkini.
2. Input `stok_fisik >= 0` per barang; `selisih = stok_fisik − stok_sistem`; keterangan wajib bila selisih ≠ 0 (rusak, hilang, kedaluwarsa, salah catat).
3. `draft` → `diajukan` (oleh pembuat). Setelah diajukan, data tidak bisa diedit kecuali ditolak.
4. Admin menyetujui (dalam `DB::transaction`): isi `disetujui_oleh` (relasi `approver()` di model `StokOpname`), `disetujui_pada`, status `final`. Untuk setiap barang dengan selisih ≠ 0: kunci baris barang, **hitung ulang selisih terhadap stok saat ini** bila stok sudah berubah sejak draft (tampilkan peringatan ke admin sebelum setuju), set `stok = stok_fisik`, tulis kartu stok `penyesuaian` dengan `id_stok_opname`. HPP rata-rata tidak berubah. Jalankan pemeriksaan notifikasi (buat atau tutup).
5. Status `final` tidak bisa diubah.
- Catat di CLAUDE.md keputusan poin 4 (penanganan penjualan yang terjadi di antara draft dan persetujuan); ini bahan pembahasan di Bab IV.

## Feature test (`tests/Feature/StokOpnameTest.php`)
1. Kasir membuat draft, mengisi stok fisik, mengajukan.
2. Kasir tidak bisa menyetujui (403).
3. Admin menyetujui: stok berubah menjadi stok fisik, kartu stok penyesuaian tercatat, HPP tetap.
4. Opname final tidak bisa diedit.
5. Selisih ≠ 0 tanpa keterangan ditolak.
6. Halaman peringatan stok hanya menampilkan barang aktif dengan stok ≤ minimum.

## Prompt untuk Claude Code
```
Baca CLAUDE.md, docs/rancangan.md bagian 3 (Stok opname) dan docs/modul/06_monitoring_stok_opname.md.
Kerjakan Modul 6:
1. Halaman cek stok, peringatan stok minimum, dan riwayat kartu stok per barang (Admin dan Kasir).
2. StokOpnameService dengan alur draft → diajukan → final; persetujuan hanya Administrator,
   dalam DB::transaction, memakai KartuStokService dan NotifikasiStokService.
3. Tangani stok yang berubah antara draft dan persetujuan seperti dijelaskan di file modul,
   dan catat keputusannya di CLAUDE.md.
4. Feature test StokOpnameTest dengan 6 skenario.
Jalankan php artisan test, centang Modul 6 di CLAUDE.md.
```
