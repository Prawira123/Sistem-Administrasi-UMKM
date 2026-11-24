<?php

namespace Database\Factories;

use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Supplier>
 */
class SupplierFactory extends Factory
{
    protected $model = Supplier::class;

    public function definition(): array
    {
        return [
            'kode_supplier' => 'SUP-'.fake()->unique()->numerify('9##'),
            'nama_supplier' => fake()->company(),
            'nama_kontak' => fake()->name(),
            'no_telepon' => fake()->numerify('08##########'),
            'alamat' => fake()->address(),
            'status' => 'aktif',
        ];
    }

    public function nonaktif(): static
    {
        return $this->state(fn () => ['status' => 'nonaktif']);
    }
}
