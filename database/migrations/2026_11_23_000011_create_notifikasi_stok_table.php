<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifikasi_stok', function (Blueprint $table) {
            $table->id('id_notifikasi');
            $table->unsignedBigInteger('id_barang');
            $table->integer('stok_saat_ini');
            $table->integer('stok_minimum');
            $table->string('pesan', 255);
            $table->enum('status', ['baru', 'dibaca', 'selesai'])->default('baru');
            $table->unsignedBigInteger('dibaca_oleh')->nullable();
            $table->dateTime('dibaca_pada')->nullable();
            $table->timestamps();

            $table->index('status', 'idx_notif_status');
            $table->foreign('id_barang')
                ->references('id_barang')->on('barang')
                ->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreign('dibaca_oleh')
                ->references('id_user')->on('users')
                ->cascadeOnUpdate()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifikasi_stok');
    }
};
