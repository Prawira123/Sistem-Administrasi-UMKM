# Panduan Modul Pengembangan

Folder ini berisi spesifikasi modul 2 sampai 9 beserta prompt siap tempel untuk Claude Code CLI.
Modul 1 (migration, model, seeder 18 tabel) sudah selesai dan ada di repo ini.

## Persiapan sekali saja (di laptop, VS Code)

Sumber kode adalah repo Anda sendiri: https://github.com/Prawira123/Sistem-Administrasi-UMKM
(commit "first set up" sudah berisi modul 1: 18 migration, 18 model + relasi, 6 seeder). Zip dari Claude tidak perlu dipakai.

1. Clone repo dan buka di VS Code, lalu salin folder `docs/modul/` ini ke dalam repo (`docs/modul/`).
2. Jalankan:
   ```bash
   composer install
   cp .env.example .env
   php artisan key:generate
   npm install
   ```
3. Buat database `sistemadminumkm` (utf8mb4) di MySQL sesuai `.env.example`, isi `DB_USERNAME`/`DB_PASSWORD` di `.env`.
   Ubah juga `APP_LOCALE=id` di `.env` dan `.env.example` (saat ini masih `en`, sehingga pesan validasi bawaan akan berbahasa Inggris).
4. Test memakai MySQL. Buat database kedua `sistemadminumkm_test` (utf8mb4), lalu di `phpunit.xml` ganti dua baris
   `<env name="DB_CONNECTION" value="sqlite"/>` dan `<env name="DB_DATABASE" value=":memory:"/>` menjadi:
   ```xml
   <env name="DB_CONNECTION" value="mysql"/>
   <env name="DB_DATABASE" value="sistemadminumkm_test"/>
   ```
   Username dan password ikut dari `.env`. Database test dikosongkan setiap kali test jalan, jadi data di `sistemadminumkm` aman.
5. `php artisan migrate:fresh --seed` lalu `php artisan test`. Akun seeder: `admin` / `password` dan `kasir` / `password`.
6. Tampilan memakai **Tailwind CSS v4** (sudah terpasang di repo lewat `@tailwindcss/vite`). Saat mengembangkan, jalankan dua terminal: `php artisan serve` dan `npm run dev`. Sebelum demo/sidang, jalankan `npm run build`.
7. Jalankan `claude` di terminal VS Code dari folder repo.

## Kondisi repo yang perlu diketahui sebelum modul 2

Hasil pengecekan isi repo (24/11/2026). Skema database sudah sesuai rancangan: 18 tabel, 30 foreign key, 12 CHECK constraint, dan `migrate:fresh --seed` berhasil di MySQL. Beberapa hal kecil yang memengaruhi modul berikutnya:

| Temuan | Dampak | Ditangani di |
|---|---|---|
| `CLAUDE.md` belum punya bagian "Status modul" | Prompt meminta mencentang status modul | Modul 2 (prompt menambahkan bagian ini) |
| `APP_LOCALE=en` di `.env.example` | Pesan validasi bawaan berbahasa Inggris | Modul 2 (ganti ke `id` + file `lang/id/validation.php`) |
| Model `Barang` memasukkan `stok`, `hpp_rata_rata`, `harga_beli_terakhir` ke `$fillable` | Stok bisa ikut berubah bila controller memakai `$request->all()` | Modul 3 (hapus ketiganya dari `$fillable`, service mengisi langsung) |
| Factory baru ada `UserFactory` | Test modul 3 dan seterusnya butuh data barang/supplier/kategori | Modul 3 (buat `KategoriBarangFactory`, `SupplierFactory`, `BarangFactory`) |
| Konfigurasi Gemini memakai `services.gemini.api_key`, `base_url`, `timeout` | Nama kunci berbeda dari draf awal | Sudah disesuaikan di modul 9 |
| Tailwind CSS v4 + Vite sudah terpasang (`resources/css/app.css` berisi `@import 'tailwindcss'`), belum ada Alpine.js | Form dinamis (barang masuk, POS) butuh JavaScript ringan | Modul 2 memasang Alpine.js dan komponen Blade Tailwind |
| Nama relasi: `StokOpname::approver()`, `NotifikasiStok::pembaca()`, `KategoriPengeluaran::pengeluaran()`, `BarangMasuk/Penjualan/StokOpname::detail()` | Pakai nama ini, jangan buat relasi ganda | Semua modul |

## Claude Code tidak boleh commit atau push

Tidak ada prompt modul yang menyuruh Claude Code menjalankan `git commit` atau `git push`. Commit dan push selalu Anda lakukan sendiri. Untuk mengunci aturan ini:

1. Tambahkan baris berikut di `CLAUDE.md` bagian "Cara kerja":
   `- Jangan menjalankan git add, git commit, git push, atau perintah git lain yang mengubah repo. Pengguna melakukannya sendiri.`
2. Buat file `.claude/settings.json` di root repo:
   ```json
   {
     "permissions": {
       "deny": [
         "Bash(git push:*)",
         "Bash(git commit:*)",
         "Bash(git add:*)",
         "Bash(git reset:*)",
         "Bash(git checkout:*)"
       ]
     }
   }
   ```
   Dengan ini Claude Code tidak bisa menjalankan perintah tersebut sama sekali, walaupun diminta.

## Cara memakai prompt

- Kerjakan berurutan, satu modul per sesi. Mulai sesi baru (`/clear`) setiap ganti modul supaya konteks bersih.
- Tempel blok **Prompt untuk Claude Code** dari file modul. Claude Code akan membaca `CLAUDE.md` otomatis.
- Setelah modul selesai: cek di browser (`php artisan serve`), jalankan `php artisan test`, lalu commit:
  `git add -A && git commit -m "Modul N: ..." && git push`.
- Centang status modul di `CLAUDE.md` bagian "Status modul" (atau minta Claude Code mencentangnya).

## Daftar modul

| No | File | Isi | Bergantung pada |
|---|---|---|---|
| 2 | `02_autentikasi_hak_akses.md` | Login username, logout, middleware role, gate, layout | 1 |
| 3 | `03_master_data.md` | Kategori barang, supplier, barang, pengguna, dashboard awal | 2 |
| 4 | `04_barang_masuk.md` | Barang masuk, HPP rata-rata, kartu stok, kas keluar | 3 |
| 5 | `05_penjualan.md` | Kasir/POS, nota, pembatalan, notifikasi stok minimum | 4 |
| 6 | `06_monitoring_stok_opname.md` | Cek stok, peringatan, stok opname (ajukan, setujui) | 5 |
| 7 | `07_keuangan.md` | Pengeluaran, modal/prive, validasi kas, HPP periodik | 6 |
| 8 | `08_laporan.md` | Pergerakan stok, arus kas, laba rugi, cetak/PDF | 7 |
| 9 | `09_ai_assistant.md` | AI Assistant Gemini, riwayat | 8 |

## Prompt pembuka setiap sesi (opsional)

```
Baca CLAUDE.md dan docs/rancangan.md. Cek status modul di CLAUDE.md dan
git log terakhir, lalu ringkas dalam 5 baris apa yang sudah ada sebelum kita mulai.
```

## Bila ada masalah

```
php artisan test gagal di <nama test>. Tampilkan penyebabnya, perbaiki kodenya
(bukan test-nya, kecuali test-nya yang salah terhadap rancangan), lalu jalankan
ulang hanya test yang gagal.
```
