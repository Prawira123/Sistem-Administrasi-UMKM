<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hpp_periode', function (Blueprint $table) {
            $table->id('id_hpp');
            $table->unsignedBigInteger('id_barang');
            $table->date('periode_awal');
            $table->date('periode_akhir');
            $table->integer('qty_persediaan_awal')->default(0);
            $table->decimal('nilai_persediaan_awal', 15, 2)->default(0);
            $table->integer('qty_pembelian')->default(0);
            $table->decimal('nilai_pembelian', 15, 2)->default(0);
            $table->integer('qty_persediaan_akhir')->default(0);
            $table->decimal('nilai_persediaan_akhir', 15, 2)->default(0);
            $table->decimal('total_hpp', 15, 2)->default(0);
            $table->integer('qty_terjual')->default(0);
            $table->decimal('hpp_per_unit', 15, 2)->default(0);
            $table->unsignedBigInteger('id_user');
            $table->timestamps();

            $table->unique(['id_barang', 'periode_awal', 'periode_akhir'], 'uk_hpp_barang_periode');
            $table->foreign('id_barang')
                ->references('id_barang')->on('barang')
                ->cascadeOnUpdate()->restrictOnDelete();
            $table->foreign('id_user')
                ->references('id_user')->on('users')
                ->cascadeOnUpdate()->restrictOnDelete();
        });

        DB::statement('ALTER TABLE `hpp_periode` ADD CONSTRAINT chk_hpp_periode CHECK (periode_akhir >= periode_awal)');
    }

    public function down(): void
    {
        Schema::dropIfExists('hpp_periode');
    }
};
