<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\Location;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

    public function test_fractional_product_prices_are_rejected(): void
    {
        $this->actingAsAdmin();
        $location = Location::factory()->create();

        $response = $this->post('/admin/products', [
            'name' => 'Produk Pecahan',
            'sku' => 'SKU-FRACTION',
            'location_id' => $location->id,
            'cost_price' => '1000.50',
            'sale_price' => '1500.25',
            'stock' => 10,
            'unit' => 'pcs',
        ]);

        $response->assertSessionHasErrors(['cost_price', 'sale_price']);
        $this->assertDatabaseMissing('products', ['sku' => 'SKU-FRACTION']);
    }

    public function test_uploaded_product_photo_is_optimized_to_fixed_square(): void
    {
        Storage::fake('public');
        $this->actingAsAdmin();
        $location = Location::factory()->create();

        $this->post('/admin/products', [
            'name' => 'Produk Foto',
            'sku' => 'SKU-FOTO',
            'photo' => UploadedFile::fake()->image('large-product.jpg', 1600, 900),
            'location_id' => $location->id,
            'cost_price' => 1000,
            'sale_price' => 1500,
            'stock' => 10,
            'unit' => 'pcs',
        ])->assertRedirect('/admin/products');

        $product = Product::where('sku', 'SKU-FOTO')->firstOrFail();
        Storage::disk('public')->assertExists($product->photo_path);
        $optimized = getimagesize(Storage::disk('public')->path($product->photo_path));
        $this->assertSame(640, $optimized[0]);
        $this->assertSame(640, $optimized[1]);
        $this->assertLessThanOrEqual(300 * 1024, Storage::disk('public')->size($product->photo_path));
    }

    public function test_edit_form_renders_decimal_prices_as_valid_whole_rupiah_inputs(): void
    {
        $this->actingAsAdmin();
        $product = Product::factory()->create([
            'cost_price' => '1000.00',
            'sale_price' => '1500.00',
        ]);

        $this->get(route('admin.products.edit', $product))
            ->assertOk()
            ->assertSee('name="cost_price" min="0" step="100" value="1000"', false)
            ->assertSee('name="sale_price" min="0" step="100" value="1500"', false);
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
