<?php

namespace Database\Factories;

use App\Models\Wali;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Wali>
 */
class WaliFactory extends Factory
{
    protected $model = Wali::class;

    public function definition(): array
    {
        return [
            'user_id' => null,
            'name' => fake()->name(),
            'relationship' => fake()->randomElement(['Orang Tua', 'Wali', 'Kakak']),
            'phone' => fake()->phoneNumber(),
            'alternate_phone' => fake()->optional()->phoneNumber(),
            'email' => fake()->safeEmail(),
            'address' => fake()->address(),
            'notifications_enabled' => true,
            'notification_channels' => ['email'],
        ];
    }
}
