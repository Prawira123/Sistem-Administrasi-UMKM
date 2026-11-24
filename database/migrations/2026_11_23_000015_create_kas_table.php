<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kas', function (Blueprint $table) {
            $table->id('id_kas');
            $table->dateTime('tanggal');
            $table->enum('jenis', ['masuk', 'keluar']);
            $table->enum('kategori', [
                'penjualan',
                'pembelian_barang',
                'operasional',
                'modal',
                'prive',
                'penyesuaian',
            ]);
            $table->unsignedBigInteger('id_penjualan')->nullable()->unique('uk_kas_id_penjualan');
            $table->unsignedBigInteger('id_barang_masuk')->nullable()->unique('uk_kas_id_barang_masuk');
            $table->unsignedBigInteger('id_pengeluaran')->nullable()->unique('uk_kas_id_pengeluaran');
            $table->unsignedBigInteger('id_validasi_kas')->nullable()->unique('uk_kas_id_validasi_kas');
            $table->decimal('jumlah', 15, 2);
            $table->string('keterangan', 255)->nullable();
            $table->unsignedBigInteger('id_user');
            $table->timestamps();

            $table->index('tanggal', 'idx_kas_tanggal');
            $table->foreign('id_penjualan')
                ->references('id_penjualan')->on('penjualan')
                ->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreign('id_barang_masuk')
                ->references('id_barang_masuk')->on('barang_masuk')
                ->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreign('id_pengeluaran')
                ->references('id_pengeluaran')->on('pengeluaran_operasional')
                ->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreign('id_validasi_kas')
                ->references('id_validasi_kas')->on('validasi_kas')
                ->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreign('id_user')
                ->references('id_user')->on('users')
                ->cascadeOnUpdate()->restrictOnDelete();
        });

        DB::statement('ALTER TABLE `kas` ADD CONSTRAINT chk_kas_jumlah CHECK (jumlah > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('kas');
    }
};
