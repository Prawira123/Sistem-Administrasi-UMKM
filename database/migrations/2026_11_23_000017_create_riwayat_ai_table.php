<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('riwayat_ai', function (Blueprint $table) {
            $table->id('id_riwayat_ai');
            $table->unsignedBigInteger('id_user');
            $table->text('pertanyaan');
            $table->enum('topik', ['stok', 'keuangan', 'umum'])->default('umum');
            $table->json('konteks_data')->nullable();
            $table->mediumText('jawaban')->nullable();
            $table->string('model', 50);
            $table->enum('status', ['berhasil', 'gagal'])->default('berhasil');
            $table->string('pesan_error', 255)->nullable();
            $table->unsignedInteger('waktu_respons_ms')->nullable();
            $table->timestamps();

            $table->index(['id_user', 'created_at'], 'idx_ai_user_waktu');
            $table->foreign('id_user')
                ->references('id_user')->on('users')
                ->cascadeOnUpdate()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('riwayat_ai');
    }
};
