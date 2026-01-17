<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function actingAsAdmin(): User
    {
        $admin = User::factory()->create([
            'role' => UserRole::ADMIN->value,
        ]);

        $this->withSession([
            'smart_user' => [
                'id' => $admin->id,
                'name' => $admin->name,
                'role' => UserRole::ADMIN->value,
            ],
        ]);

        return $admin;
    }

    public function test_admin_can_view_product_list(): void
    {
        $this->actingAsAdmin();

        Product::factory()->create();

        $response = $this->get('/admin/products');

        $response->assertStatus(200);
        $response->assertSee('Daftar Produk');
    }

    public function test_admin_can_create_product(): void
    {
        $this->actingAsAdmin();

        $location = Location::factory()->create();

        $response = $this->post('/admin/products', [
            'name' => 'Produk Test',
            'sku' => 'SKU123',
            'location_id' => $location->id,
            'cost_price' => 1000,
            'sale_price' => 1500,
            'stock' => 10,
            'unit' => 'pcs',
        ]);

        $response->assertRedirect('/admin/products');
        $this->assertDatabaseHas('products', ['sku' => 'SKU123']);
    }

    public function test_admin_can_manage_locations(): void
    {
        $this->actingAsAdmin();

        $this->get('/admin/locations')->assertStatus(200);

        $this->post('/admin/locations', [
            'name' => 'Kantin Baru',
            'code' => 'KB01',
            'type' => 'kantin',
            'is_active' => true,
        ])->assertRedirect('/admin/locations');

        $this->assertDatabaseHas('locations', ['code' => 'KB01']);
    }

    public function test_admin_can_manage_categories(): void
    {
        $this->actingAsAdmin();

        $this->get('/admin/categories')->assertStatus(200);

        $this->post('/admin/categories', [
            'name' => 'Cemilan',
            'slug' => 'cemilan',
        ])->assertRedirect('/admin/categories');

        $this->assertDatabaseHas('product_categories', ['slug' => 'cemilan']);
    }
}
