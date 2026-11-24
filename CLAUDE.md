# Sistem Informasi Monitoring Stok dan Keuangan (Skripsi)

Aplikasi web untuk toko retail UMKM di Sekretariat DPRD Kabupaten Badung. Modul: monitoring stok, pengelolaan keuangan, dan AI Assistant berbasis Gemini API. Proyek tugas akhir, jadi kode harus rapi, mudah dijelaskan, dan sesuai rancangan.

## Sumber kebenaran

- `docs/rancangan.md`: hak akses, proses DFD, aturan bisnis, laporan, dan struktur tabel lengkap. **Baca dulu sebelum membuat fitur.**
- `docs/skema_database.sql`: DDL MySQL final. Migration Laravel harus menghasilkan skema yang sama (nama tabel, kolom, tipe, FK, UNIQUE, CHECK).
- `docs/diagram/*.mmd`: DFD dan ERD (Mermaid).
- Bila kode dan rancangan berbeda, ikuti rancangan atau tanyakan dulu. Jangan menambah tabel/kolom tanpa menyebutkannya.

## Stack

- Laravel 13 (PHP 8.3+), MySQL 8.3, Blade untuk tampilan, autentikasi bawaan Laravel (tanpa Breeze/Jetstream).
- UI: Tailwind CSS v4 lewat Vite (`@tailwindcss/vite`, sudah terpasang) + Alpine.js untuk interaksi ringan. Pakai komponen Blade di `resources/views/components/` (x-button, x-input, x-card, x-table, x-badge, x-modal, dan lainnya); jangan menulis ulang class Tailwind yang sama di banyak halaman. View PDF (dompdf) memakai CSS biasa inline karena dompdf tidak mendukung Tailwind v4.
- Database lokal: `sistemadminumkm` (lihat `.env.example`). Akun seeder: `admin`/`password` dan `kasir`/`password`.
- Gemini API lewat HTTP client Laravel; key di `.env` sebagai `GEMINI_API_KEY`, dibaca lewat `config('services.gemini.api_key')`. Model, base URL, dan timeout juga dari `config('services.gemini.*')`.
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
- **Jangan menjalankan `git add`, `git commit`, `git push`, atau perintah git lain yang mengubah repo.** Pengguna melakukan commit dan push sendiri. Boleh membaca (`git status`, `git diff`, `git log`).
- Spesifikasi dan prompt per modul ada di `docs/modul/` (mulai dari `docs/modul/00_PANDUAN.md`).

## Catatan repo (modul 1)

- Nama relasi yang sudah ada, pakai ini dan jangan buat relasi ganda: `BarangMasuk/Penjualan/StokOpname::detail()`, `StokOpname::approver()` (kolom `disetujui_oleh`), `NotifikasiStok::pembaca()` (kolom `dibaca_oleh`), `KategoriPengeluaran::pengeluaran()`, `Kas::pengeluaran()`, `*::kas()` (hasOne).
- CHECK constraint dipasang di migration lewat `DB::statement('ALTER TABLE ... ADD CONSTRAINT ...')`.
- Test memakai **MySQL**, database terpisah `sistemadminumkm_test` (diatur di `phpunit.xml`), bukan SQLite, agar CHECK constraint, ENUM, dan `lockForUpdate` diuji sama seperti di aplikasi. Jangan mengubah `phpunit.xml` kembali ke SQLite.
- COA tidak dipakai: beban dikelompokkan lewat `kategori_pengeluaran`, arus kas lewat enum `kas.kategori`.

## Catatan repo (modul 2)

- Konstanta role/status di `User`: `ROLE_ADMINISTRATOR`, `ROLE_KASIR`, `STATUS_AKTIF`, `STATUS_NONAKTIF`; helper `isAdministrator()`, `isKasir()`, `isAktif()`, `labelRole()`.
- Route: grup `auth`, lalu `->middleware('role:'.User::ROLE_ADMINISTRATOR)` atau `role:administrator,kasir`. Gate admin di `AppServiceProvider::GATE_ADMINISTRATOR`: `kelola-master`, `ubah-harga-jual`, `hapus-barang`, `setujui-opname`, `batalkan-penjualan`, `akses-keuangan`, `akses-laporan`, `akses-ai`.
- Halaman memakai layout sebagai komponen: `<x-layouts::app title="...">` dan `<x-layouts::guest>` (didaftarkan lewat `Blade::anonymousComponentPath`). Flash `success`/`error`/`warning` otomatis tampil lewat `<x-alert />` di layout.
- Menu sidebar di `resources/views/layouts/partials/sidebar-menu.blade.php`; route yang belum ada tampil abu-abu "Segera". Nama route yang sudah disiapkan: `barang.index`, `kategori-barang.index`, `supplier.index`, `pengguna.index`, `barang-masuk.index`, `penjualan.index`, `stok.index`, `stok-opname.index`, `pengeluaran.index`, `kas.index`, `validasi-kas.index`, `hpp.index`, `laporan.index`, `ai.index`.
- Elemen `#notifikasi-stok` di header untuk lonceng modul 5.
- Format: `App\Support\Format::rupiah()`, `::tanggal()`, `::tanggalWaktu()`; directive Blade `@rupiah(...)` dan `@tanggal(...)`.
- Di dalam tag komponen Blade pakai `x-on:click`, bukan `@click`. `tests/TestCase.php` memanggil `withoutVite()`.

## Catatan repo (modul 3)

- `Barang::$fillable` **tidak** berisi `stok`, `hpp_rata_rata`, `harga_beli_terakhir`. Service mengisinya langsung (`$barang->stok = $baru; $barang->save();`). Factory tetap bisa mengisinya: `Barang::factory()->stok(20, 3000)`.
- Helper `Barang`: `statusStok()` (`aman`/`menipis`/`habis`, cocok dengan `x-badge`), `isStokMenipis()`, scope `aktif()` dan `stokMenipis()` (aktif dan `stok <= stok_minimum`).
- Kode otomatis lewat `App\Services\NomorOtomatisService` (lihat catatan modul 4); `supplier()` → SUP-001, `barang()` → BRG-0001.
- Hapus data yang masih dirujuk FK RESTRICT: pakai `$this->hapusAtauTolak($model, $routeIndex, $pesanSukses)` dari base `Controller` (menangkap `QueryException` SQLSTATE 23000).
- Controller master di `app/Http/Controllers/Master/`, Form Request di `app/Http/Requests/Master/`, view di `resources/views/master/<entitas>/` (`index`, `create`, `edit`, `_form`). Route `barang.*` untuk kedua role kecuali `barang.destroy` (admin); `kategori-barang.*`, `supplier.*`, `pengguna.*` (parameter `{user}`) khusus admin.
- Komponen tambahan: `x-form-field` (label + input + error + bantuan), `x-search-bar` (form GET `q` + slot filter), `x-delete-button` (tombol + modal hapus), `x-button size="sm"`, `x-table :polos="true"` (di dalam x-card).
- Dashboard memakai `App\Services\DashboardService` (barang aktif, stok menipis; admin: penjualan hari ini dan saldo kas dari tabel `penjualan`/`kas`).

## Catatan repo (modul 4)

- Uang: `App\Support\Uang` (bcmath, string 2 desimal): `normal()`, `tambah()`, `kurang()`, `kali()`, `bagi()` (dibulatkan setengah ke atas), `banding()`. Jangan pakai float.
- `KartuStokService::catat($barang, $jenis, $masuk, $keluar, $sumber, $keterangan, $user)`: satu-satunya penulis `kartu_stok`. Panggil **setelah** `$barang->save()`; `stok_sesudah = $barang->stok`, `hpp_satuan = $barang->hpp_rata_rata`. Konstanta `MASUK`/`KELUAR`/`PENYESUAIAN`.
- `KasService::catat($jenis, $kategori, $jumlah, $referensi, $user, $tanggal, $keterangan)`: satu-satunya penulis `kas`; `validasi()` bisa dipanggil terpisah. Keduanya melempar `InvalidArgumentException` bila referensi salah.
- `NomorOtomatisService`: `barangMasuk()`, `penjualan()`, `pengeluaran()`, `stokOpname()`, `supplier()`, `barang()`. Panggil di dalam `DB::transaction` agar nomor dikunci (`lockForUpdate`).
- `NotifikasiStokService::tutupBilaAman($barang)` dipakai barang masuk dan pembatalan penjualan (lihat modul 5).
- `BarangMasukService::simpan($data, $user)` dan `hitungHppRataRata()`. Kesalahan aturan bisnis dilempar sebagai `ValidationException`. Barang masuk tidak bisa diedit/dihapus.
- Kartu stok `tanggal` = waktu pencatatan (`now()`); kas pembelian `tanggal` = tanggal faktur + jam pencatatan.
- **`tanggal_masuk` hanya boleh di bulan berjalan dan tidak lebih dari hari ini** (validasi di `StoreBarangMasukRequest`). Alasannya: kartu stok memakai waktu pencatatan, sedangkan HPP periodik (modul 7) menghitung pembelian dari `tanggal_masuk`, jadi keduanya harus jatuh di bulan yang sama. Seeder dan test yang memakai "kemarin" harus tetap di bulan berjalan (test memakai `travelTo`).
- `ContohTransaksiSeeder` (dipanggil `DatabaseSeeder`): modal awal Rp5.000.000 lewat `KasService`, dua barang masuk lewat `BarangMasukService`; Beras dan Pulpen sengaja di bawah stok minimum.
- Komponen Alpine terdaftar di `resources/js/app.js` lewat `Alpine.data(...)`, file di `resources/js/components/`. `x-input` boleh tanpa `name` (untuk `x-bind:name` di baris dinamis).

## Catatan repo (modul 5)

- `PenjualanService::simpan($items, $metode, $jumlahBayar, $kasir)` dan `batalkan($penjualan, $alasan, $admin)`. Item dengan barang sama digabung; stok dicek **setelah** `lockForUpdate` (urut `id_barang`). Pesan stok kurang: "Stok {nama} tidak cukup (sisa {stok})." Non-tunai: `jumlah_bayar = total_harga`, kembalian 0. Konstanta `PenjualanService::METODE`.
- Pembatalan: kunci header penjualan, status `batal`, `keterangan` = alasan, stok kembali lewat kartu stok `penyesuaian` (`id_penjualan`), HPP rata-rata tidak berubah, kas dihapus lewat `KasService::hapusMilikPenjualan()`, lalu `tutupBilaAman`. Kartu stok `keluar` tetap ada (FK RESTRICT), jadi riwayat mutasi lengkap.
- `NotifikasiStokService`: `periksa()` (buat notifikasi bila `stok <= stok_minimum` dan belum ada yang `baru`/`dibaca`), `tutupBilaAman()`, `tandaiDibaca()`, `tandaiSemuaDibaca()`, `ringkasan()`. Lonceng: `layouts/partials/notifikasi-stok.blade.php`, datanya dari view composer di `AppServiceProvider`.
- Route: `barang.cari` (`GET /barang/cari?q=`, JSON, aktif dan stok > 0, kode persis di atas dengan `tepat: true`), `penjualan.index/create/store/show`, `penjualan.nota` (`?ukuran=58|80`), `penjualan.batal` (grup admin + gate `batalkan-penjualan`), `notifikasi-stok.baca`, `notifikasi-stok.baca-semua`.
- `Barang::dataKasir()`: bentuk data barang untuk POS (tanpa HPP). Helper JS uang bersama di `resources/js/components/uang.js` (`keSen`, `formatRupiah`); POS di `kasir-pos.js` (`Alpine.data('kasirPos')`).
- **Kasir hanya melihat penjualan miliknya** (`id_user` = user login): riwayat dipaksa ke filter miliknya (parameter `kasir` diabaikan dan filternya disembunyikan); detail dan nota memakai gate `lihat-penjualan` (403 bila bukan miliknya). Administrator melihat semua dan bisa memfilter per kasir.
- **Pembatalan hanya untuk transaksi dengan `tanggal_penjualan` di bulan berjalan**, karena laporan dan HPP periode sebelumnya sudah berjalan. Dicek di `PenjualanService::batalkan()` (pesan `PenjualanService::PESAN_BULAN_LALU`); tombol batal disembunyikan lewat `Penjualan::bisaDibatalkan()` (`isBulanBerjalan()` dan status `selesai`). Test memakai `travelTo`.
- `ContohTransaksiSeeder` juga membuat dua penjualan (tunai dan QRIS) oleh Kasir; Beras dan Pulpen memicu notifikasi stok minimum.

## Status modul

- [x] Modul 1: migration, model, relasi, seeder 18 tabel
- [x] Modul 2: autentikasi (username), hak akses role, fondasi UI Tailwind
- [x] Modul 3: master data (kategori barang, supplier, barang, pengguna)
- [x] Modul 4: barang masuk (HPP rata-rata, kartu stok, kas)
- [x] Modul 5: penjualan, pembatalan, notifikasi stok minimum
- [ ] Modul 6: monitoring stok dan stok opname
- [ ] Modul 7: keuangan (pengeluaran, modal/prive, validasi kas, HPP periodik)
- [ ] Modul 8: laporan
- [ ] Modul 9: AI Assistant (Gemini)
