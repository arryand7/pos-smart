<?php

namespace Database\Factories;

use App\Models\Santri;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Santri>
 */
class SantriFactory extends Factory
{
    protected $model = Santri::class;

    public function definition(): array
    {
        return [
            'user_id' => null,
            'wali_id' => null,
            'nis' => 'S'.fake()->unique()->numerify('########'),
            'qr_code' => fake()->unique()->uuid(),
            'nisn' => fake()->optional()->numerify('##########'),
            'name' => fake()->name(),
            'nickname' => fake()->firstName(),
            'gender' => fake()->randomElement(['Laki-laki', 'Perempuan']),
            'class' => fake()->randomElement(['7A', '8B', '9C']),
            'dormitory' => fake()->randomElement(['Putra 1', 'Putri 2']),
            'status' => 'active',
            'wallet_balance' => 0,
            'daily_limit' => 0,
            'weekly_limit' => 200000,
            'monthly_limit' => 0,
            'limit_reset_at' => null,
            'is_wallet_locked' => false,
            'blocked_category_ids' => [],
            'whitelisted_category_ids' => [],
            'metadata' => [],
            'notes' => null,
        ];
    }
}
