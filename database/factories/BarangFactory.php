<?php

namespace Database\Factories;

use App\Models\Barang;
use App\Models\KategoriBarang;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory tidak terikat $fillable, sehingga stok dan HPP bisa diisi untuk keperluan test.
 *
 * @extends Factory<Barang>
 */
class BarangFactory extends Factory
{
    protected $model = Barang::class;

    public function definition(): array
    {
        return [
            'kode_barang' => 'TST-'.fake()->unique()->numerify('######'),
            'id_kategori' => KategoriBarang::factory(),
            'nama_barang' => ucwords(fake()->words(3, true)),
            'satuan' => 'pcs',
            'harga_beli_terakhir' => 0,
            'hpp_rata_rata' => 0,
            'harga_jual' => fake()->numberBetween(2, 100) * 500,
            'stok' => 0,
            'stok_minimum' => 5,
            'status' => 'aktif',
        ];
    }

    /**
     * Barang dengan stok awal (dan opsional HPP rata-rata), hanya untuk test.
     */
    public function stok(int $stok, ?float $hpp = null): static
    {
        return $this->state(fn () => array_filter([
            'stok' => $stok,
            'hpp_rata_rata' => $hpp,
            'harga_beli_terakhir' => $hpp,
        ], fn ($nilai) => $nilai !== null));
    }

    public function nonaktif(): static
    {
        return $this->state(fn () => ['status' => 'nonaktif']);
    }
}
