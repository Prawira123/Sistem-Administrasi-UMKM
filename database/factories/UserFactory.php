<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    protected static ?string $password;

    public function definition(): array
    {
        return [
            'nama' => fake()->name(),
            'username' => fake()->unique()->userName(),
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => 'kasir',
            'status' => 'aktif',
            'remember_token' => Str::random(10),
        ];
    }

    public function administrator(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'administrator',
        ]);
    }

    public function kasir(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'kasir',
        ]);
    }

    public function nonaktif(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'nonaktif',
        ]);
    }
}
