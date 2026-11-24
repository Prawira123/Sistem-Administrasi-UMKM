<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier', function (Blueprint $table) {
            $table->increments('id_supplier');
            $table->string('kode_supplier', 20)->unique();
            $table->string('nama_supplier', 100);
            $table->string('nama_kontak', 100)->nullable();
            $table->string('no_telepon', 20)->nullable();
            $table->text('alamat')->nullable();
            $table->enum('status', ['aktif', 'nonaktif'])->default('aktif');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier');
    }
};
