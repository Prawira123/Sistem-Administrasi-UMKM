<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('detail_stok_opname', function (Blueprint $table) {
            $table->id('id_detail_opname');
            $table->unsignedBigInteger('id_stok_opname');
            $table->unsignedBigInteger('id_barang');
            $table->integer('stok_sistem');
            $table->integer('stok_fisik');
            $table->integer('selisih');
            $table->string('keterangan', 255)->nullable();
            $table->timestamps();

            $table->unique(['id_stok_opname', 'id_barang'], 'uk_dso_barang');
            $table->foreign('id_stok_opname')
                ->references('id_stok_opname')->on('stok_opname')
                ->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreign('id_barang')
                ->references('id_barang')->on('barang')
                ->cascadeOnUpdate()->restrictOnDelete();
        });

        DB::statement('ALTER TABLE `detail_stok_opname` ADD CONSTRAINT chk_dso_fisik CHECK (stok_fisik >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('detail_stok_opname');
    }
};
