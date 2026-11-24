-- =====================================================================
-- Skema Basis Data
-- Sistem Informasi Monitoring Stok dan Keuangan Berbasis Web
-- pada UMKM Sekretariat DPRD Kabupaten Badung
-- DBMS   : MySQL 8.3 (InnoDB, utf8mb4) | Framework: Laravel 13
-- Catatan: dihasilkan dari rancangan ERD; di Laravel dapat dipecah
--          menjadi file migration dengan urutan tabel yang sama.
-- =====================================================================

CREATE DATABASE IF NOT EXISTS db_monitoring_stok_keuangan
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE db_monitoring_stok_keuangan;

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `riwayat_ai`;
DROP TABLE IF EXISTS `hpp_periode`;
DROP TABLE IF EXISTS `kas`;
DROP TABLE IF EXISTS `validasi_kas`;
DROP TABLE IF EXISTS `pengeluaran_operasional`;
DROP TABLE IF EXISTS `kategori_pengeluaran`;
DROP TABLE IF EXISTS `notifikasi_stok`;
DROP TABLE IF EXISTS `kartu_stok`;
DROP TABLE IF EXISTS `detail_stok_opname`;
DROP TABLE IF EXISTS `stok_opname`;
DROP TABLE IF EXISTS `detail_penjualan`;
DROP TABLE IF EXISTS `penjualan`;
DROP TABLE IF EXISTS `detail_barang_masuk`;
DROP TABLE IF EXISTS `barang_masuk`;
DROP TABLE IF EXISTS `barang`;
DROP TABLE IF EXISTS `supplier`;
DROP TABLE IF EXISTS `kategori_barang`;
DROP TABLE IF EXISTS `users`;
SET FOREIGN_KEY_CHECKS = 1;

-- D1 | Pengguna
-- Akun pengguna sistem dengan dua level hak akses: Administrator (pengelola toko, akses penuh) dan Kasir (transaksi penjualan dan pengelolaan stok).
CREATE TABLE `users` (
  `id_user` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Kode unik pengguna',
  `nama` VARCHAR(100) NOT NULL COMMENT 'Nama lengkap pengguna',
  `username` VARCHAR(50) NOT NULL COMMENT 'Nama pengguna untuk login, unik',
  `email` VARCHAR(100) NULL DEFAULT NULL COMMENT 'Email pengguna (opsional)',
  `password` VARCHAR(255) NOT NULL COMMENT 'Kata sandi ter-hash (bcrypt)',
  `role` ENUM('administrator','kasir') NOT NULL DEFAULT 'kasir' COMMENT 'Level hak akses pengguna',
  `status` ENUM('aktif','nonaktif') NOT NULL DEFAULT 'aktif' COMMENT 'Akun nonaktif tidak dapat login',
  `remember_token` VARCHAR(100) NULL DEFAULT NULL COMMENT 'Token ''ingat saya'' bawaan Laravel',
  `terakhir_login` DATETIME NULL DEFAULT NULL COMMENT 'Waktu login terakhir',
  `created_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'Waktu data dibuat (dikelola Laravel)',
  `updated_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'Waktu data terakhir diubah (dikelola Laravel)',
  PRIMARY KEY (`id_user`),
  UNIQUE KEY `uk_users_username` (`username`),
  UNIQUE KEY `uk_users_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Pengguna';

-- D2 | Kategori Barang
-- Pengelompokan barang dagangan (misalnya makanan ringan, minuman, alat tulis) untuk memudahkan pencarian dan laporan.
CREATE TABLE `kategori_barang` (
  `id_kategori` INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Kode unik kategori',
  `nama_kategori` VARCHAR(100) NOT NULL COMMENT 'Nama kategori barang',
  `keterangan` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Keterangan tambahan',
  `created_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'Waktu data dibuat (dikelola Laravel)',
  `updated_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'Waktu data terakhir diubah (dikelola Laravel)',
  PRIMARY KEY (`id_kategori`),
  UNIQUE KEY `uk_kategori_barang_nama_kategori` (`nama_kategori`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Kategori Barang';

-- D4 | Supplier
-- Pemasok barang. Data supplier diinput oleh Administrator dan menjadi asal setiap transaksi barang masuk.
CREATE TABLE `supplier` (
  `id_supplier` INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Kode unik supplier',
  `kode_supplier` VARCHAR(20) NOT NULL COMMENT 'Kode supplier, contoh SUP-001',
  `nama_supplier` VARCHAR(100) NOT NULL COMMENT 'Nama supplier atau toko grosir',
  `nama_kontak` VARCHAR(100) NULL DEFAULT NULL COMMENT 'Nama orang yang dapat dihubungi',
  `no_telepon` VARCHAR(20) NULL DEFAULT NULL COMMENT 'Nomor telepon/WhatsApp',
  `alamat` TEXT NULL DEFAULT NULL COMMENT 'Alamat supplier',
  `status` ENUM('aktif','nonaktif') NOT NULL DEFAULT 'aktif' COMMENT 'Supplier nonaktif tidak muncul di form barang masuk',
  `created_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'Waktu data dibuat (dikelola Laravel)',
  `updated_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'Waktu data terakhir diubah (dikelola Laravel)',
  PRIMARY KEY (`id_supplier`),
  UNIQUE KEY `uk_supplier_kode_supplier` (`kode_supplier`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Supplier';

-- D3 | Barang
-- Data barang dagangan beserta posisi stok terkini, batas stok minimum, harga jual, dan HPP rata-rata per unit. Kolom stok selalu diperbarui otomatis oleh transaksi, bukan diketik manual. Kasir boleh menambah dan mengubah data barang, tetapi harga jual hanya dapat diubah Administrator.
CREATE TABLE `barang` (
  `id_barang` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Kode unik barang',
  `kode_barang` VARCHAR(30) NOT NULL COMMENT 'Kode/SKU atau barcode barang',
  `id_kategori` INT UNSIGNED NOT NULL COMMENT 'Kategori barang, ref. kategori_barang',
  `nama_barang` VARCHAR(150) NOT NULL COMMENT 'Nama barang',
  `satuan` VARCHAR(20) NOT NULL DEFAULT 'pcs' COMMENT 'Satuan jual: pcs, bungkus, botol, dus',
  `harga_beli_terakhir` DECIMAL(15,2) NOT NULL DEFAULT 0 COMMENT 'Harga beli per unit pada barang masuk terakhir',
  `hpp_rata_rata` DECIMAL(15,2) NOT NULL DEFAULT 0 COMMENT 'HPP per unit metode rata-rata tertimbang, dihitung otomatis',
  `harga_jual` DECIMAL(15,2) NOT NULL DEFAULT 0 COMMENT 'Harga jual per unit',
  `stok` INT NOT NULL DEFAULT 0 COMMENT 'Stok terkini (real-time), tidak boleh negatif',
  `stok_minimum` INT NOT NULL DEFAULT 5 COMMENT 'Batas peringatan stok minimum',
  `status` ENUM('aktif','nonaktif') NOT NULL DEFAULT 'aktif' COMMENT 'Barang nonaktif tidak bisa dijual',
  `created_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'Waktu data dibuat (dikelola Laravel)',
  `updated_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'Waktu data terakhir diubah (dikelola Laravel)',
  PRIMARY KEY (`id_barang`),
  UNIQUE KEY `uk_barang_kode_barang` (`kode_barang`),
  CONSTRAINT chk_barang_stok CHECK (stok >= 0),
  CONSTRAINT chk_barang_stok_min CHECK (stok_minimum >= 0),
  CONSTRAINT chk_barang_harga CHECK (harga_jual >= 0 AND hpp_rata_rata >= 0),
  KEY idx_barang_nama (nama_barang),
  CONSTRAINT `fk_barang_id_kategori` FOREIGN KEY (`id_kategori`) REFERENCES `kategori_barang` (`id_kategori`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Barang';

-- D5 | Barang Masuk
-- Header transaksi barang masuk dari supplier (satu faktur/nota pembelian).
CREATE TABLE `barang_masuk` (
  `id_barang_masuk` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Kode unik transaksi barang masuk',
  `kode_barang_masuk` VARCHAR(30) NOT NULL COMMENT 'Nomor otomatis, contoh BM-20261123-001',
  `no_faktur` VARCHAR(50) NULL DEFAULT NULL COMMENT 'Nomor nota/faktur dari supplier',
  `id_supplier` INT UNSIGNED NOT NULL COMMENT 'Supplier asal barang, ref. supplier',
  `id_user` BIGINT UNSIGNED NOT NULL COMMENT 'Pengguna (Administrator/Kasir) yang mencatat, ref. users',
  `tanggal_masuk` DATE NOT NULL COMMENT 'Tanggal barang diterima',
  `total_pembelian` DECIMAL(15,2) NOT NULL DEFAULT 0 COMMENT 'Jumlah seluruh subtotal detail',
  `metode_pembayaran` ENUM('tunai','transfer') NOT NULL DEFAULT 'tunai' COMMENT 'Cara pembayaran ke supplier',
  `keterangan` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Catatan tambahan',
  `created_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'Waktu data dibuat (dikelola Laravel)',
  `updated_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'Waktu data terakhir diubah (dikelola Laravel)',
  PRIMARY KEY (`id_barang_masuk`),
  UNIQUE KEY `uk_barang_masuk_kode_barang_masuk` (`kode_barang_masuk`),
  KEY idx_bm_tanggal (tanggal_masuk),
  CONSTRAINT `fk_barang_masuk_id_supplier` FOREIGN KEY (`id_supplier`) REFERENCES `supplier` (`id_supplier`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_barang_masuk_id_user` FOREIGN KEY (`id_user`) REFERENCES `users` (`id_user`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Barang Masuk';

-- D5 | Detail Barang Masuk
-- Rincian barang per transaksi barang masuk. Tabel ini memecah relasi banyak-ke-banyak antara barang_masuk dan barang.
CREATE TABLE `detail_barang_masuk` (
  `id_detail_masuk` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Kode unik baris detail',
  `id_barang_masuk` BIGINT UNSIGNED NOT NULL COMMENT 'Header transaksi, ref. barang_masuk',
  `id_barang` BIGINT UNSIGNED NOT NULL COMMENT 'Barang yang masuk, ref. barang',
  `jumlah` INT NOT NULL COMMENT 'Jumlah unit masuk, > 0',
  `harga_beli` DECIMAL(15,2) NOT NULL COMMENT 'Harga beli per unit',
  `subtotal` DECIMAL(15,2) NOT NULL COMMENT 'jumlah × harga_beli',
  `tanggal_kadaluarsa` DATE NULL DEFAULT NULL COMMENT 'Tanggal kedaluwarsa (opsional)',
  `created_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'Waktu data dibuat (dikelola Laravel)',
  `updated_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'Waktu data terakhir diubah (dikelola Laravel)',
  PRIMARY KEY (`id_detail_masuk`),
  CONSTRAINT chk_dbm_jumlah CHECK (jumlah > 0),
  CONSTRAINT chk_dbm_harga CHECK (harga_beli >= 0),
  CONSTRAINT `fk_detail_barang_masuk_id_barang_masuk` FOREIGN KEY (`id_barang_masuk`) REFERENCES `barang_masuk` (`id_barang_masuk`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_detail_barang_masuk_id_barang` FOREIGN KEY (`id_barang`) REFERENCES `barang` (`id_barang`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Detail Barang Masuk';

-- D6 | Penjualan
-- Header transaksi penjualan yang diinput oleh Kasir (atau Administrator). Menyimpan total pendapatan dan total HPP transaksi.
CREATE TABLE `penjualan` (
  `id_penjualan` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Kode unik transaksi penjualan',
  `no_transaksi` VARCHAR(30) NOT NULL COMMENT 'Nomor nota otomatis, contoh TRX-20261123-0001',
  `id_user` BIGINT UNSIGNED NOT NULL COMMENT 'Kasir yang melayani, ref. users',
  `tanggal_penjualan` DATETIME NOT NULL COMMENT 'Tanggal dan jam transaksi',
  `total_item` INT NOT NULL DEFAULT 0 COMMENT 'Jumlah unit barang terjual',
  `total_harga` DECIMAL(15,2) NOT NULL DEFAULT 0 COMMENT 'Total pendapatan transaksi',
  `total_hpp` DECIMAL(15,2) NOT NULL DEFAULT 0 COMMENT 'Total HPP transaksi (Σ subtotal_hpp)',
  `metode_pembayaran` ENUM('tunai','qris','transfer') NOT NULL DEFAULT 'tunai' COMMENT 'Cara pembayaran pelanggan',
  `jumlah_bayar` DECIMAL(15,2) NOT NULL DEFAULT 0 COMMENT 'Uang yang dibayarkan pelanggan',
  `kembalian` DECIMAL(15,2) NOT NULL DEFAULT 0 COMMENT 'jumlah_bayar − total_harga',
  `status` ENUM('selesai','batal') NOT NULL DEFAULT 'selesai' COMMENT 'Transaksi batal tidak dihitung di laporan',
  `keterangan` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Catatan, misalnya alasan pembatalan',
  `created_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'Waktu data dibuat (dikelola Laravel)',
  `updated_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'Waktu data terakhir diubah (dikelola Laravel)',
  PRIMARY KEY (`id_penjualan`),
  UNIQUE KEY `uk_penjualan_no_transaksi` (`no_transaksi`),
  KEY idx_penjualan_tanggal (tanggal_penjualan),
  CONSTRAINT chk_penjualan_bayar CHECK (jumlah_bayar >= 0 AND kembalian >= 0),
  CONSTRAINT `fk_penjualan_id_user` FOREIGN KEY (`id_user`) REFERENCES `users` (`id_user`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Penjualan';

-- D6 | Detail Penjualan
-- Rincian barang per transaksi penjualan (barang keluar). Harga jual dan HPP disalin saat transaksi agar laporan lama tidak berubah ketika harga diperbarui.
CREATE TABLE `detail_penjualan` (
  `id_detail_penjualan` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Kode unik baris detail',
  `id_penjualan` BIGINT UNSIGNED NOT NULL COMMENT 'Header transaksi, ref. penjualan',
  `id_barang` BIGINT UNSIGNED NOT NULL COMMENT 'Barang yang dijual, ref. barang',
  `jumlah` INT NOT NULL COMMENT 'Jumlah unit terjual, > 0',
  `harga_jual` DECIMAL(15,2) NOT NULL COMMENT 'Harga jual per unit saat transaksi',
  `hpp_satuan` DECIMAL(15,2) NOT NULL COMMENT 'HPP rata-rata per unit saat transaksi',
  `subtotal` DECIMAL(15,2) NOT NULL COMMENT 'jumlah × harga_jual',
  `subtotal_hpp` DECIMAL(15,2) NOT NULL COMMENT 'jumlah × hpp_satuan',
  `created_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'Waktu data dibuat (dikelola Laravel)',
  `updated_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'Waktu data terakhir diubah (dikelola Laravel)',
  PRIMARY KEY (`id_detail_penjualan`),
  CONSTRAINT chk_dp_jumlah CHECK (jumlah > 0),
  CONSTRAINT `fk_detail_penjualan_id_penjualan` FOREIGN KEY (`id_penjualan`) REFERENCES `penjualan` (`id_penjualan`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_detail_penjualan_id_barang` FOREIGN KEY (`id_barang`) REFERENCES `barang` (`id_barang`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Detail Penjualan';

-- D9 | Stok Opname
-- Header kegiatan pencocokan stok sistem dengan stok fisik di rak. Hasil hitung dapat diinput Kasir, tetapi penyesuaian stok baru berlaku setelah disetujui Administrator. Latar belakang laporan menyebut stok opname belum rutin karena belum ada sistem pendukung.
CREATE TABLE `stok_opname` (
  `id_stok_opname` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Kode unik stok opname',
  `kode_opname` VARCHAR(30) NOT NULL COMMENT 'Nomor otomatis, contoh SO-202611-01',
  `id_user` BIGINT UNSIGNED NOT NULL COMMENT 'Pengguna (Administrator/Kasir) yang menginput hasil hitung, ref. users',
  `tanggal_opname` DATE NOT NULL COMMENT 'Tanggal pelaksanaan',
  `status` ENUM('draft','diajukan','final') NOT NULL DEFAULT 'draft' COMMENT 'draft → diajukan (oleh Kasir) → final (disetujui Administrator); stok disesuaikan saat final',
  `disetujui_oleh` BIGINT UNSIGNED NULL DEFAULT NULL COMMENT 'Administrator yang menyetujui, ref. users',
  `disetujui_pada` DATETIME NULL DEFAULT NULL COMMENT 'Waktu persetujuan',
  `keterangan` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Catatan',
  `created_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'Waktu data dibuat (dikelola Laravel)',
  `updated_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'Waktu data terakhir diubah (dikelola Laravel)',
  PRIMARY KEY (`id_stok_opname`),
  UNIQUE KEY `uk_stok_opname_kode_opname` (`kode_opname`),
  CONSTRAINT `fk_stok_opname_id_user` FOREIGN KEY (`id_user`) REFERENCES `users` (`id_user`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_stok_opname_disetujui_oleh` FOREIGN KEY (`disetujui_oleh`) REFERENCES `users` (`id_user`) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stok Opname';

-- D9 | Detail Stok Opname
-- Hasil hitung fisik per barang beserta selisihnya terhadap stok sistem.
CREATE TABLE `detail_stok_opname` (
  `id_detail_opname` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Kode unik baris detail',
  `id_stok_opname` BIGINT UNSIGNED NOT NULL COMMENT 'Header opname, ref. stok_opname',
  `id_barang` BIGINT UNSIGNED NOT NULL COMMENT 'Barang yang dihitung, ref. barang',
  `stok_sistem` INT NOT NULL COMMENT 'Stok menurut sistem saat opname',
  `stok_fisik` INT NOT NULL COMMENT 'Stok hasil hitung fisik, ≥ 0',
  `selisih` INT NOT NULL COMMENT 'stok_fisik − stok_sistem',
  `keterangan` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Penyebab selisih: rusak, hilang, kedaluwarsa',
  `created_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'Waktu data dibuat (dikelola Laravel)',
  `updated_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'Waktu data terakhir diubah (dikelola Laravel)',
  PRIMARY KEY (`id_detail_opname`),
  UNIQUE KEY uk_dso_barang (id_stok_opname, id_barang),
  CONSTRAINT chk_dso_fisik CHECK (stok_fisik >= 0),
  CONSTRAINT `fk_detail_stok_opname_id_stok_opname` FOREIGN KEY (`id_stok_opname`) REFERENCES `stok_opname` (`id_stok_opname`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_detail_stok_opname_id_barang` FOREIGN KEY (`id_barang`) REFERENCES `barang` (`id_barang`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Detail Stok Opname';

-- D7 | Kartu Stok (Pergerakan Stok)
-- Log setiap perubahan stok (masuk, keluar, penyesuaian). Menjadi sumber Laporan Pergerakan Stok dan jejak audit stok.
CREATE TABLE `kartu_stok` (
  `id_kartu_stok` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Kode unik mutasi',
  `id_barang` BIGINT UNSIGNED NOT NULL COMMENT 'Barang yang bergerak, ref. barang',
  `tanggal` DATETIME NOT NULL COMMENT 'Waktu mutasi',
  `jenis_mutasi` ENUM('masuk','keluar','penyesuaian') NOT NULL COMMENT 'Jenis pergerakan stok',
  `id_barang_masuk` BIGINT UNSIGNED NULL DEFAULT NULL COMMENT 'Sumber mutasi bila dari barang masuk',
  `id_penjualan` BIGINT UNSIGNED NULL DEFAULT NULL COMMENT 'Sumber mutasi bila dari penjualan/pembatalan',
  `id_stok_opname` BIGINT UNSIGNED NULL DEFAULT NULL COMMENT 'Sumber mutasi bila dari stok opname',
  `jumlah_masuk` INT NOT NULL DEFAULT 0 COMMENT 'Unit bertambah',
  `jumlah_keluar` INT NOT NULL DEFAULT 0 COMMENT 'Unit berkurang',
  `stok_sebelum` INT NOT NULL COMMENT 'Stok sebelum mutasi',
  `stok_sesudah` INT NOT NULL COMMENT 'Stok sesudah mutasi',
  `hpp_satuan` DECIMAL(15,2) NOT NULL DEFAULT 0 COMMENT 'HPP rata-rata per unit setelah mutasi',
  `keterangan` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Uraian mutasi',
  `id_user` BIGINT UNSIGNED NOT NULL COMMENT 'Pengguna yang memicu mutasi, ref. users',
  `created_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'Waktu data dibuat (dikelola Laravel)',
  `updated_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'Waktu data terakhir diubah (dikelola Laravel)',
  PRIMARY KEY (`id_kartu_stok`),
  KEY idx_ks_barang_tanggal (id_barang, tanggal),
  CONSTRAINT chk_ks_jumlah CHECK (jumlah_masuk >= 0 AND jumlah_keluar >= 0),
  CONSTRAINT `fk_kartu_stok_id_barang` FOREIGN KEY (`id_barang`) REFERENCES `barang` (`id_barang`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_kartu_stok_id_barang_masuk` FOREIGN KEY (`id_barang_masuk`) REFERENCES `barang_masuk` (`id_barang_masuk`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_kartu_stok_id_penjualan` FOREIGN KEY (`id_penjualan`) REFERENCES `penjualan` (`id_penjualan`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_kartu_stok_id_stok_opname` FOREIGN KEY (`id_stok_opname`) REFERENCES `stok_opname` (`id_stok_opname`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_kartu_stok_id_user` FOREIGN KEY (`id_user`) REFERENCES `users` (`id_user`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Kartu Stok (Pergerakan Stok)';

-- D8 | Notifikasi Stok Minimum
-- Peringatan otomatis yang dibuat sistem ketika stok barang turun sampai atau di bawah stok minimum.
CREATE TABLE `notifikasi_stok` (
  `id_notifikasi` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Kode unik notifikasi',
  `id_barang` BIGINT UNSIGNED NOT NULL COMMENT 'Barang yang stoknya menipis, ref. barang',
  `stok_saat_ini` INT NOT NULL COMMENT 'Stok saat notifikasi dibuat',
  `stok_minimum` INT NOT NULL COMMENT 'Batas minimum saat notifikasi dibuat',
  `pesan` VARCHAR(255) NOT NULL COMMENT 'Isi peringatan',
  `status` ENUM('baru','dibaca','selesai') NOT NULL DEFAULT 'baru' COMMENT 'selesai = stok sudah diisi ulang di atas minimum',
  `dibaca_oleh` BIGINT UNSIGNED NULL DEFAULT NULL COMMENT 'Pengguna (Administrator/Kasir) yang membaca, ref. users',
  `dibaca_pada` DATETIME NULL DEFAULT NULL COMMENT 'Waktu dibaca',
  `created_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'Waktu data dibuat (dikelola Laravel)',
  `updated_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'Waktu data terakhir diubah (dikelola Laravel)',
  PRIMARY KEY (`id_notifikasi`),
  KEY idx_notif_status (status),
  CONSTRAINT `fk_notifikasi_stok_id_barang` FOREIGN KEY (`id_barang`) REFERENCES `barang` (`id_barang`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_notifikasi_stok_dibaca_oleh` FOREIGN KEY (`dibaca_oleh`) REFERENCES `users` (`id_user`) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Notifikasi Stok Minimum';

-- D10 | Kategori Pengeluaran
-- Jenis beban operasional, misalnya listrik, air, sewa tempat, transportasi, kemasan, dan lain-lain. Menjadi rincian beban di laporan laba rugi.
CREATE TABLE `kategori_pengeluaran` (
  `id_kategori_pengeluaran` INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Kode unik kategori',
  `nama_kategori` VARCHAR(100) NOT NULL COMMENT 'Nama kategori beban',
  `keterangan` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Keterangan tambahan',
  `created_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'Waktu data dibuat (dikelola Laravel)',
  `updated_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'Waktu data terakhir diubah (dikelola Laravel)',
  PRIMARY KEY (`id_kategori_pengeluaran`),
  UNIQUE KEY `uk_kategori_pengeluaran_nama_kategori` (`nama_kategori`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Kategori Pengeluaran';

-- D10 | Pengeluaran Operasional
-- Pencatatan beban operasional toko di luar pembelian barang dagangan.
CREATE TABLE `pengeluaran_operasional` (
  `id_pengeluaran` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Kode unik pengeluaran',
  `kode_pengeluaran` VARCHAR(30) NOT NULL COMMENT 'Nomor otomatis, contoh OP-20261123-01',
  `id_kategori_pengeluaran` INT UNSIGNED NOT NULL COMMENT 'Jenis beban, ref. kategori_pengeluaran',
  `id_user` BIGINT UNSIGNED NOT NULL COMMENT 'Administrator pencatat, ref. users',
  `tanggal_pengeluaran` DATE NOT NULL COMMENT 'Tanggal pengeluaran',
  `deskripsi` VARCHAR(255) NOT NULL COMMENT 'Uraian pengeluaran',
  `jumlah` DECIMAL(15,2) NOT NULL COMMENT 'Nominal pengeluaran, > 0',
  `metode_pembayaran` ENUM('tunai','transfer') NOT NULL DEFAULT 'tunai' COMMENT 'Cara pembayaran',
  `bukti` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Path file foto nota (opsional)',
  `created_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'Waktu data dibuat (dikelola Laravel)',
  `updated_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'Waktu data terakhir diubah (dikelola Laravel)',
  PRIMARY KEY (`id_pengeluaran`),
  UNIQUE KEY `uk_pengeluaran_operasional_kode_pengeluaran` (`kode_pengeluaran`),
  KEY idx_op_tanggal (tanggal_pengeluaran),
  CONSTRAINT chk_op_jumlah CHECK (jumlah > 0),
  CONSTRAINT `fk_pengeluaran_operasional_id_kategori_pengeluaran` FOREIGN KEY (`id_kategori_pengeluaran`) REFERENCES `kategori_pengeluaran` (`id_kategori_pengeluaran`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_pengeluaran_operasional_id_user` FOREIGN KEY (`id_user`) REFERENCES `users` (`id_user`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Pengeluaran Operasional';

-- D12 | Validasi Kas Harian
-- Pencocokan harian antara total penjualan tunai di sistem dengan uang tunai fisik di laci kasir, untuk mendeteksi selisih yang sebelumnya tidak terdeteksi.
CREATE TABLE `validasi_kas` (
  `id_validasi_kas` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Kode unik validasi',
  `tanggal` DATE NOT NULL COMMENT 'Tanggal yang divalidasi, satu kali per hari',
  `total_tunai_sistem` DECIMAL(15,2) NOT NULL COMMENT 'Σ penjualan tunai berstatus selesai pada tanggal itu',
  `uang_fisik` DECIMAL(15,2) NOT NULL COMMENT 'Uang tunai hasil hitung fisik',
  `selisih` DECIMAL(15,2) NOT NULL COMMENT 'uang_fisik − total_tunai_sistem',
  `status` ENUM('sesuai','selisih') NOT NULL COMMENT 'sesuai bila selisih = 0',
  `catatan` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Penjelasan bila ada selisih',
  `id_user` BIGINT UNSIGNED NOT NULL COMMENT 'Administrator yang memvalidasi, ref. users',
  `created_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'Waktu data dibuat (dikelola Laravel)',
  `updated_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'Waktu data terakhir diubah (dikelola Laravel)',
  PRIMARY KEY (`id_validasi_kas`),
  UNIQUE KEY `uk_validasi_kas_tanggal` (`tanggal`),
  CONSTRAINT `fk_validasi_kas_id_user` FOREIGN KEY (`id_user`) REFERENCES `users` (`id_user`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Validasi Kas Harian';

-- D11 | Kas (Arus Kas)
-- Buku kas usaha. Setiap uang masuk dan keluar dicatat di sini, sebagian otomatis dari transaksi dan sebagian manual (modal, prive, penyesuaian). Kategori prive memisahkan keuangan usaha dari keuangan pribadi.
CREATE TABLE `kas` (
  `id_kas` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Kode unik baris kas',
  `tanggal` DATETIME NOT NULL COMMENT 'Tanggal dan jam arus kas',
  `jenis` ENUM('masuk','keluar') NOT NULL COMMENT 'Arah arus kas',
  `kategori` ENUM('penjualan','pembelian_barang','operasional','modal','prive','penyesuaian') NOT NULL COMMENT 'Sumber/tujuan arus kas',
  `id_penjualan` BIGINT UNSIGNED NULL DEFAULT NULL COMMENT 'Diisi bila kategori penjualan',
  `id_barang_masuk` BIGINT UNSIGNED NULL DEFAULT NULL COMMENT 'Diisi bila kategori pembelian_barang',
  `id_pengeluaran` BIGINT UNSIGNED NULL DEFAULT NULL COMMENT 'Diisi bila kategori operasional',
  `id_validasi_kas` BIGINT UNSIGNED NULL DEFAULT NULL COMMENT 'Diisi bila penyesuaian dari selisih validasi kas',
  `jumlah` DECIMAL(15,2) NOT NULL COMMENT 'Nominal, selalu positif',
  `keterangan` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Uraian',
  `id_user` BIGINT UNSIGNED NOT NULL COMMENT 'Pengguna pencatat, ref. users',
  `created_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'Waktu data dibuat (dikelola Laravel)',
  `updated_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'Waktu data terakhir diubah (dikelola Laravel)',
  PRIMARY KEY (`id_kas`),
  UNIQUE KEY `uk_kas_id_penjualan` (`id_penjualan`),
  UNIQUE KEY `uk_kas_id_barang_masuk` (`id_barang_masuk`),
  UNIQUE KEY `uk_kas_id_pengeluaran` (`id_pengeluaran`),
  UNIQUE KEY `uk_kas_id_validasi_kas` (`id_validasi_kas`),
  KEY idx_kas_tanggal (tanggal),
  CONSTRAINT chk_kas_jumlah CHECK (jumlah > 0),
  CONSTRAINT `fk_kas_id_penjualan` FOREIGN KEY (`id_penjualan`) REFERENCES `penjualan` (`id_penjualan`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_kas_id_barang_masuk` FOREIGN KEY (`id_barang_masuk`) REFERENCES `barang_masuk` (`id_barang_masuk`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_kas_id_pengeluaran` FOREIGN KEY (`id_pengeluaran`) REFERENCES `pengeluaran_operasional` (`id_pengeluaran`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_kas_id_validasi_kas` FOREIGN KEY (`id_validasi_kas`) REFERENCES `validasi_kas` (`id_validasi_kas`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_kas_id_user` FOREIGN KEY (`id_user`) REFERENCES `users` (`id_user`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Kas (Arus Kas)';

-- D13 | HPP Periodik
-- Hasil perhitungan HPP per produk per periode dengan rumus HPP = Persediaan Awal + Pembelian Bersih − Persediaan Akhir (Bab 2.6). Disimpan agar laporan laba rugi periode yang sudah ditutup tidak berubah.
CREATE TABLE `hpp_periode` (
  `id_hpp` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Kode unik perhitungan',
  `id_barang` BIGINT UNSIGNED NOT NULL COMMENT 'Barang yang dihitung, ref. barang',
  `periode_awal` DATE NOT NULL COMMENT 'Tanggal awal periode',
  `periode_akhir` DATE NOT NULL COMMENT 'Tanggal akhir periode',
  `qty_persediaan_awal` INT NOT NULL DEFAULT 0 COMMENT 'Unit persediaan awal',
  `nilai_persediaan_awal` DECIMAL(15,2) NOT NULL DEFAULT 0 COMMENT 'Nilai rupiah persediaan awal',
  `qty_pembelian` INT NOT NULL DEFAULT 0 COMMENT 'Unit pembelian dalam periode',
  `nilai_pembelian` DECIMAL(15,2) NOT NULL DEFAULT 0 COMMENT 'Nilai pembelian bersih',
  `qty_persediaan_akhir` INT NOT NULL DEFAULT 0 COMMENT 'Unit persediaan akhir',
  `nilai_persediaan_akhir` DECIMAL(15,2) NOT NULL DEFAULT 0 COMMENT 'Nilai rupiah persediaan akhir',
  `total_hpp` DECIMAL(15,2) NOT NULL DEFAULT 0 COMMENT 'Awal + Pembelian − Akhir',
  `qty_terjual` INT NOT NULL DEFAULT 0 COMMENT 'Unit terjual dalam periode',
  `hpp_per_unit` DECIMAL(15,2) NOT NULL DEFAULT 0 COMMENT 'total_hpp ÷ qty_terjual',
  `id_user` BIGINT UNSIGNED NOT NULL COMMENT 'Administrator yang menjalankan perhitungan',
  `created_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'Waktu data dibuat (dikelola Laravel)',
  `updated_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'Waktu data terakhir diubah (dikelola Laravel)',
  PRIMARY KEY (`id_hpp`),
  UNIQUE KEY uk_hpp_barang_periode (id_barang, periode_awal, periode_akhir),
  CONSTRAINT chk_hpp_periode CHECK (periode_akhir >= periode_awal),
  CONSTRAINT `fk_hpp_periode_id_barang` FOREIGN KEY (`id_barang`) REFERENCES `barang` (`id_barang`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_hpp_periode_id_user` FOREIGN KEY (`id_user`) REFERENCES `users` (`id_user`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='HPP Periodik';

-- D14 | Riwayat AI Assistant
-- Riwayat tanya-jawab Administrator dengan AI Assistant (Gemini API), termasuk ringkasan data yang dikirim sebagai konteks. Berguna untuk menampilkan riwayat percakapan dan sebagai bukti pengujian Black Box.
CREATE TABLE `riwayat_ai` (
  `id_riwayat_ai` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Kode unik riwayat',
  `id_user` BIGINT UNSIGNED NOT NULL COMMENT 'Administrator penanya, ref. users',
  `pertanyaan` TEXT NOT NULL COMMENT 'Pertanyaan dalam bahasa sehari-hari',
  `topik` ENUM('stok','keuangan','umum') NOT NULL DEFAULT 'umum' COMMENT 'Hasil klasifikasi pertanyaan',
  `konteks_data` JSON NULL DEFAULT NULL COMMENT 'Ringkasan data sistem yang dikirim ke Gemini',
  `jawaban` MEDIUMTEXT NULL DEFAULT NULL COMMENT 'Jawaban dari Gemini API',
  `model` VARCHAR(50) NOT NULL COMMENT 'Nama model Gemini yang dipakai',
  `status` ENUM('berhasil','gagal') NOT NULL DEFAULT 'berhasil' COMMENT 'Status pemanggilan API',
  `pesan_error` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Pesan galat bila gagal',
  `waktu_respons_ms` INT UNSIGNED NULL DEFAULT NULL COMMENT 'Lama respons API dalam milidetik',
  `created_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'Waktu data dibuat (dikelola Laravel)',
  `updated_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'Waktu data terakhir diubah (dikelola Laravel)',
  PRIMARY KEY (`id_riwayat_ai`),
  KEY idx_ai_user_waktu (id_user, created_at),
  CONSTRAINT `fk_riwayat_ai_id_user` FOREIGN KEY (`id_user`) REFERENCES `users` (`id_user`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Riwayat AI Assistant';

-- ---------------------------------------------------------------------
-- Data awal yang disarankan (boleh diubah sesuai kondisi toko)
-- Akun Administrator & Kasir dibuat lewat Laravel Seeder agar password
-- di-hash dengan Hash::make().
-- ---------------------------------------------------------------------
INSERT INTO kategori_pengeluaran (nama_kategori, keterangan, created_at, updated_at) VALUES
 ('Listrik', 'Tagihan listrik toko', NOW(), NOW()),
 ('Air', 'Tagihan air', NOW(), NOW()),
 ('Sewa Tempat', 'Sewa lapak/kios', NOW(), NOW()),
 ('Transportasi', 'Ongkos belanja/ambil barang', NOW(), NOW()),
 ('Kemasan dan ATK', 'Kantong plastik, kertas struk, alat tulis', NOW(), NOW()),
 ('Upah', 'Upah kasir', NOW(), NOW()),
 ('Lain-lain', 'Pengeluaran operasional lainnya', NOW(), NOW());
