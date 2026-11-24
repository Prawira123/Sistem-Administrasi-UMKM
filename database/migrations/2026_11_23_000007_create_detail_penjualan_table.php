<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('detail_penjualan', function (Blueprint $table) {
            $table->id('id_detail_penjualan');
            $table->unsignedBigInteger('id_penjualan');
            $table->unsignedBigInteger('id_barang');
            $table->integer('jumlah');
            $table->decimal('harga_jual', 15, 2);
            $table->decimal('hpp_satuan', 15, 2);
            $table->decimal('subtotal', 15, 2);
            $table->decimal('subtotal_hpp', 15, 2);
            $table->timestamps();

            $table->foreign('id_penjualan')
                ->references('id_penjualan')->on('penjualan')
                ->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreign('id_barang')
                ->references('id_barang')->on('barang')
                ->cascadeOnUpdate()->restrictOnDelete();
        });

        DB::statement('ALTER TABLE `detail_penjualan` ADD CONSTRAINT chk_dp_jumlah CHECK (jumlah > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('detail_penjualan');
    }
};
