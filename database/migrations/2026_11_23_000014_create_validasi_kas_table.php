<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('validasi_kas', function (Blueprint $table) {
            $table->id('id_validasi_kas');
            $table->date('tanggal')->unique();
            $table->decimal('total_tunai_sistem', 15, 2);
            $table->decimal('uang_fisik', 15, 2);
            $table->decimal('selisih', 15, 2);
            $table->enum('status', ['sesuai', 'selisih']);
            $table->string('catatan', 255)->nullable();
            $table->unsignedBigInteger('id_user');
            $table->timestamps();

            $table->foreign('id_user')
                ->references('id_user')->on('users')
                ->cascadeOnUpdate()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('validasi_kas');
    }
};
