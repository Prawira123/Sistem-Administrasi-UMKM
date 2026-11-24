# Rancangan Sistem Informasi Monitoring Stok dan Keuangan

UMKM toko retail di lingkungan Sekretariat DPRD Kabupaten Badung (Puspem Badung, Bali).
Stack: **Laravel 13**, **MySQL 8.3**, Gemini API (AI Assistant). Dokumen ini adalah sumber kebenaran untuk implementasi;
versi lengkap dengan gambar ada di `Perancangan_Sistem.docx`, skrip DDL di `skema_database.sql`, kode diagram Mermaid di `diagram/`.

## 1. Pengguna dan hak akses

| Halaman / fitur | Administrator | Kasir |
|---|---|---|
| Login, Dashboard | Ya | Ya |
| Data Barang | Ya | Ya: tambah dan ubah, **tanpa** ubah `harga_jual` dan tanpa hapus |
| Data Supplier, kategori barang, pengguna | Ya | Tidak |
| Barang Masuk | Ya | Ya |
| Transaksi Penjualan | Ya | Ya |
| Cek Stok, Monitoring Stok, Peringatan Stok Minimum | Ya | Ya |
| Stok Opname: input hasil hitung dan ajukan | Ya | Ya |
| Stok Opname: persetujuan (final) | Ya | Tidak |
| Batalkan penjualan | Ya | Tidak |
| Perhitungan HPP, Pengeluaran Operasional, modal/prive, Validasi Kas | Ya | Tidak |
| Laporan Pergerakan Stok, Arus Kas, Laba Rugi | Ya | Tidak |
| AI Assistant (Gemini API) | Ya | Tidak |

## 2. Proses (DFD level 1)

Entitas eksternal: Administrator, Kasir, Gemini API. Supplier bukan entitas eksternal (datanya diinput pengguna).

| No | Proses | Pengguna | Data store |
|---|---|---|---|
| 1.0 | Login | Admin, Kasir | users |
| 2.0 | Kelola Data Master | Admin (Kasir: data barang) | users, kategori_barang, barang, supplier |
| 3.0 | Kelola Barang Masuk | Admin, Kasir | barang_masuk, barang, kartu_stok, kas, notifikasi_stok |
| 4.0 | Transaksi Penjualan | Admin, Kasir | penjualan, barang, kartu_stok, kas, notifikasi_stok |
| 5.0 | Monitoring Stok | Admin, Kasir (persetujuan opname: Admin) | barang, notifikasi_stok, stok_opname, kartu_stok |
| 6.0 | Kelola Keuangan | Admin | pengeluaran_operasional, kas, validasi_kas, hpp_periode |
| 7.0 | Buat Laporan | Admin | kartu_stok, kas, penjualan, pengeluaran_operasional, hpp_periode |
| 8.0 | AI Assistant | Admin | riwayat_ai (+ baca tabel stok dan keuangan) |

- **3.0 Kelola Barang Masuk:** Administrator atau Kasir mencatat barang yang datang dari supplier. Setiap item menambah stok, menghitung ulang HPP rata-rata tertimbang, menulis mutasi ke kartu stok, dan membuat baris kas keluar. Bila stok kembali di atas minimum, notifikasi yang masih terbuka ditutup.
- **4.0 Transaksi Penjualan:** Kasir memindai atau mencari barang, sistem menolak jumlah yang melebihi stok, lalu menyimpan transaksi beserta salinan harga jual dan HPP per unit. Stok berkurang, mutasi keluar tercatat, kas masuk tercatat, dan notifikasi dibuat bila stok sampai di batas minimum.
- **5.0 Monitoring Stok:** Cek stok, peringatan stok minimum, dan input stok opname bisa dilakukan Kasir dan Administrator. Hasil opname yang diajukan baru mengubah stok setelah disetujui Administrator, sebagai kontrol karena Kasir ikut mengelola stok.
- **6.0 Kelola Keuangan:** Pencatatan beban operasional, modal dan prive (pemisahan uang usaha dan pribadi), validasi kas harian, serta perhitungan HPP periodik dengan rumus Persediaan Awal + Pembelian Bersih − Persediaan Akhir.
- **7.0 Buat Laporan:** Laporan dihasilkan langsung dari data transaksi sesuai periode yang dipilih, sehingga penyusunan laporan yang sebelumnya lebih dari tiga hari bisa dilakukan seketika.
- **8.0 AI Assistant:** Gemini API tidak mengakses basis data secara langsung. Sistem mengambil data yang relevan, merangkumnya menjadi JSON, lalu mengirimkannya bersama pertanyaan sebagai prompt. Jawaban disimpan sebagai riwayat. Fitur ini hanya untuk Administrator.

## 3. Aturan bisnis (wajib dalam satu `DB::transaction`)

### Simpan barang masuk
1. Simpan header `barang_masuk` dan semua `detail_barang_masuk`; `total_pembelian` = Σ subtotal.
2. Per item: `hpp_baru = (stok_lama × hpp_lama + jumlah × harga_beli) ÷ (stok_lama + jumlah)`; tambah `stok`; isi `harga_beli_terakhir`.
3. Tulis `kartu_stok` jenis `masuk` (isi `id_barang_masuk`, stok_sebelum, stok_sesudah, hpp_satuan).
4. Bila stok > `stok_minimum`, ubah notifikasi barang itu yang berstatus `baru`/`dibaca` menjadi `selesai`.
5. Tulis `kas` jenis `keluar`, kategori `pembelian_barang`, `id_barang_masuk` terisi.

### Simpan penjualan
1. Kunci baris barang (`lockForUpdate`), tolak bila jumlah > stok.
2. Simpan header dan detail; salin `harga_jual` dan `hpp_rata_rata` ke `detail_penjualan.harga_jual` / `hpp_satuan`; hitung total_harga, total_hpp, kembalian.
3. Kurangi stok; tulis `kartu_stok` jenis `keluar` (isi `id_penjualan`).
4. Bila stok ≤ `stok_minimum` dan belum ada notifikasi berstatus `baru`/`dibaca`, buat `notifikasi_stok`.
5. Tulis `kas` jenis `masuk`, kategori `penjualan`, `id_penjualan` terisi.
6. Nomor transaksi `TRX-YYYYMMDD-0001`.

### Batalkan penjualan (Admin)
1. `status = batal`, isi `keterangan`.
2. Kembalikan stok; tulis `kartu_stok` jenis `penyesuaian` dengan `id_penjualan`.
3. Hapus baris `kas` milik penjualan itu.

### Stok opname
1. Kasir/Admin input `stok_fisik` per barang (`selisih = stok_fisik − stok_sistem`), status `draft` → `diajukan`.
2. Admin menyetujui: isi `disetujui_oleh`, `disetujui_pada`, status `final` (tidak bisa diedit lagi).
3. Saat final: per barang dengan selisih ≠ 0, `stok = stok_fisik`, tulis `kartu_stok` jenis `penyesuaian` (isi `id_stok_opname`). HPP rata-rata tidak berubah.

### Keuangan
- Pengeluaran operasional → `kas` keluar kategori `operasional` (`id_pengeluaran`).
- Modal → `kas` masuk kategori `modal`; prive (ambil pribadi) → `kas` keluar kategori `prive`.
- Validasi kas harian (satu per tanggal): `total_tunai_sistem` = Σ penjualan tunai berstatus selesai; `selisih = uang_fisik − total_tunai_sistem`; bila ≠ 0 buat `kas` kategori `penyesuaian` dengan `id_validasi_kas`.
- HPP periodik per barang per bulan: `total_hpp = nilai_persediaan_awal + nilai_pembelian − nilai_persediaan_akhir`; nilai persediaan = `stok_sesudah × hpp_satuan` dari mutasi `kartu_stok` terakhir sebelum awal / pada akhir periode.

### Validasi referensi (di aplikasi, bukan CHECK MySQL)
- `kas.kategori=penjualan` ⇒ `id_penjualan` wajib; `pembelian_barang` ⇒ `id_barang_masuk`; `operasional` ⇒ `id_pengeluaran`; `modal` hanya `masuk`; `prive` hanya `keluar`.
- `kartu_stok.jenis_mutasi=masuk` ⇒ `id_barang_masuk`; `keluar` ⇒ `id_penjualan`.

### Penomoran otomatis
`TRX-YYYYMMDD-0001` (penjualan), `BM-YYYYMMDD-001` (barang masuk), `OP-YYYYMMDD-01` (pengeluaran), `SO-YYYYMM-01` (stok opname).

## 4. Laporan

- **Pergerakan stok:** `kartu_stok` per barang dan periode, urut tanggal.
- **Arus kas:** saldo awal (Σ masuk − Σ keluar sebelum periode) + kas masuk per kategori − kas keluar per kategori = saldo akhir.
- **Laba rugi (bulanan, multi-step):** Pendapatan (Σ `penjualan.total_harga` status selesai) − HPP (Σ `hpp_periode.total_hpp`) = laba kotor; − beban operasional per kategori = laba bersih. Pembelian barang dan prive **bukan** beban.
- **Peringatan stok minimum:** `barang.status='aktif' AND stok <= stok_minimum`.

## 5. AI Assistant (Gemini API)

- Hanya Administrator. API key di `.env` (`GEMINI_API_KEY`), jangan disimpan di DB atau dikirim ke browser.
- Gemini **tidak** mengakses database. Alur: klasifikasi pertanyaan (stok/keuangan/umum) → query data relevan → ringkas jadi JSON → kirim sebagai konteks prompt → simpan pertanyaan, `konteks_data`, jawaban, model, status, waktu respons ke `riwayat_ai`.
- Instruksi sistem: jawab dalam bahasa Indonesia, hanya berdasarkan data yang diberikan, katakan tidak tahu bila data tidak ada.

## 6. Relasi (ERD Crow's Foot)

| Induk | Relasi | Anak | Kardinalitas |
|---|---|---|---|
| `kategori_barang` | mengelompokkan | `barang` | 1 : N |
| `supplier` | memasok | `barang_masuk` | 1 : N |
| `users` | mencatat | `barang_masuk` | 1 : N |
| `barang_masuk` | berisi | `detail_barang_masuk` | 1 : N (min 1) |
| `barang` | dibeli dalam | `detail_barang_masuk` | 1 : N |
| `users` | melayani | `penjualan` | 1 : N |
| `penjualan` | berisi | `detail_penjualan` | 1 : N (min 1) |
| `barang` | terjual dalam | `detail_penjualan` | 1 : N |
| `users` | melaksanakan | `stok_opname` | 1 : N |
| `users` | menyetujui | `stok_opname` | 0..1 : N |
| `stok_opname` | berisi | `detail_stok_opname` | 1 : N (min 1) |
| `barang` | dihitung dalam | `detail_stok_opname` | 1 : N |
| `barang` | memiliki mutasi | `kartu_stok` | 1 : N |
| `barang_masuk` | menghasilkan | `kartu_stok` | 0..1 : N |
| `penjualan` | menghasilkan | `kartu_stok` | 0..1 : N |
| `stok_opname` | menghasilkan | `kartu_stok` | 0..1 : N |
| `users` | memicu | `kartu_stok` | 1 : N |
| `barang` | memicu | `notifikasi_stok` | 1 : N |
| `users` | membaca | `notifikasi_stok` | 0..1 : N |
| `kategori_pengeluaran` | mengelompokkan | `pengeluaran_operasional` | 1 : N |
| `users` | mencatat | `pengeluaran_operasional` | 1 : N |
| `penjualan` | dicatat sebagai | `kas` | 1 : 1 (opsional) |
| `barang_masuk` | dicatat sebagai | `kas` | 1 : 1 (opsional) |
| `pengeluaran_operasional` | dicatat sebagai | `kas` | 1 : 1 (opsional) |
| `validasi_kas` | disesuaikan dengan | `kas` | 1 : 1 (opsional) |
| `users` | mencatat | `kas` | 1 : N |
| `users` | memvalidasi | `validasi_kas` | 1 : N |
| `barang` | dihitung HPP-nya | `hpp_periode` | 1 : N |
| `users` | menjalankan | `hpp_periode` | 1 : N |
| `users` | bertanya | `riwayat_ai` | 1 : N |

## 7. Struktur tabel

Database `db_monitoring_stok_keuangan`, InnoDB, `utf8mb4_unicode_ci`. Primary key bernama `id_<tabel>`. Semua tabel punya `created_at`/`updated_at` (`$table->timestamps()`).

### `users`

Akun pengguna sistem dengan dua level hak akses: Administrator (pengelola toko, akses penuh) dan Kasir (transaksi penjualan dan pengelolaan stok).

| Kolom | Tipe MySQL | Key | Null | Default | Migration Laravel | Keterangan |
|---|---|---|---|---|---|---|
| `id_user` | `BIGINT UNSIGNED` | PK | Tidak | AUTO_INCREMENT | `$table->id('id_user')` | Kode unik pengguna |
| `nama` | `VARCHAR(100)` |  | Tidak | – | `$table->string('nama', 100)` | Nama lengkap pengguna |
| `username` | `VARCHAR(50)` | UK | Tidak | – | `$table->string('username', 50)->unique()` | Nama pengguna untuk login, unik |
| `email` | `VARCHAR(100)` | UK | Ya | NULL | `$table->string('email', 100)->nullable()->unique()` | Email pengguna (opsional) |
| `password` | `VARCHAR(255)` |  | Tidak | – | `$table->string('password', 255)` | Kata sandi ter-hash (bcrypt) |
| `role` | `ENUM('administrator','kasir')` |  | Tidak | 'kasir' | `$table->enum('role', ['administrator','kasir'])->default('kasir')` | Level hak akses pengguna |
| `status` | `ENUM('aktif','nonaktif')` |  | Tidak | 'aktif' | `$table->enum('status', ['aktif','nonaktif'])->default('aktif')` | Akun nonaktif tidak dapat login |
| `remember_token` | `VARCHAR(100)` |  | Ya | NULL | `$table->string('remember_token', 100)->nullable()` | Token 'ingat saya' bawaan Laravel |
| `terakhir_login` | `DATETIME` |  | Ya | NULL | `$table->dateTime('terakhir_login')->nullable()` | Waktu login terakhir |

### `kategori_barang` _(asumsi)_

Pengelompokan barang dagangan (misalnya makanan ringan, minuman, alat tulis) untuk memudahkan pencarian dan laporan.

| Kolom | Tipe MySQL | Key | Null | Default | Migration Laravel | Keterangan |
|---|---|---|---|---|---|---|
| `id_kategori` | `INT UNSIGNED` | PK | Tidak | AUTO_INCREMENT | `$table->increments('id_kategori')` | Kode unik kategori |
| `nama_kategori` | `VARCHAR(100)` | UK | Tidak | – | `$table->string('nama_kategori', 100)->unique()` | Nama kategori barang |
| `keterangan` | `VARCHAR(255)` |  | Ya | NULL | `$table->string('keterangan', 255)->nullable()` | Keterangan tambahan |

### `supplier`

Pemasok barang. Data supplier diinput oleh Administrator dan menjadi asal setiap transaksi barang masuk.

| Kolom | Tipe MySQL | Key | Null | Default | Migration Laravel | Keterangan |
|---|---|---|---|---|---|---|
| `id_supplier` | `INT UNSIGNED` | PK | Tidak | AUTO_INCREMENT | `$table->increments('id_supplier')` | Kode unik supplier |
| `kode_supplier` | `VARCHAR(20)` | UK | Tidak | – | `$table->string('kode_supplier', 20)->unique()` | Kode supplier, contoh SUP-001 |
| `nama_supplier` | `VARCHAR(100)` |  | Tidak | – | `$table->string('nama_supplier', 100)` | Nama supplier atau toko grosir |
| `nama_kontak` | `VARCHAR(100)` |  | Ya | NULL | `$table->string('nama_kontak', 100)->nullable()` | Nama orang yang dapat dihubungi |
| `no_telepon` | `VARCHAR(20)` |  | Ya | NULL | `$table->string('no_telepon', 20)->nullable()` | Nomor telepon/WhatsApp |
| `alamat` | `TEXT` |  | Ya | NULL | `$table->text('alamat')->nullable()` | Alamat supplier |
| `status` | `ENUM('aktif','nonaktif')` |  | Tidak | 'aktif' | `$table->enum('status', ['aktif','nonaktif'])->default('aktif')` | Supplier nonaktif tidak muncul di form barang masuk |

### `barang`

Data barang dagangan beserta posisi stok terkini, batas stok minimum, harga jual, dan HPP rata-rata per unit. Kolom stok selalu diperbarui otomatis oleh transaksi, bukan diketik manual. Kasir boleh menambah dan mengubah data barang, tetapi harga jual hanya dapat diubah Administrator.

| Kolom | Tipe MySQL | Key | Null | Default | Migration Laravel | Keterangan |
|---|---|---|---|---|---|---|
| `id_barang` | `BIGINT UNSIGNED` | PK | Tidak | AUTO_INCREMENT | `$table->id('id_barang')` | Kode unik barang |
| `kode_barang` | `VARCHAR(30)` | UK | Tidak | – | `$table->string('kode_barang', 30)->unique()` | Kode/SKU atau barcode barang |
| `id_kategori` | `INT UNSIGNED` | FK | Tidak | – | `$table->unsignedInteger('id_kategori'); $table->foreign('id_kategori')->references('id_kategori')->on('kategori_barang')->cascadeOnUpdate()->restrictOnDelete()` | Kategori barang, ref. kategori_barang → `kategori_barang.id_kategori` ON DELETE RESTRICT |
| `nama_barang` | `VARCHAR(150)` |  | Tidak | – | `$table->string('nama_barang', 150)` | Nama barang |
| `satuan` | `VARCHAR(20)` |  | Tidak | 'pcs' | `$table->string('satuan', 20)->default('pcs')` | Satuan jual: pcs, bungkus, botol, dus |
| `harga_beli_terakhir` | `DECIMAL(15,2)` |  | Tidak | 0 | `$table->decimal('harga_beli_terakhir', 15, 2)->default(0)` | Harga beli per unit pada barang masuk terakhir |
| `hpp_rata_rata` | `DECIMAL(15,2)` |  | Tidak | 0 | `$table->decimal('hpp_rata_rata', 15, 2)->default(0)` | HPP per unit metode rata-rata tertimbang, dihitung otomatis |
| `harga_jual` | `DECIMAL(15,2)` |  | Tidak | 0 | `$table->decimal('harga_jual', 15, 2)->default(0)` | Harga jual per unit |
| `stok` | `INT` |  | Tidak | 0 | `$table->integer('stok')->default(0)` | Stok terkini (real-time), tidak boleh negatif |
| `stok_minimum` | `INT` |  | Tidak | 5 | `$table->integer('stok_minimum')->default(5)` | Batas peringatan stok minimum |
| `status` | `ENUM('aktif','nonaktif')` |  | Tidak | 'aktif' | `$table->enum('status', ['aktif','nonaktif'])->default('aktif')` | Barang nonaktif tidak bisa dijual |

### `barang_masuk`

Header transaksi barang masuk dari supplier (satu faktur/nota pembelian).

| Kolom | Tipe MySQL | Key | Null | Default | Migration Laravel | Keterangan |
|---|---|---|---|---|---|---|
| `id_barang_masuk` | `BIGINT UNSIGNED` | PK | Tidak | AUTO_INCREMENT | `$table->id('id_barang_masuk')` | Kode unik transaksi barang masuk |
| `kode_barang_masuk` | `VARCHAR(30)` | UK | Tidak | – | `$table->string('kode_barang_masuk', 30)->unique()` | Nomor otomatis, contoh BM-20261123-001 |
| `no_faktur` | `VARCHAR(50)` |  | Ya | NULL | `$table->string('no_faktur', 50)->nullable()` | Nomor nota/faktur dari supplier |
| `id_supplier` | `INT UNSIGNED` | FK | Tidak | – | `$table->unsignedInteger('id_supplier'); $table->foreign('id_supplier')->references('id_supplier')->on('supplier')->cascadeOnUpdate()->restrictOnDelete()` | Supplier asal barang, ref. supplier → `supplier.id_supplier` ON DELETE RESTRICT |
| `id_user` | `BIGINT UNSIGNED` | FK | Tidak | – | `$table->foreignId('id_user')->constrained('users', 'id_user')->cascadeOnUpdate()->restrictOnDelete()` | Pengguna (Administrator/Kasir) yang mencatat, ref. users → `users.id_user` ON DELETE RESTRICT |
| `tanggal_masuk` | `DATE` |  | Tidak | – | `$table->date('tanggal_masuk')` | Tanggal barang diterima |
| `total_pembelian` | `DECIMAL(15,2)` |  | Tidak | 0 | `$table->decimal('total_pembelian', 15, 2)->default(0)` | Jumlah seluruh subtotal detail |
| `metode_pembayaran` | `ENUM('tunai','transfer')` |  | Tidak | 'tunai' | `$table->enum('metode_pembayaran', ['tunai','transfer'])->default('tunai')` | Cara pembayaran ke supplier |
| `keterangan` | `VARCHAR(255)` |  | Ya | NULL | `$table->string('keterangan', 255)->nullable()` | Catatan tambahan |

### `detail_barang_masuk`

Rincian barang per transaksi barang masuk. Tabel ini memecah relasi banyak-ke-banyak antara barang_masuk dan barang.

| Kolom | Tipe MySQL | Key | Null | Default | Migration Laravel | Keterangan |
|---|---|---|---|---|---|---|
| `id_detail_masuk` | `BIGINT UNSIGNED` | PK | Tidak | AUTO_INCREMENT | `$table->id('id_detail_masuk')` | Kode unik baris detail |
| `id_barang_masuk` | `BIGINT UNSIGNED` | FK | Tidak | – | `$table->foreignId('id_barang_masuk')->constrained('barang_masuk', 'id_barang_masuk')->cascadeOnUpdate()->cascadeOnDelete()` | Header transaksi, ref. barang_masuk → `barang_masuk.id_barang_masuk` ON DELETE CASCADE |
| `id_barang` | `BIGINT UNSIGNED` | FK | Tidak | – | `$table->foreignId('id_barang')->constrained('barang', 'id_barang')->cascadeOnUpdate()->restrictOnDelete()` | Barang yang masuk, ref. barang → `barang.id_barang` ON DELETE RESTRICT |
| `jumlah` | `INT` |  | Tidak | – | `$table->integer('jumlah')` | Jumlah unit masuk, > 0 |
| `harga_beli` | `DECIMAL(15,2)` |  | Tidak | – | `$table->decimal('harga_beli', 15, 2)` | Harga beli per unit |
| `subtotal` | `DECIMAL(15,2)` |  | Tidak | – | `$table->decimal('subtotal', 15, 2)` | jumlah × harga_beli |
| `tanggal_kadaluarsa` | `DATE` |  | Ya | NULL | `$table->date('tanggal_kadaluarsa')->nullable()` | Tanggal kedaluwarsa (opsional) |

### `penjualan`

Header transaksi penjualan yang diinput oleh Kasir (atau Administrator). Menyimpan total pendapatan dan total HPP transaksi.

| Kolom | Tipe MySQL | Key | Null | Default | Migration Laravel | Keterangan |
|---|---|---|---|---|---|---|
| `id_penjualan` | `BIGINT UNSIGNED` | PK | Tidak | AUTO_INCREMENT | `$table->id('id_penjualan')` | Kode unik transaksi penjualan |
| `no_transaksi` | `VARCHAR(30)` | UK | Tidak | – | `$table->string('no_transaksi', 30)->unique()` | Nomor nota otomatis, contoh TRX-20261123-0001 |
| `id_user` | `BIGINT UNSIGNED` | FK | Tidak | – | `$table->foreignId('id_user')->constrained('users', 'id_user')->cascadeOnUpdate()->restrictOnDelete()` | Kasir yang melayani, ref. users → `users.id_user` ON DELETE RESTRICT |
| `tanggal_penjualan` | `DATETIME` |  | Tidak | – | `$table->dateTime('tanggal_penjualan')` | Tanggal dan jam transaksi |
| `total_item` | `INT` |  | Tidak | 0 | `$table->integer('total_item')->default(0)` | Jumlah unit barang terjual |
| `total_harga` | `DECIMAL(15,2)` |  | Tidak | 0 | `$table->decimal('total_harga', 15, 2)->default(0)` | Total pendapatan transaksi |
| `total_hpp` | `DECIMAL(15,2)` |  | Tidak | 0 | `$table->decimal('total_hpp', 15, 2)->default(0)` | Total HPP transaksi (Σ subtotal_hpp) |
| `metode_pembayaran` | `ENUM('tunai','qris','transfer')` |  | Tidak | 'tunai' | `$table->enum('metode_pembayaran', ['tunai','qris','transfer'])->default('tunai')` | Cara pembayaran pelanggan |
| `jumlah_bayar` | `DECIMAL(15,2)` |  | Tidak | 0 | `$table->decimal('jumlah_bayar', 15, 2)->default(0)` | Uang yang dibayarkan pelanggan |
| `kembalian` | `DECIMAL(15,2)` |  | Tidak | 0 | `$table->decimal('kembalian', 15, 2)->default(0)` | jumlah_bayar − total_harga |
| `status` | `ENUM('selesai','batal')` |  | Tidak | 'selesai' | `$table->enum('status', ['selesai','batal'])->default('selesai')` | Transaksi batal tidak dihitung di laporan |
| `keterangan` | `VARCHAR(255)` |  | Ya | NULL | `$table->string('keterangan', 255)->nullable()` | Catatan, misalnya alasan pembatalan |

### `detail_penjualan`

Rincian barang per transaksi penjualan (barang keluar). Harga jual dan HPP disalin saat transaksi agar laporan lama tidak berubah ketika harga diperbarui.

| Kolom | Tipe MySQL | Key | Null | Default | Migration Laravel | Keterangan |
|---|---|---|---|---|---|---|
| `id_detail_penjualan` | `BIGINT UNSIGNED` | PK | Tidak | AUTO_INCREMENT | `$table->id('id_detail_penjualan')` | Kode unik baris detail |
| `id_penjualan` | `BIGINT UNSIGNED` | FK | Tidak | – | `$table->foreignId('id_penjualan')->constrained('penjualan', 'id_penjualan')->cascadeOnUpdate()->cascadeOnDelete()` | Header transaksi, ref. penjualan → `penjualan.id_penjualan` ON DELETE CASCADE |
| `id_barang` | `BIGINT UNSIGNED` | FK | Tidak | – | `$table->foreignId('id_barang')->constrained('barang', 'id_barang')->cascadeOnUpdate()->restrictOnDelete()` | Barang yang dijual, ref. barang → `barang.id_barang` ON DELETE RESTRICT |
| `jumlah` | `INT` |  | Tidak | – | `$table->integer('jumlah')` | Jumlah unit terjual, > 0 |
| `harga_jual` | `DECIMAL(15,2)` |  | Tidak | – | `$table->decimal('harga_jual', 15, 2)` | Harga jual per unit saat transaksi |
| `hpp_satuan` | `DECIMAL(15,2)` |  | Tidak | – | `$table->decimal('hpp_satuan', 15, 2)` | HPP rata-rata per unit saat transaksi |
| `subtotal` | `DECIMAL(15,2)` |  | Tidak | – | `$table->decimal('subtotal', 15, 2)` | jumlah × harga_jual |
| `subtotal_hpp` | `DECIMAL(15,2)` |  | Tidak | – | `$table->decimal('subtotal_hpp', 15, 2)` | jumlah × hpp_satuan |

### `stok_opname` _(asumsi)_

Header kegiatan pencocokan stok sistem dengan stok fisik di rak. Hasil hitung dapat diinput Kasir, tetapi penyesuaian stok baru berlaku setelah disetujui Administrator. Latar belakang laporan menyebut stok opname belum rutin karena belum ada sistem pendukung.

| Kolom | Tipe MySQL | Key | Null | Default | Migration Laravel | Keterangan |
|---|---|---|---|---|---|---|
| `id_stok_opname` | `BIGINT UNSIGNED` | PK | Tidak | AUTO_INCREMENT | `$table->id('id_stok_opname')` | Kode unik stok opname |
| `kode_opname` | `VARCHAR(30)` | UK | Tidak | – | `$table->string('kode_opname', 30)->unique()` | Nomor otomatis, contoh SO-202611-01 |
| `id_user` | `BIGINT UNSIGNED` | FK | Tidak | – | `$table->foreignId('id_user')->constrained('users', 'id_user')->cascadeOnUpdate()->restrictOnDelete()` | Pengguna (Administrator/Kasir) yang menginput hasil hitung, ref. users → `users.id_user` ON DELETE RESTRICT |
| `tanggal_opname` | `DATE` |  | Tidak | – | `$table->date('tanggal_opname')` | Tanggal pelaksanaan |
| `status` | `ENUM('draft','diajukan','final')` |  | Tidak | 'draft' | `$table->enum('status', ['draft','diajukan','final'])->default('draft')` | draft → diajukan (oleh Kasir) → final (disetujui Administrator); stok disesuaikan saat final |
| `disetujui_oleh` | `BIGINT UNSIGNED` | FK | Ya | NULL | `$table->foreignId('disetujui_oleh')->nullable()->constrained('users', 'id_user')->cascadeOnUpdate()->nullOnDelete()` | Administrator yang menyetujui, ref. users → `users.id_user` ON DELETE SET NULL |
| `disetujui_pada` | `DATETIME` |  | Ya | NULL | `$table->dateTime('disetujui_pada')->nullable()` | Waktu persetujuan |
| `keterangan` | `VARCHAR(255)` |  | Ya | NULL | `$table->string('keterangan', 255)->nullable()` | Catatan |

### `detail_stok_opname` _(asumsi)_

Hasil hitung fisik per barang beserta selisihnya terhadap stok sistem.

| Kolom | Tipe MySQL | Key | Null | Default | Migration Laravel | Keterangan |
|---|---|---|---|---|---|---|
| `id_detail_opname` | `BIGINT UNSIGNED` | PK | Tidak | AUTO_INCREMENT | `$table->id('id_detail_opname')` | Kode unik baris detail |
| `id_stok_opname` | `BIGINT UNSIGNED` | FK | Tidak | – | `$table->foreignId('id_stok_opname')->constrained('stok_opname', 'id_stok_opname')->cascadeOnUpdate()->cascadeOnDelete()` | Header opname, ref. stok_opname → `stok_opname.id_stok_opname` ON DELETE CASCADE |
| `id_barang` | `BIGINT UNSIGNED` | FK | Tidak | – | `$table->foreignId('id_barang')->constrained('barang', 'id_barang')->cascadeOnUpdate()->restrictOnDelete()` | Barang yang dihitung, ref. barang → `barang.id_barang` ON DELETE RESTRICT |
| `stok_sistem` | `INT` |  | Tidak | – | `$table->integer('stok_sistem')` | Stok menurut sistem saat opname |
| `stok_fisik` | `INT` |  | Tidak | – | `$table->integer('stok_fisik')` | Stok hasil hitung fisik, ≥ 0 |
| `selisih` | `INT` |  | Tidak | – | `$table->integer('selisih')` | stok_fisik − stok_sistem |
| `keterangan` | `VARCHAR(255)` |  | Ya | NULL | `$table->string('keterangan', 255)->nullable()` | Penyebab selisih: rusak, hilang, kedaluwarsa |

### `kartu_stok`

Log setiap perubahan stok (masuk, keluar, penyesuaian). Menjadi sumber Laporan Pergerakan Stok dan jejak audit stok.

| Kolom | Tipe MySQL | Key | Null | Default | Migration Laravel | Keterangan |
|---|---|---|---|---|---|---|
| `id_kartu_stok` | `BIGINT UNSIGNED` | PK | Tidak | AUTO_INCREMENT | `$table->id('id_kartu_stok')` | Kode unik mutasi |
| `id_barang` | `BIGINT UNSIGNED` | FK | Tidak | – | `$table->foreignId('id_barang')->constrained('barang', 'id_barang')->cascadeOnUpdate()->restrictOnDelete()` | Barang yang bergerak, ref. barang → `barang.id_barang` ON DELETE RESTRICT |
| `tanggal` | `DATETIME` |  | Tidak | – | `$table->dateTime('tanggal')` | Waktu mutasi |
| `jenis_mutasi` | `ENUM('masuk','keluar','penyesuaian')` |  | Tidak | – | `$table->enum('jenis_mutasi', ['masuk','keluar','penyesuaian'])` | Jenis pergerakan stok |
| `id_barang_masuk` | `BIGINT UNSIGNED` | FK | Ya | NULL | `$table->foreignId('id_barang_masuk')->nullable()->constrained('barang_masuk', 'id_barang_masuk')->cascadeOnUpdate()->restrictOnDelete()` | Sumber mutasi bila dari barang masuk → `barang_masuk.id_barang_masuk` ON DELETE RESTRICT |
| `id_penjualan` | `BIGINT UNSIGNED` | FK | Ya | NULL | `$table->foreignId('id_penjualan')->nullable()->constrained('penjualan', 'id_penjualan')->cascadeOnUpdate()->restrictOnDelete()` | Sumber mutasi bila dari penjualan/pembatalan → `penjualan.id_penjualan` ON DELETE RESTRICT |
| `id_stok_opname` | `BIGINT UNSIGNED` | FK | Ya | NULL | `$table->foreignId('id_stok_opname')->nullable()->constrained('stok_opname', 'id_stok_opname')->cascadeOnUpdate()->restrictOnDelete()` | Sumber mutasi bila dari stok opname → `stok_opname.id_stok_opname` ON DELETE RESTRICT |
| `jumlah_masuk` | `INT` |  | Tidak | 0 | `$table->integer('jumlah_masuk')->default(0)` | Unit bertambah |
| `jumlah_keluar` | `INT` |  | Tidak | 0 | `$table->integer('jumlah_keluar')->default(0)` | Unit berkurang |
| `stok_sebelum` | `INT` |  | Tidak | – | `$table->integer('stok_sebelum')` | Stok sebelum mutasi |
| `stok_sesudah` | `INT` |  | Tidak | – | `$table->integer('stok_sesudah')` | Stok sesudah mutasi |
| `hpp_satuan` | `DECIMAL(15,2)` |  | Tidak | 0 | `$table->decimal('hpp_satuan', 15, 2)->default(0)` | HPP rata-rata per unit setelah mutasi |
| `keterangan` | `VARCHAR(255)` |  | Ya | NULL | `$table->string('keterangan', 255)->nullable()` | Uraian mutasi |
| `id_user` | `BIGINT UNSIGNED` | FK | Tidak | – | `$table->foreignId('id_user')->constrained('users', 'id_user')->cascadeOnUpdate()->restrictOnDelete()` | Pengguna yang memicu mutasi, ref. users → `users.id_user` ON DELETE RESTRICT |

### `notifikasi_stok`

Peringatan otomatis yang dibuat sistem ketika stok barang turun sampai atau di bawah stok minimum.

| Kolom | Tipe MySQL | Key | Null | Default | Migration Laravel | Keterangan |
|---|---|---|---|---|---|---|
| `id_notifikasi` | `BIGINT UNSIGNED` | PK | Tidak | AUTO_INCREMENT | `$table->id('id_notifikasi')` | Kode unik notifikasi |
| `id_barang` | `BIGINT UNSIGNED` | FK | Tidak | – | `$table->foreignId('id_barang')->constrained('barang', 'id_barang')->cascadeOnUpdate()->cascadeOnDelete()` | Barang yang stoknya menipis, ref. barang → `barang.id_barang` ON DELETE CASCADE |
| `stok_saat_ini` | `INT` |  | Tidak | – | `$table->integer('stok_saat_ini')` | Stok saat notifikasi dibuat |
| `stok_minimum` | `INT` |  | Tidak | – | `$table->integer('stok_minimum')` | Batas minimum saat notifikasi dibuat |
| `pesan` | `VARCHAR(255)` |  | Tidak | – | `$table->string('pesan', 255)` | Isi peringatan |
| `status` | `ENUM('baru','dibaca','selesai')` |  | Tidak | 'baru' | `$table->enum('status', ['baru','dibaca','selesai'])->default('baru')` | selesai = stok sudah diisi ulang di atas minimum |
| `dibaca_oleh` | `BIGINT UNSIGNED` | FK | Ya | NULL | `$table->foreignId('dibaca_oleh')->nullable()->constrained('users', 'id_user')->cascadeOnUpdate()->nullOnDelete()` | Pengguna (Administrator/Kasir) yang membaca, ref. users → `users.id_user` ON DELETE SET NULL |
| `dibaca_pada` | `DATETIME` |  | Ya | NULL | `$table->dateTime('dibaca_pada')->nullable()` | Waktu dibaca |

### `kategori_pengeluaran` _(asumsi)_

Jenis beban operasional, misalnya listrik, air, sewa tempat, transportasi, kemasan, dan lain-lain. Menjadi rincian beban di laporan laba rugi.

| Kolom | Tipe MySQL | Key | Null | Default | Migration Laravel | Keterangan |
|---|---|---|---|---|---|---|
| `id_kategori_pengeluaran` | `INT UNSIGNED` | PK | Tidak | AUTO_INCREMENT | `$table->increments('id_kategori_pengeluaran')` | Kode unik kategori |
| `nama_kategori` | `VARCHAR(100)` | UK | Tidak | – | `$table->string('nama_kategori', 100)->unique()` | Nama kategori beban |
| `keterangan` | `VARCHAR(255)` |  | Ya | NULL | `$table->string('keterangan', 255)->nullable()` | Keterangan tambahan |

### `pengeluaran_operasional`

Pencatatan beban operasional toko di luar pembelian barang dagangan.

| Kolom | Tipe MySQL | Key | Null | Default | Migration Laravel | Keterangan |
|---|---|---|---|---|---|---|
| `id_pengeluaran` | `BIGINT UNSIGNED` | PK | Tidak | AUTO_INCREMENT | `$table->id('id_pengeluaran')` | Kode unik pengeluaran |
| `kode_pengeluaran` | `VARCHAR(30)` | UK | Tidak | – | `$table->string('kode_pengeluaran', 30)->unique()` | Nomor otomatis, contoh OP-20261123-01 |
| `id_kategori_pengeluaran` | `INT UNSIGNED` | FK | Tidak | – | `$table->unsignedInteger('id_kategori_pengeluaran'); $table->foreign('id_kategori_pengeluaran')->references('id_kategori_pengeluaran')->on('kategori_pengeluaran')->cascadeOnUpdate()->restrictOnDelete()` | Jenis beban, ref. kategori_pengeluaran → `kategori_pengeluaran.id_kategori_pengeluaran` ON DELETE RESTRICT |
| `id_user` | `BIGINT UNSIGNED` | FK | Tidak | – | `$table->foreignId('id_user')->constrained('users', 'id_user')->cascadeOnUpdate()->restrictOnDelete()` | Administrator pencatat, ref. users → `users.id_user` ON DELETE RESTRICT |
| `tanggal_pengeluaran` | `DATE` |  | Tidak | – | `$table->date('tanggal_pengeluaran')` | Tanggal pengeluaran |
| `deskripsi` | `VARCHAR(255)` |  | Tidak | – | `$table->string('deskripsi', 255)` | Uraian pengeluaran |
| `jumlah` | `DECIMAL(15,2)` |  | Tidak | – | `$table->decimal('jumlah', 15, 2)` | Nominal pengeluaran, > 0 |
| `metode_pembayaran` | `ENUM('tunai','transfer')` |  | Tidak | 'tunai' | `$table->enum('metode_pembayaran', ['tunai','transfer'])->default('tunai')` | Cara pembayaran |
| `bukti` | `VARCHAR(255)` |  | Ya | NULL | `$table->string('bukti', 255)->nullable()` | Path file foto nota (opsional) |

### `validasi_kas` _(asumsi)_

Pencocokan harian antara total penjualan tunai di sistem dengan uang tunai fisik di laci kasir, untuk mendeteksi selisih yang sebelumnya tidak terdeteksi.

| Kolom | Tipe MySQL | Key | Null | Default | Migration Laravel | Keterangan |
|---|---|---|---|---|---|---|
| `id_validasi_kas` | `BIGINT UNSIGNED` | PK | Tidak | AUTO_INCREMENT | `$table->id('id_validasi_kas')` | Kode unik validasi |
| `tanggal` | `DATE` | UK | Tidak | – | `$table->date('tanggal')->unique()` | Tanggal yang divalidasi, satu kali per hari |
| `total_tunai_sistem` | `DECIMAL(15,2)` |  | Tidak | – | `$table->decimal('total_tunai_sistem', 15, 2)` | Σ penjualan tunai berstatus selesai pada tanggal itu |
| `uang_fisik` | `DECIMAL(15,2)` |  | Tidak | – | `$table->decimal('uang_fisik', 15, 2)` | Uang tunai hasil hitung fisik |
| `selisih` | `DECIMAL(15,2)` |  | Tidak | – | `$table->decimal('selisih', 15, 2)` | uang_fisik − total_tunai_sistem |
| `status` | `ENUM('sesuai','selisih')` |  | Tidak | – | `$table->enum('status', ['sesuai','selisih'])` | sesuai bila selisih = 0 |
| `catatan` | `VARCHAR(255)` |  | Ya | NULL | `$table->string('catatan', 255)->nullable()` | Penjelasan bila ada selisih |
| `id_user` | `BIGINT UNSIGNED` | FK | Tidak | – | `$table->foreignId('id_user')->constrained('users', 'id_user')->cascadeOnUpdate()->restrictOnDelete()` | Administrator yang memvalidasi, ref. users → `users.id_user` ON DELETE RESTRICT |

### `kas`

Buku kas usaha. Setiap uang masuk dan keluar dicatat di sini, sebagian otomatis dari transaksi dan sebagian manual (modal, prive, penyesuaian). Kategori prive memisahkan keuangan usaha dari keuangan pribadi.

| Kolom | Tipe MySQL | Key | Null | Default | Migration Laravel | Keterangan |
|---|---|---|---|---|---|---|
| `id_kas` | `BIGINT UNSIGNED` | PK | Tidak | AUTO_INCREMENT | `$table->id('id_kas')` | Kode unik baris kas |
| `tanggal` | `DATETIME` |  | Tidak | – | `$table->dateTime('tanggal')` | Tanggal dan jam arus kas |
| `jenis` | `ENUM('masuk','keluar')` |  | Tidak | – | `$table->enum('jenis', ['masuk','keluar'])` | Arah arus kas |
| `kategori` | `ENUM('penjualan','pembelian_barang','operasional','modal','prive','penyesuaian')` |  | Tidak | – | `$table->enum('kategori', ['penjualan','pembelian_barang','operasional','modal','prive','penyesuaian'])` | Sumber/tujuan arus kas |
| `id_penjualan` | `BIGINT UNSIGNED` | FK, UK | Ya | NULL | `$table->foreignId('id_penjualan')->nullable()->unique()->constrained('penjualan', 'id_penjualan')->cascadeOnUpdate()->cascadeOnDelete()` | Diisi bila kategori penjualan → `penjualan.id_penjualan` ON DELETE CASCADE |
| `id_barang_masuk` | `BIGINT UNSIGNED` | FK, UK | Ya | NULL | `$table->foreignId('id_barang_masuk')->nullable()->unique()->constrained('barang_masuk', 'id_barang_masuk')->cascadeOnUpdate()->cascadeOnDelete()` | Diisi bila kategori pembelian_barang → `barang_masuk.id_barang_masuk` ON DELETE CASCADE |
| `id_pengeluaran` | `BIGINT UNSIGNED` | FK, UK | Ya | NULL | `$table->foreignId('id_pengeluaran')->nullable()->unique()->constrained('pengeluaran_operasional', 'id_pengeluaran')->cascadeOnUpdate()->cascadeOnDelete()` | Diisi bila kategori operasional → `pengeluaran_operasional.id_pengeluaran` ON DELETE CASCADE |
| `id_validasi_kas` | `BIGINT UNSIGNED` | FK, UK | Ya | NULL | `$table->foreignId('id_validasi_kas')->nullable()->unique()->constrained('validasi_kas', 'id_validasi_kas')->cascadeOnUpdate()->cascadeOnDelete()` | Diisi bila penyesuaian dari selisih validasi kas → `validasi_kas.id_validasi_kas` ON DELETE CASCADE |
| `jumlah` | `DECIMAL(15,2)` |  | Tidak | – | `$table->decimal('jumlah', 15, 2)` | Nominal, selalu positif |
| `keterangan` | `VARCHAR(255)` |  | Ya | NULL | `$table->string('keterangan', 255)->nullable()` | Uraian |
| `id_user` | `BIGINT UNSIGNED` | FK | Tidak | – | `$table->foreignId('id_user')->constrained('users', 'id_user')->cascadeOnUpdate()->restrictOnDelete()` | Pengguna pencatat, ref. users → `users.id_user` ON DELETE RESTRICT |

### `hpp_periode`

Hasil perhitungan HPP per produk per periode dengan rumus HPP = Persediaan Awal + Pembelian Bersih − Persediaan Akhir (Bab 2.6). Disimpan agar laporan laba rugi periode yang sudah ditutup tidak berubah.

| Kolom | Tipe MySQL | Key | Null | Default | Migration Laravel | Keterangan |
|---|---|---|---|---|---|---|
| `id_hpp` | `BIGINT UNSIGNED` | PK | Tidak | AUTO_INCREMENT | `$table->id('id_hpp')` | Kode unik perhitungan |
| `id_barang` | `BIGINT UNSIGNED` | FK | Tidak | – | `$table->foreignId('id_barang')->constrained('barang', 'id_barang')->cascadeOnUpdate()->restrictOnDelete()` | Barang yang dihitung, ref. barang → `barang.id_barang` ON DELETE RESTRICT |
| `periode_awal` | `DATE` |  | Tidak | – | `$table->date('periode_awal')` | Tanggal awal periode |
| `periode_akhir` | `DATE` |  | Tidak | – | `$table->date('periode_akhir')` | Tanggal akhir periode |
| `qty_persediaan_awal` | `INT` |  | Tidak | 0 | `$table->integer('qty_persediaan_awal')->default(0)` | Unit persediaan awal |
| `nilai_persediaan_awal` | `DECIMAL(15,2)` |  | Tidak | 0 | `$table->decimal('nilai_persediaan_awal', 15, 2)->default(0)` | Nilai rupiah persediaan awal |
| `qty_pembelian` | `INT` |  | Tidak | 0 | `$table->integer('qty_pembelian')->default(0)` | Unit pembelian dalam periode |
| `nilai_pembelian` | `DECIMAL(15,2)` |  | Tidak | 0 | `$table->decimal('nilai_pembelian', 15, 2)->default(0)` | Nilai pembelian bersih |
| `qty_persediaan_akhir` | `INT` |  | Tidak | 0 | `$table->integer('qty_persediaan_akhir')->default(0)` | Unit persediaan akhir |
| `nilai_persediaan_akhir` | `DECIMAL(15,2)` |  | Tidak | 0 | `$table->decimal('nilai_persediaan_akhir', 15, 2)->default(0)` | Nilai rupiah persediaan akhir |
| `total_hpp` | `DECIMAL(15,2)` |  | Tidak | 0 | `$table->decimal('total_hpp', 15, 2)->default(0)` | Awal + Pembelian − Akhir |
| `qty_terjual` | `INT` |  | Tidak | 0 | `$table->integer('qty_terjual')->default(0)` | Unit terjual dalam periode |
| `hpp_per_unit` | `DECIMAL(15,2)` |  | Tidak | 0 | `$table->decimal('hpp_per_unit', 15, 2)->default(0)` | total_hpp ÷ qty_terjual |
| `id_user` | `BIGINT UNSIGNED` | FK | Tidak | – | `$table->foreignId('id_user')->constrained('users', 'id_user')->cascadeOnUpdate()->restrictOnDelete()` | Administrator yang menjalankan perhitungan → `users.id_user` ON DELETE RESTRICT |

### `riwayat_ai`

Riwayat tanya-jawab Administrator dengan AI Assistant (Gemini API), termasuk ringkasan data yang dikirim sebagai konteks. Berguna untuk menampilkan riwayat percakapan dan sebagai bukti pengujian Black Box.

| Kolom | Tipe MySQL | Key | Null | Default | Migration Laravel | Keterangan |
|---|---|---|---|---|---|---|
| `id_riwayat_ai` | `BIGINT UNSIGNED` | PK | Tidak | AUTO_INCREMENT | `$table->id('id_riwayat_ai')` | Kode unik riwayat |
| `id_user` | `BIGINT UNSIGNED` | FK | Tidak | – | `$table->foreignId('id_user')->constrained('users', 'id_user')->cascadeOnUpdate()->restrictOnDelete()` | Administrator penanya, ref. users → `users.id_user` ON DELETE RESTRICT |
| `pertanyaan` | `TEXT` |  | Tidak | – | `$table->text('pertanyaan')` | Pertanyaan dalam bahasa sehari-hari |
| `topik` | `ENUM('stok','keuangan','umum')` |  | Tidak | 'umum' | `$table->enum('topik', ['stok','keuangan','umum'])->default('umum')` | Hasil klasifikasi pertanyaan |
| `konteks_data` | `JSON` |  | Ya | NULL | `$table->json('konteks_data')->nullable()` | Ringkasan data sistem yang dikirim ke Gemini |
| `jawaban` | `MEDIUMTEXT` |  | Ya | NULL | `$table->mediumText('jawaban')->nullable()` | Jawaban dari Gemini API |
| `model` | `VARCHAR(50)` |  | Tidak | – | `$table->string('model', 50)` | Nama model Gemini yang dipakai |
| `status` | `ENUM('berhasil','gagal')` |  | Tidak | 'berhasil' | `$table->enum('status', ['berhasil','gagal'])->default('berhasil')` | Status pemanggilan API |
| `pesan_error` | `VARCHAR(255)` |  | Ya | NULL | `$table->string('pesan_error', 255)->nullable()` | Pesan galat bila gagal |
| `waktu_respons_ms` | `INT UNSIGNED` |  | Ya | NULL | `$table->unsignedInteger('waktu_respons_ms')->nullable()` | Lama respons API dalam milidetik |

Constraint tambahan (CHECK, UNIQUE gabungan, index) lihat `skema_database.sql`.

## 8. Asumsi yang dipakai

A1 tabel kategori_barang · A2 HPP rata-rata tertimbang + HPP periodik rumus Bab 2.6 · A3 stok opname · A4 validasi kas harian ·
A5 kas modal/prive (pemisahan uang usaha dan pribadi) · A6 kategori pengeluaran · A7 supplier bukan entitas eksternal DFD ·
A8 pembelian lunas saat barang diterima · A9 metode bayar tunai/QRIS/transfer · A10 penjualan bisa dibatalkan Admin ·
A11 riwayat AI disimpan · A12 laporan laba rugi dan HPP per bulan · A13 cakupan "pengelolaan stok" Kasir seperti tabel hak akses.
