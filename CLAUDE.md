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

- `StoreBarangRequest` (dan turunannya `UpdateBarangRequest`): `harga_jual` hanya divalidasi bila `can('ubah-harga-jual')`, `status` hanya bila `can('hapus-barang')`; untuk Kasir keduanya diabaikan walaupun dikirim (barang baru → default `aktif`, ubah → status lama tetap) dan di form tampil sebagai info nonaktif.
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
- `NotifikasiStokService::tutupBilaAman($barang)` dipakai barang masuk dan pembatalan penjualan (lihat modul 5). Barang masuk juga memanggil `periksa($barang)` (stok masih ≤ minimum setelah masuk sedikit). `BarangController::update` memanggil `periksa()` lalu `tutupBilaAman()` dalam `DB::transaction` bila `stok_minimum` berubah (`wasChanged`).
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

## Catatan repo (modul 6)

- Monitoring (Admin dan Kasir, `MonitoringStokController`): `stok.index` (cek stok barang aktif, filter `q`/`kategori`/`status` aman/menipis/habis lewat scope `Barang::statusStok()`), `stok.peringatan` (`Barang::stokMenipis()`), `stok.kartu` (`/stok/{barang}/kartu`, filter tanggal dan jenis mutasi). Dashboard dan lonceng menautkan ke `stok.peringatan`.
- `StokOpnameService`: `buatDraft($user, ?$idKategori, ?$keterangan)` (semua barang aktif atau satu kategori; `stok_sistem` = stok saat draft, `stok_fisik` awal = `stok_sistem`), `simpanHitungan($opname, [id_detail_opname => [stok_fisik, keterangan]], $user)`, `ajukan()`, `setujui()` (Admin), `tolak($opname, $catatan, $admin)` (diajukan → draft, catatan disimpan di `stok_opname.keterangan`), `perubahanSejakDraft()` (peringatan untuk Admin), `bolehUbah()`.
- Draft hanya bisa diubah/diajukan oleh pembuatnya atau Administrator (gate `ubah-opname`); `diajukan` dan `final` terkunci (`ValidationException` kunci `opname`). Persetujuan/penolakan: route di grup admin + gate `setujui-opname`.
- Route: `stok-opname.*` (resource tanpa destroy, parameter `{opname}`), `stok-opname.ajukan`, `stok-opname.setujui`, `stok-opname.tolak`. Form `stok-opname.update` dengan `ajukan=1` menyimpan lalu langsung mengajukan.
- Keterangan wajib bila selisih ≠ 0 (dicek di `SimpanHitunganRequest::after()` dan di service); saran: Rusak, Hilang, Kedaluwarsa, Salah catat (`StokOpnameService::KETERANGAN_SELISIH`).
- **Keputusan stok yang berubah antara draft dan persetujuan (bahan Bab IV):** yang diterapkan saat disetujui adalah **selisih hasil hitung**, bukan angka stok fisik: `stok_baru = stok_saat_ini + selisih`. Contoh: draft stok_sistem 10, fisik 8 (selisih −2), lalu terjual 3 (stok 7) → stok_baru = 5. Alasannya, penjualan/barang masuk setelah barang dihitung sudah tercatat di kartu stok dan tidak boleh hilang. Admin melihat tabel "Stok berubah sejak draft dibuat" sebelum menyetujui. Detail opname tetap menyimpan angka hitungan asli; perubahan dicatat di keterangan kartu stok ("stok berubah X → Y sejak draft; selisih Z diterapkan").
- **Bila `stok_saat_ini + selisih < 0` untuk salah satu barang, seluruh persetujuan ditolak** (tidak ada barang yang disesuaikan) dengan pesan "Stok {nama} sudah berubah dan hasil penyesuaian menjadi minus. Kembalikan opname ke draft dan hitung ulang." Admin lalu menolak opname (kembali ke draft) agar dihitung ulang.
- Saat disetujui: barang berselisih dikunci (`lockForUpdate`, urut `id_barang`), kartu stok `penyesuaian` dengan `id_stok_opname` (selisih + → `jumlah_masuk`, − → `jumlah_keluar`), HPP rata-rata tidak berubah, lalu `NotifikasiStokService::periksa()` dan `tutupBilaAman()`.

## Catatan repo (modul 7)

- Controller keuangan di `app/Http/Controllers/Keuangan/` (implements `HasMiddleware`, `can:akses-keuangan`) di dalam grup route `role:administrator`; Form Request di `app/Http/Requests/Keuangan/`; view di `resources/views/keuangan/<fitur>/`.
- Route: `kategori-pengeluaran.*` (tanpa show), `pengeluaran.*` (tanpa show) + `pengeluaran.bukti`, `kas.index/create/store/destroy` (parameter `{kas}`, form `?kategori=modal|prive`), `validasi-kas.index/create/store/show` (parameter `{validasi}`, create `?tanggal=`), `hpp.index` (`?bulan=YYYY-MM`), `hpp.hitung` (POST, `hitung_ulang=1` untuk konfirmasi).
- **Semua penulisan kas lewat `KasService`.** Tambahan modul 7: `perbarui($kas, $jumlah, $tanggal, $keterangan)` (pengeluaran diedit), `hapusMilikPengeluaran()`, `hapusManual()` (hanya kategori modal/prive; kas transaksi lain mengikuti transaksinya), `saldoSebelum($waktu)` (saldo awal buku kas/arus kas), konstanta `KATEGORI_MANUAL` (`modal` ⇒ masuk, `prive` ⇒ keluar).
- `PengeluaranService::simpan/perbarui/hapus`: nomor `OP-YYYYMMDD-01` dari tanggal pengeluaran; kas keluar `operasional` tanggal = tanggal pengeluaran + jam pencatatan. Bukti (jpg/png/pdf ≤ 2 MB) di disk `public` folder `bukti`, ditampilkan lewat route `pengeluaran.bukti` (khusus admin, tidak perlu `storage:link`). File dibuang bila transaksi gagal, file lama dihapus saat diganti/dihapus.
- **Keuangan hanya bulan berjalan:** tanggal pengeluaran, modal/prive, dan validasi kas harus ≥ tanggal 1 bulan berjalan dan ≤ hari ini (Form Request `after_or_equal` + `before_or_equal:today`; `validasi-kas.create?tanggal=` juga dicek). Pengeluaran dengan tanggal lama di bulan sebelumnya tidak bisa diedit/diubah/dihapus, dan kas modal/prive bulan sebelumnya tidak bisa dihapus: controller me-redirect dengan flash `error` (`PengeluaranService::PESAN_BULAN_LALU`, `KasService::PESAN_BULAN_LALU`), service melempar `ValidationException` sebagai pertahanan kedua. Tombol disembunyikan lewat `PengeluaranOperasional::isBulanBerjalan()` dan `Kas::bisaDihapus()` (pengeluaran lama tampil "Terkunci").
- `ValidasiKasService::simpan($tanggal, $uangFisik, $catatan, $admin)` dan `totalTunaiSistem($tanggal)`: satu per tanggal (Form Request `unique` + cek di service + UNIQUE DB), tanggal mendatang ditolak, catatan wajib bila selisih ≠ 0; kas `penyesuaian` masuk bila lebih, keluar bila kurang, `jumlah = |selisih|`. Validasi kas tidak bisa diubah/dihapus. **Penjualan tunai pada tanggal yang sudah ada di `validasi_kas` tidak bisa dibatalkan** (`PenjualanService::batalkan()`, pesan `PESAN_KAS_DIVALIDASI`; tombol disembunyikan lewat `Penjualan::isKasSudahDivalidasi()` di `bisaDibatalkan()`), agar `total_tunai_sistem` yang tersimpan tetap cocok. Penjualan non-tunai di tanggal itu tetap bisa dibatalkan.
- `HppService::hitungBulan($tahun, $bulan, $admin, $hitungUlang = false)`: persediaan awal/akhir dari mutasi kartu stok terakhir sebelum tanggal 1 / sampai akhir bulan (`stok_sesudah × hpp_satuan`), pembelian dari `detail_barang_masuk` per `tanggal_masuk`, terjual dari penjualan `selesai`. Barang tanpa persediaan dan tanpa gerakan tidak disimpan. Bulan yang sudah dihitung butuh `hitungUlang` (ValidationException kunci `periode`); bulan mendatang ditolak; bulan berjalan boleh dihitung sementara. Selisih stok opname ikut masuk HPP.
- Buku kas: default bulan berjalan; saldo berjalan dan saldo akhir hanya tampil tanpa filter kategori/jenis.

## Catatan repo (modul 8)

- **Paket baru:** `barryvdh/laravel-dompdf` ^3.1 (Composer, PDF laporan) dan `chart.js` ^4 (npm, grafik dashboard; diimpor di `resources/js/components/grafik-penjualan.js`, hanya modul bar yang didaftarkan). Tidak ada tabel/kolom baru.
- `config/toko.php` (`TOKO_NAMA`, `TOKO_ALAMAT` di `.env`, contoh di `.env.example`): identitas toko untuk kop laporan.
- `config/dompdf.php` dipublikasikan dengan `enable_font_subsetting = true` (PDF ±25–40 KB, bukan ±860 KB). Test PDF memeriksa ukuran < 200 KB.
- `LaporanService` mengembalikan array biasa (uang string 2 desimal) untuk web, PDF, dashboard, dan AI (modul 9): `pergerakanStok($dari, $sampai, ?$idBarang)` (stok awal = `stok_sesudah` mutasi terakhir sebelum periode; "semua barang" melewati barang tanpa stok dan tanpa mutasi), `arusKas($dari, $sampai, $rincian)`, `labaRugi($tahun, $bulan)`, `penjualan($dari, $sampai)`, `barangTerlaris($dari, $sampai, ?$batas)`, `stokMinimum()`, `grafikPenjualan($hari)`.
- **Laba rugi:** HPP hanya dari `hpp_periode` bulan itu. Bila belum dihitung, `hpp`/`laba_kotor`/`laba_bersih` = `null` dan `hpp_dihitung = false` (view menampilkan peringatan + tautan `hpp.index`; tidak ada angka pengganti). `hpp_usang = true` bila ada kartu stok bulan itu yang dibuat setelah HPP dihitung (saran hitung ulang). Pembelian barang dan prive hanya ditampilkan sebagai catatan, bukan beban. Beban dikelompokkan per `kategori_pengeluaran` menurut `tanggal_pengeluaran`.
- `LaporanController` (`can:akses-laporan`, grup admin): route `laporan.index`, `laporan.pergerakan-stok` (`?dari&sampai&barang`), `laporan.arus-kas` (`?dari&sampai&rincian=1`), `laporan.laba-rugi` (`?bulan=YYYY-MM`), `laporan.penjualan`, `laporan.stok-minimum`. Default periode: awal bulan berjalan s.d. hari ini. **Unduh PDF: tambahkan `?unduh=pdf`** ke URL yang sama.
- View web `resources/views/laporan/*.blade.php` (Tailwind; `_kop` berisi tombol Cetak/Unduh `print:hidden` dan kop toko). View PDF `resources/views/laporan/pdf/*.blade.php` meng-`@extends('laporan.pdf._layout')` dengan CSS biasa di `<style>` (font DejaVu Sans). Layout app memakai `print:hidden` pada sidebar dan header agar halaman bisa dicetak langsung.
- **Kartu "Laba bulan ini" di dashboard** (`DashboardService::labaBulanIni()`): bila HPP periodik bulan berjalan sudah dihitung → laba bersih resmi dari `labaRugi()` (label "Laba bersih (HPP periodik)"); bila belum → `LaporanService::estimasiLabaBulan()` = Σ `penjualan.total_harga` − Σ `penjualan.total_hpp` (status selesai) − beban operasional, label "Estimasi (HPP rata-rata)" + tautan `hpp.index`. Estimasi tidak memuat selisih stok opname. **Estimasi hanya untuk dashboard; laporan laba rugi resmi tetap hanya memakai `hpp_periode`.**
- Dashboard admin (`DashboardService` memakai `LaporanService`): kartu penjualan hari ini, laba bulan ini, saldo kas, barang aktif, stok menipis; grafik penjualan 7/30 hari (`Alpine.data('grafikPenjualan')`, data lewat `@js`); 5 barang terlaris 30 hari.

## Catatan repo (modul 9)

- **Paket baru:** `@tailwindcss/typography` (npm dev, `@plugin` di `resources/css/app.css`) untuk class `prose` pada jawaban AI. `.env.example` berisi `GEMINI_API_KEY=` (kosong), `GEMINI_MODEL`, `GEMINI_TIMEOUT`. Tidak ada tabel/kolom baru (`riwayat_ai` dari modul 1).
- `AiAssistantService`:
  - `klasifikasi()`: **berbasis kata kunci** (`KATA_KUNCI`), dihitung per kelompok; stok/keuangan bila satu kelompok lebih banyak, `umum` bila seimbang atau tidak ada (konteks berisi stok dan keuangan).
  - `kumpulkanKonteks($topik)` lewat `LaporanService`: stok (jumlah aktif/menipis/habis, maks 20 barang menipis; 10 untuk `umum`), keuangan (saldo kas, penjualan bulan ini dan lalu, laba rugi bulan ini dan lalu termasuk estimasi berlabel bila HPP periodik belum dihitung, maks 10 beban per kategori), 10 barang terlaris 30 hari. Nilai uang string desimal Rupiah. **Tanpa data pengguna** (nama, username, email, password). Sekitar 2 KB JSON pada data seeder.
  - `tanya($pertanyaan, $admin)`: `bodyRequest()` (systemInstruction `INSTRUKSI_SISTEM`, konteks JSON dalam blok ```json + pertanyaan, temperature 0.2), API key lewat header `x-goog-api-key` (tidak di URL/body/browser). Selalu menyimpan `riwayat_ai`.
  - **Gagal:** key kosong, HTTP error, respons tanpa teks (`GeminiException`), dan timeout/koneksi (`ConnectionException`) disimpan dengan `status = gagal` dan `pesan_error` (≤ 250 karakter, key disensor). Pengguna melihat `PESAN_GAGAL` "AI Assistant sedang tidak dapat dihubungi. Coba lagi nanti."
- `AiAssistantController` (`can:akses-ai`, grup admin):
  - Route: `ai.index` (chat, 10 tanya-jawab terakhir milik admin), `ai.tanya` (POST, `throttle:ai-assistant`), `ai.riwayat` (semua admin, cari dan filter status, paginasi), `ai.show` (`{riwayat}`, konteks JSON sebagai bukti pengujian).
  - Rate limiter `ai-assistant` di `AppServiceProvider`: 10 per menit per `id_user`; bila lewat batas, redirect balik dengan flash error (bukan halaman 429).
  - `TanyaAiRequest`: wajib, maks 500 karakter (`AiAssistantService::MAKS_PERTANYAAN`).
- **Chat:** form POST biasa. Alpine (inline di `ai/index`) hanya menampilkan gelembung pertanyaan dan indikator "Sedang menjawab..." lalu submit; textarea memakai `readonly` (bukan `disabled`) agar ikut terkirim. Jawaban dirender lewat `RiwayatAi::jawabanHtml()` (`Str::markdown`, `html_input => strip`, `allow_unsafe_links => false`). `store()` menaikkan `set_time_limit` ke timeout Gemini + 30 detik.
- Test memakai `Http::fake()` + `Http::preventStrayRequests()` dan config gemini palsu; isi JSON `riwayat_ai.konteks_data` dibandingkan dengan `assertEquals`, karena MySQL mengubah urutan key JSON.

## Status modul

- [x] Modul 1: migration, model, relasi, seeder 18 tabel
- [x] Modul 2: autentikasi (username), hak akses role, fondasi UI Tailwind
- [x] Modul 3: master data (kategori barang, supplier, barang, pengguna)
- [x] Modul 4: barang masuk (HPP rata-rata, kartu stok, kas)
- [x] Modul 5: penjualan, pembatalan, notifikasi stok minimum
- [x] Modul 6: monitoring stok dan stok opname
- [x] Modul 7: keuangan (pengeluaran, modal/prive, validasi kas, HPP periodik)
- [x] Modul 8: laporan
- [x] Modul 9: AI Assistant (Gemini)
