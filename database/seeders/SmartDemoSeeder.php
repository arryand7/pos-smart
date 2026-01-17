<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Santri;
use App\Models\User;
use App\Models\Wali;
use App\Models\WalletTransaction;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SmartDemoSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $this->seedCoreUsers();
            $locations = $this->seedLocations();
            $categories = $this->seedCategories();
            $this->seedProducts($locations, $categories, 50);
            $this->seedFamilies(50, collect($categories)->pluck('id')->filter()->all());
        });
    }

    protected function seedCoreUsers(): void
    {
        $defaultPassword = Hash::make('password');

        User::updateOrCreate(
            ['email' => 'superadmin@smart.test'],
            [
                'name' => 'Super Admin SMART',
                'password' => $defaultPassword,
                'role' => UserRole::SUPER_ADMIN->value,
                'phone' => '081200000000',
            ],
        );

        User::updateOrCreate(
            ['email' => 'admin@smart.test'],
            [
                'name' => 'Admin SMART',
                'password' => $defaultPassword,
                'role' => UserRole::ADMIN->value,
                'phone' => '081200000001',
            ],
        );

        User::updateOrCreate(
            ['email' => 'bendahara@smart.test'],
            [
                'name' => 'Bendahara SMART',
                'password' => $defaultPassword,
                'role' => UserRole::BENDAHARA->value,
                'phone' => '081200000002',
            ],
        );

        User::updateOrCreate(
            ['email' => 'kasir@smart.test'],
            [
                'name' => 'Kasir SMART',
                'password' => $defaultPassword,
                'role' => UserRole::KASIR->value,
                'phone' => '081200000003',
            ],
        );
    }

    protected function seedLocations(): array
    {
        $data = [
            ['name' => 'Kantin Utama', 'code' => 'KNTN', 'type' => 'kantin'],
            ['name' => 'Koperasi Santri', 'code' => 'KPRS', 'type' => 'koperasi'],
            ['name' => 'Laundry Sabira', 'code' => 'LDRY', 'type' => 'laundry'],
        ];

        return collect($data)->map(function ($item) {
            return Location::updateOrCreate(
                ['code' => $item['code']],
                [
                    'name' => $item['name'],
                    'type' => $item['type'],
                    'phone' => '0812'.rand(10000000, 99999999),
                    'manager_name' => fake()->name(),
                    'is_active' => true,
                ],
            );
        })->all();
    }

    protected function seedCategories(): array
    {
        $categories = [
            ['Snacks', 'snacks'],
            ['Minuman', 'minuman'],
            ['Kebersihan', 'kebersihan'],
            ['ATK', 'atk'],
            ['Laundry', 'laundry'],
        ];

        return collect($categories)->map(function ($item) {
            return ProductCategory::updateOrCreate(
                ['slug' => $item[1]],
                ['name' => $item[0], 'description' => Str::title($item[0])]
            );
        })->all();
    }

    protected function seedProducts(array $locations, array $categories, int $count = 50): void
    {
        $categoryIds = collect($categories)->pluck('id')->filter()->values()->all();

        for ($i = 1; $i <= $count; $i++) {
            $sku = 'PRD'.str_pad((string) $i, 4, '0', STR_PAD_LEFT);
            $location = collect($locations)->random();
            $category = collect($categories)->random();

            Product::updateOrCreate(
                ['sku' => $sku],
                [
                    'location_id' => $location->id,
                    'category_id' => $category->id,
                    'barcode' => 'BR'.str_pad((string) $i, 6, '0', STR_PAD_LEFT),
                    'name' => ucfirst(fake()->word()).' '.ucfirst(fake()->word()),
                    'unit' => 'pcs',
                    'cost_price' => rand(1000, 8000),
                    'sale_price' => rand(1500, 15000),
                    'stock' => rand(20, 200),
                    'stock_alert' => rand(5, 30),
                    'is_active' => true,
                    'is_whitelisted' => fake()->boolean(10),
                    'tags' => [fake()->colorName()],
                ],
            );
        }
    }

    protected function seedFamilies(int $count = 50, array $categoryIds = []): void
    {
        $defaultPassword = Hash::make('password');

        for ($i = 1; $i <= $count; $i++) {
            $waliEmail = "wali{$i}@smart.test";
            $waliUser = User::updateOrCreate(
                ['email' => $waliEmail],
                [
                    'name' => 'Wali '.$i,
                    'password' => $defaultPassword,
                    'role' => UserRole::WALI->value,
                    'phone' => '0821'.str_pad((string) $i, 8, '0', STR_PAD_LEFT),
                ],
            );

            $wali = Wali::updateOrCreate(
                ['user_id' => $waliUser->id],
                [
                    'name' => $waliUser->name,
                    'relationship' => fake()->randomElement(['Orang Tua', 'Wali', 'Kakak']),
                    'phone' => $waliUser->phone,
                    'email' => $waliUser->email,
                    'address' => fake()->address(),
                ],
            );

            $santriEmail = "santri{$i}@smart.test";
            $santriUser = User::updateOrCreate(
                ['email' => $santriEmail],
                [
                    'name' => 'Santri '.$i,
                    'password' => $defaultPassword,
                    'role' => UserRole::SANTRI->value,
                    'phone' => '0831'.str_pad((string) $i, 8, '0', STR_PAD_LEFT),
                ],
            );

            $santri = Santri::updateOrCreate(
                ['nis' => 'S'.str_pad((string) $i, 6, '0', STR_PAD_LEFT)],
                [
                    'user_id' => $santriUser->id,
                    'wali_id' => $wali->id,
                    'name' => $santriUser->name,
                    'nickname' => fake()->firstName(),
                    'gender' => fake()->randomElement(['Laki-laki', 'Perempuan']),
                    'class' => 'Kelas '.rand(7, 12),
                    'dormitory' => fake()->randomElement(['Putra 1', 'Putri 2', 'Putra 3']),
                    'status' => 'active',
                    'wallet_balance' => 0,
                    'daily_limit' => rand(20000, 80000),
                    'monthly_limit' => rand(300000, 800000),
                    'blocked_category_ids' => $this->randomCategorySelection($categoryIds),
                    'whitelisted_category_ids' => $this->randomCategorySelection($categoryIds),
                ],
            );

            $amount = rand(50000, 200000);
            $santri->update(['wallet_balance' => $amount]);

            WalletTransaction::create([
                'santri_id' => $santri->id,
                'performed_by' => null,
                'type' => 'credit',
                'channel' => 'topup',
                'amount' => $amount,
                'balance_before' => 0,
                'balance_after' => $amount,
                'reference_type' => null,
                'reference_id' => null,
                'status' => 'completed',
                'description' => 'Saldo awal demo',
                'metadata' => ['seeded' => true],
                'occurred_at' => now()->subDays(rand(1, 10)),
            ]);
        }
    }

    /**
     * Ambil acak kategori untuk whitelist/blacklist dengan peluang kecil.
     *
     * @param  array<int, int>  $categoryIds
     * @return array<int, int>
     */
    protected function randomCategorySelection(array $categoryIds): array
    {
        if (empty($categoryIds) || ! fake()->boolean(35)) {
            return [];
        }

        return [fake()->randomElement($categoryIds)];
    }
}
