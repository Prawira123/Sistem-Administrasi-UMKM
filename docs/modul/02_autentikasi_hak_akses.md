# Modul 2: Autentikasi dan Hak Akses

**DFD:** proses 1.0 Login. **Tabel:** `users`, `sessions`.

## Tujuan
Pengguna login dengan `username` dan password. Akun `nonaktif` ditolak. Setiap halaman dibatasi sesuai role (`administrator`, `kasir`) seperti tabel hak akses di `docs/rancangan.md` bagian 1.

## Yang dibuat
- `app/Http/Controllers/Auth/LoginController.php`: `showLoginForm`, `login`, `logout`.
- `app/Http/Requests/LoginRequest.php`: `username` wajib, `password` wajib, pesan bahasa Indonesia.
- `app/Http/Middleware/RoleMiddleware.php` dengan alias `role` di `bootstrap/app.php`; dipakai `->middleware('role:administrator')` atau `role:administrator,kasir`.
- Gate di `AppServiceProvider`: `kelola-master` (admin), `ubah-harga-jual` (admin), `hapus-barang` (admin), `setujui-opname` (admin), `batalkan-penjualan` (admin), `akses-keuangan` (admin), `akses-laporan` (admin), `akses-ai` (admin).
- Layout Blade `resources/views/layouts/app.blade.php`: sidebar menu yang tampil sesuai role (`@can`), nama pengguna dan role di header, tombol logout, area flash message.
- `resources/views/auth/login.blade.php`.
- Halaman `dashboard` sederhana (placeholder; diisi di modul 3).
- Route: `/` → redirect ke `login` atau `dashboard`; grup `auth` untuk semua halaman aplikasi.
- Helper format: `app/Support/Format.php` dengan `rupiah(15000) → "Rp15.000"` dan `tanggal($date) → "24/11/2026"` (zona waktu Asia/Makassar), serta Blade directive `@rupiah` bila perlu.

## Aturan
- Login dengan `Auth::attempt(['username' => ..., 'password' => ..., 'status' => 'aktif'])`.
- Pesan gagal: "Username atau password salah." Akun nonaktif: "Akun Anda tidak aktif. Hubungi Administrator."
- Saat login berhasil, isi `terakhir_login = now()`, regenerasi session.
- Batasi percobaan login (RateLimiter, 5 kali per menit per username+IP).
- Akses ditolak → halaman 403 bahasa Indonesia ("Anda tidak memiliki akses ke halaman ini.").
- Tampilan: **Blade + Tailwind CSS v4** lewat Vite (sudah terpasang di repo). Tanpa paket starter kit (Breeze/Jetstream) dan tanpa library UI berbayar, agar mudah dijelaskan. Catat pilihan ini di CLAUDE.md bagian Stack.

## Fondasi UI Tailwind (dipakai semua modul berikutnya)
- Pasang **Alpine.js** (`npm install alpinejs`, impor di `resources/js/app.js`) untuk interaksi ringan: dropdown, modal konfirmasi, sidebar mobile, baris item dinamis.
- Warna tema di `resources/css/app.css` lewat `@theme` (misalnya `--color-primary-*`), jangan menulis warna acak di setiap halaman.
- Komponen Blade anonim di `resources/views/components/`: `x-button` (varian primary/secondary/danger), `x-input`, `x-select`, `x-textarea`, `x-label`, `x-input-error`, `x-card`, `x-table`, `x-badge` (aman/menipis/habis, status), `x-alert` (flash sukses/gagal), `x-modal` (konfirmasi hapus/batal), `x-page-header` (judul + tombol aksi), `x-empty-state`.
- Layout `layouts/app.blade.php`: sidebar kiri (bisa dilipat di layar kecil), header berisi nama pengguna, role, dan tempat lonceng notifikasi (diisi modul 5). Layout `layouts/guest.blade.php` untuk login.
- Responsif minimal untuk lebar tablet, karena kasir mungkin memakai tablet.
- Ikon: SVG inline (misalnya Heroicons disalin ke komponen `x-icon`), tanpa CDN.

## Penyesuaian dengan repo
- Tambahkan bagian `## Status modul` di akhir `CLAUDE.md` (modul 1 dicentang, modul 2 sampai 9 belum).
- Ganti `APP_LOCALE=id` di `.env.example`, tambahkan `lang/id/validation.php` (pesan validasi bahasa Indonesia) dan `lang/id/auth.php`.
- Tambahkan konstanta `ROLE_ADMINISTRATOR` dan `ROLE_KASIR` di model `User` dan pakai di middleware/gate; method `isAdministrator()` dan `isKasir()` sudah ada.
- Route `/` saat ini menampilkan `welcome`; ganti menjadi redirect ke login/dashboard.

## Feature test (`tests/Feature/AutentikasiTest.php`)
1. Admin login dengan username benar → redirect dashboard.
2. Password salah → kembali ke login dengan error.
3. Akun nonaktif tidak bisa login.
4. Tamu membuka `/dashboard` → redirect login.
5. Kasir membuka route khusus admin (buat route uji, misalnya `/supplier` placeholder) → 403.
6. Logout menghapus sesi.

## Prompt untuk Claude Code
```
Baca CLAUDE.md, docs/rancangan.md bagian 1, dan docs/modul/02_autentikasi_hak_akses.md.
Kerjakan Modul 2 sesuai file modul tersebut:
1. Login dengan username (bukan email), tolak akun nonaktif, catat terakhir_login, rate limit.
2. Middleware role dengan alias 'role' dan Gate untuk setiap hak akses admin yang disebut di file modul.
3. UI dengan Tailwind CSS v4 (sudah ada di repo) + Alpine.js: komponen Blade di resources/views/components
   seperti daftar di bagian "Fondasi UI Tailwind", layout app dan guest dengan sidebar sesuai role,
   halaman login, dashboard placeholder, halaman 403 bahasa Indonesia.
4. Helper format Rupiah dan tanggal d/m/Y.
5. Feature test AutentikasiTest dengan 6 skenario di file modul.
Lakukan juga bagian "Penyesuaian dengan repo" di file modul (Status modul di CLAUDE.md, APP_LOCALE=id,
lang/id, konstanta role). Jangan ubah migration modul 1. Setelah selesai jalankan php artisan migrate:fresh --seed dan php artisan test,
lalu centang Modul 2 di CLAUDE.md dan ringkas file yang dibuat.
```
