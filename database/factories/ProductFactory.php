<?php

namespace Database\Factories;

use App\Models\Location;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'location_id' => Location::factory(),
            'category_id' => null,
            'sku' => strtoupper(Str::random(8)),
            'barcode' => strtoupper(Str::random(12)),
            'name' => ucfirst(fake()->word()).' '.ucfirst(fake()->word()),
            'unit' => 'pcs',
            'cost_price' => 2000,
            'sale_price' => 5000,
            'stock' => 100,
            'stock_alert' => 10,
            'is_active' => true,
            'is_whitelisted' => false,
            'tags' => [],
            'metadata' => [],
        ];
    }
}
