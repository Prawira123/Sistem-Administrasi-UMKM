<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('barang_masuk', function (Blueprint $table) {
            $table->id('id_barang_masuk');
            $table->string('kode_barang_masuk', 30)->unique();
            $table->string('no_faktur', 50)->nullable();
            $table->unsignedInteger('id_supplier');
            $table->unsignedBigInteger('id_user');
            $table->date('tanggal_masuk');
            $table->decimal('total_pembelian', 15, 2)->default(0);
            $table->enum('metode_pembayaran', ['tunai', 'transfer'])->default('tunai');
            $table->string('keterangan', 255)->nullable();
            $table->timestamps();

            $table->index('tanggal_masuk', 'idx_bm_tanggal');
            $table->foreign('id_supplier')
                ->references('id_supplier')->on('supplier')
                ->cascadeOnUpdate()->restrictOnDelete();
            $table->foreign('id_user')
                ->references('id_user')->on('users')
                ->cascadeOnUpdate()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('barang_masuk');
    }
};
