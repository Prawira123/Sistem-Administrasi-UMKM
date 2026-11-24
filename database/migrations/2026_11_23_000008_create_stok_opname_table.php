<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stok_opname', function (Blueprint $table) {
            $table->id('id_stok_opname');
            $table->string('kode_opname', 30)->unique();
            $table->unsignedBigInteger('id_user');
            $table->date('tanggal_opname');
            $table->enum('status', ['draft', 'diajukan', 'final'])->default('draft');
            $table->unsignedBigInteger('disetujui_oleh')->nullable();
            $table->dateTime('disetujui_pada')->nullable();
            $table->string('keterangan', 255)->nullable();
            $table->timestamps();

            $table->foreign('id_user')
                ->references('id_user')->on('users')
                ->cascadeOnUpdate()->restrictOnDelete();
            $table->foreign('disetujui_oleh')
                ->references('id_user')->on('users')
                ->cascadeOnUpdate()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stok_opname');
    }
};
