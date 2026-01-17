<?php

namespace Database\Factories;

use App\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Location>
 */
class LocationFactory extends Factory
{
    protected $model = Location::class;

    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'code' => strtoupper(Str::random(5)),
            'type' => fake()->randomElement(['kantin', 'koperasi', 'laundry']),
            'phone' => fake()->phoneNumber(),
            'manager_name' => fake()->name(),
            'address' => fake()->address(),
            'is_active' => true,
            'metadata' => [],
        ];
    }
}
