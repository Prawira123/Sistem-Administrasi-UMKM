# Sistem Informasi Monitoring Stok dan Keuangan (Skripsi)

Aplikasi web untuk toko retail UMKM di Sekretariat DPRD Kabupaten Badung. Modul: monitoring stok, pengelolaan keuangan, dan AI Assistant berbasis Gemini API. Proyek tugas akhir, jadi kode harus rapi, mudah dijelaskan, dan sesuai rancangan.

## Sumber kebenaran

- `docs/rancangan.md`: hak akses, proses DFD, aturan bisnis, laporan, dan struktur tabel lengkap. **Baca dulu sebelum membuat fitur.**
- `docs/skema_database.sql`: DDL MySQL final. Migration Laravel harus menghasilkan skema yang sama (nama tabel, kolom, tipe, FK, UNIQUE, CHECK).
- `docs/diagram/*.mmd`: DFD dan ERD (Mermaid).
- Bila kode dan rancangan berbeda, ikuti rancangan atau tanyakan dulu. Jangan menambah tabel/kolom tanpa menyebutkannya.

## Stack

- Laravel 13 (PHP 8.3+), MySQL 8.3, Blade untuk tampilan, autentikasi bawaan Laravel.
- Gemini API lewat HTTP client Laravel; key di `.env` sebagai `GEMINI_API_KEY`, dibaca lewat `config/services.php`.
- Bahasa antarmuka: Indonesia. Format uang Rupiah (`Rp1.234.567`), tanggal `d/m/Y`, zona waktu `Asia/Makassar` (WITA, Bali).

## Konvensi database dan model

- Nama tabel bahasa Indonesia, tunggal, snake_case, persis seperti di rancangan (`barang`, `penjualan`, `detail_penjualan`, ...). Set `protected $table` di setiap model.
- Primary key `id_<tabel>` (contoh `id_barang`). Set `protected $primaryKey` di setiap model; sebutkan foreign key dan owner key secara eksplisit di `belongsTo`/`hasMany`.
- Nominal uang `DECIMAL(15,2)`; cast `decimal:2` di model. Jangan pakai float untuk uang.
- Tabel `users` memakai `id_user`, login dengan `username` (bukan email), kolom `role` (`administrator`/`kasir`) dan `status` (`aktif`/`nonaktif`). Ganti migration `users` bawaan Laravel; sesuaikan `sessions.user_id` agar tetap cocok.
- Kolom `barang.stok` **tidak pernah** diubah langsung dari form. Stok hanya berubah lewat service barang masuk, penjualan, pembatalan, dan stok opname final, dan setiap perubahan menulis `kartu_stok`.

## Hak akses

- Dua role: `administrator` (akses penuh) dan `kasir` (transaksi penjualan dan pengelolaan stok). Detail per halaman ada di `docs/rancangan.md` bagian 1.
- Terapkan dengan middleware role di route **dan** policy/gate di controller. Kasir tidak boleh mengubah `harga_jual`, menghapus barang, menyetujui stok opname, membatalkan penjualan, atau membuka modul keuangan, laporan, dan AI Assistant.

## Arsitektur kode

- Logika transaksi di service class (`app/Services`), misalnya `BarangMasukService`, `PenjualanService`, `StokOpnameService`, `KasService`, `HppService`, `LaporanService`, `AiAssistantService`. Controller tetap tipis.
- Setiap transaksi yang menyentuh lebih dari satu tabel dibungkus `DB::transaction`; penjualan memakai `lockForUpdate()` pada baris barang.
- Validasi input dengan Form Request. Pesan validasi dalam bahasa Indonesia.
- Validasi referensi kas dan kartu stok (lihat rancangan bagian 3) dilakukan di service, karena MySQL tidak mengizinkan CHECK pada kolom FK yang memakai CASCADE.
- AI Assistant: Gemini tidak pernah diberi akses database. Service mengambil data, merangkum menjadi JSON, mengirimkannya sebagai konteks, lalu menyimpan hasil ke `riwayat_ai`. Tangani timeout dan error API dengan status `gagal`.

## Pengujian

- Pengujian skripsi memakai Black Box Testing; siapkan juga Feature test Laravel untuk alur utama: login per role, barang masuk (stok dan HPP rata-rata), penjualan (stok berkurang, kas masuk, notifikasi stok minimum), stok opname (ajukan lalu setujui), laporan laba rugi.
- Gunakan factory dan seeder: satu Administrator, satu Kasir, kategori pengeluaran awal (lihat bagian akhir `skema_database.sql`), dan contoh barang.

## Cara kerja

- Kerjakan per modul dengan urutan: migration dan seeder → model dan relasi → service → controller dan route → tampilan → test.
- Setelah tiap modul, jalankan `php artisan migrate:fresh --seed` dan `php artisan test`.
- Jangan commit `.env` atau API key.
