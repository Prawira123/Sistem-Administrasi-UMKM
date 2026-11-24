<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('detail_barang_masuk', function (Blueprint $table) {
            $table->id('id_detail_masuk');
            $table->unsignedBigInteger('id_barang_masuk');
            $table->unsignedBigInteger('id_barang');
            $table->integer('jumlah');
            $table->decimal('harga_beli', 15, 2);
            $table->decimal('subtotal', 15, 2);
            $table->date('tanggal_kadaluarsa')->nullable();
            $table->timestamps();

            $table->foreign('id_barang_masuk')
                ->references('id_barang_masuk')->on('barang_masuk')
                ->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreign('id_barang')
                ->references('id_barang')->on('barang')
                ->cascadeOnUpdate()->restrictOnDelete();
        });

        DB::statement('ALTER TABLE `detail_barang_masuk` ADD CONSTRAINT chk_dbm_jumlah CHECK (jumlah > 0)');
        DB::statement('ALTER TABLE `detail_barang_masuk` ADD CONSTRAINT chk_dbm_harga CHECK (harga_beli >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('detail_barang_masuk');
    }
};
