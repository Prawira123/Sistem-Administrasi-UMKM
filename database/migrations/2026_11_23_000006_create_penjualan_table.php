<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('penjualan', function (Blueprint $table) {
            $table->id('id_penjualan');
            $table->string('no_transaksi', 30)->unique();
            $table->unsignedBigInteger('id_user');
            $table->dateTime('tanggal_penjualan');
            $table->integer('total_item')->default(0);
            $table->decimal('total_harga', 15, 2)->default(0);
            $table->decimal('total_hpp', 15, 2)->default(0);
            $table->enum('metode_pembayaran', ['tunai', 'qris', 'transfer'])->default('tunai');
            $table->decimal('jumlah_bayar', 15, 2)->default(0);
            $table->decimal('kembalian', 15, 2)->default(0);
            $table->enum('status', ['selesai', 'batal'])->default('selesai');
            $table->string('keterangan', 255)->nullable();
            $table->timestamps();

            $table->index('tanggal_penjualan', 'idx_penjualan_tanggal');
            $table->foreign('id_user')
                ->references('id_user')->on('users')
                ->cascadeOnUpdate()->restrictOnDelete();
        });

        DB::statement('ALTER TABLE `penjualan` ADD CONSTRAINT chk_penjualan_bayar CHECK (jumlah_bayar >= 0 AND kembalian >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('penjualan');
    }
};
