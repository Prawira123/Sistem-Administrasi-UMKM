<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kartu_stok', function (Blueprint $table) {
            $table->id('id_kartu_stok');
            $table->unsignedBigInteger('id_barang');
            $table->dateTime('tanggal');
            $table->enum('jenis_mutasi', ['masuk', 'keluar', 'penyesuaian']);
            $table->unsignedBigInteger('id_barang_masuk')->nullable();
            $table->unsignedBigInteger('id_penjualan')->nullable();
            $table->unsignedBigInteger('id_stok_opname')->nullable();
            $table->integer('jumlah_masuk')->default(0);
            $table->integer('jumlah_keluar')->default(0);
            $table->integer('stok_sebelum');
            $table->integer('stok_sesudah');
            $table->decimal('hpp_satuan', 15, 2)->default(0);
            $table->string('keterangan', 255)->nullable();
            $table->unsignedBigInteger('id_user');
            $table->timestamps();

            $table->index(['id_barang', 'tanggal'], 'idx_ks_barang_tanggal');
            $table->foreign('id_barang')
                ->references('id_barang')->on('barang')
                ->cascadeOnUpdate()->restrictOnDelete();
            $table->foreign('id_barang_masuk')
                ->references('id_barang_masuk')->on('barang_masuk')
                ->cascadeOnUpdate()->restrictOnDelete();
            $table->foreign('id_penjualan')
                ->references('id_penjualan')->on('penjualan')
                ->cascadeOnUpdate()->restrictOnDelete();
            $table->foreign('id_stok_opname')
                ->references('id_stok_opname')->on('stok_opname')
                ->cascadeOnUpdate()->restrictOnDelete();
            $table->foreign('id_user')
                ->references('id_user')->on('users')
                ->cascadeOnUpdate()->restrictOnDelete();
        });

        DB::statement('ALTER TABLE `kartu_stok` ADD CONSTRAINT chk_ks_jumlah CHECK (jumlah_masuk >= 0 AND jumlah_keluar >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('kartu_stok');
    }
};
