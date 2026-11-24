<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pengeluaran_operasional', function (Blueprint $table) {
            $table->id('id_pengeluaran');
            $table->string('kode_pengeluaran', 30)->unique();
            $table->unsignedInteger('id_kategori_pengeluaran');
            $table->unsignedBigInteger('id_user');
            $table->date('tanggal_pengeluaran');
            $table->string('deskripsi', 255);
            $table->decimal('jumlah', 15, 2);
            $table->enum('metode_pembayaran', ['tunai', 'transfer'])->default('tunai');
            $table->string('bukti', 255)->nullable();
            $table->timestamps();

            $table->index('tanggal_pengeluaran', 'idx_op_tanggal');
            $table->foreign('id_kategori_pengeluaran')
                ->references('id_kategori_pengeluaran')->on('kategori_pengeluaran')
                ->cascadeOnUpdate()->restrictOnDelete();
            $table->foreign('id_user')
                ->references('id_user')->on('users')
                ->cascadeOnUpdate()->restrictOnDelete();
        });

        DB::statement('ALTER TABLE `pengeluaran_operasional` ADD CONSTRAINT chk_op_jumlah CHECK (jumlah > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('pengeluaran_operasional');
    }
};
