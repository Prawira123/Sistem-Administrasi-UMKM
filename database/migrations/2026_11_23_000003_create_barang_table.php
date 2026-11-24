<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('barang', function (Blueprint $table) {
            $table->id('id_barang');
            $table->string('kode_barang', 30)->unique();
            $table->unsignedInteger('id_kategori');
            $table->string('nama_barang', 150);
            $table->string('satuan', 20)->default('pcs');
            $table->decimal('harga_beli_terakhir', 15, 2)->default(0);
            $table->decimal('hpp_rata_rata', 15, 2)->default(0);
            $table->decimal('harga_jual', 15, 2)->default(0);
            $table->integer('stok')->default(0);
            $table->integer('stok_minimum')->default(5);
            $table->enum('status', ['aktif', 'nonaktif'])->default('aktif');
            $table->timestamps();

            $table->index('nama_barang', 'idx_barang_nama');
            $table->foreign('id_kategori')
                ->references('id_kategori')->on('kategori_barang')
                ->cascadeOnUpdate()->restrictOnDelete();
        });

        DB::statement('ALTER TABLE `barang` ADD CONSTRAINT chk_barang_stok CHECK (stok >= 0)');
        DB::statement('ALTER TABLE `barang` ADD CONSTRAINT chk_barang_stok_min CHECK (stok_minimum >= 0)');
        DB::statement('ALTER TABLE `barang` ADD CONSTRAINT chk_barang_harga CHECK (harga_jual >= 0 AND hpp_rata_rata >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('barang');
    }
};
