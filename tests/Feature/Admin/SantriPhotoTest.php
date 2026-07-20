<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\Santri;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SantriPhotoTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_upload_photo_used_by_pos_verification(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        Storage::disk('public')->put('santris/old.jpg', 'old-photo');
        $santri = Santri::factory()->create(['photo_path' => 'santris/old.jpg']);

        $response = $this->withSession([
            'smart_user' => [
                'id' => $admin->id,
                'name' => $admin->name,
                'role' => UserRole::ADMIN->value,
            ],
        ])->put(route('admin.santri.update', $santri), [
            'photo' => UploadedFile::fake()->image('santri.jpg', 480, 640),
            'qr_code' => 'CARD-00010',
            'weekly_limit' => 300000,
            'monthly_limit' => 400000,
            'is_wallet_locked' => false,
        ]);

        $response->assertRedirect(route('admin.santri.index'));
        $santri->refresh();
        $this->assertNotNull($santri->photo_path);
        $this->assertStringStartsWith('santris/', $santri->photo_path);
        $this->assertSame('CARD-00010', $santri->qr_code);
        $this->assertSame(300000.0, (float) $santri->weekly_limit);
        $this->assertSame(400000.0, (float) $santri->monthly_limit);
        Storage::disk('public')->assertExists($santri->photo_path);
        Storage::disk('public')->assertMissing('santris/old.jpg');

        $optimized = getimagesize(Storage::disk('public')->path($santri->photo_path));
        $this->assertSame(360, $optimized[0]);
        $this->assertSame(480, $optimized[1]);
        $this->assertLessThanOrEqual(200 * 1024, Storage::disk('public')->size($santri->photo_path));
    }

    public function test_zero_and_empty_limits_are_stored_as_zero_instead_of_null(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $santri = Santri::factory()->create([
            'daily_limit' => 50000,
            'weekly_limit' => 200000,
            'monthly_limit' => 500000,
        ]);

        $response = $this->withSession([
            'smart_user' => [
                'id' => $admin->id,
                'name' => $admin->name,
                'role' => UserRole::ADMIN->value,
            ],
        ])->put(route('admin.santri.update', $santri), [
            'daily_limit' => '0',
            'weekly_limit' => '',
            'monthly_limit' => '0',
            'is_wallet_locked' => false,
        ]);

        $response->assertRedirect(route('admin.santri.index'));
        $santri->refresh();

        $this->assertSame(0.0, (float) $santri->daily_limit);
        $this->assertSame(0.0, (float) $santri->weekly_limit);
        $this->assertSame(0.0, (float) $santri->monthly_limit);
    }

    public function test_fractional_limit_is_rejected(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $santri = Santri::factory()->create(['daily_limit' => 50000]);

        $response = $this->from(route('admin.santri.edit', $santri))
            ->withSession([
                'smart_user' => [
                    'id' => $admin->id,
                    'name' => $admin->name,
                    'role' => UserRole::ADMIN->value,
                ],
            ])->put(route('admin.santri.update', $santri), [
                'daily_limit' => '1.5',
            ]);

        $response->assertRedirect(route('admin.santri.edit', $santri));
        $response->assertSessionHasErrors('daily_limit');
        $this->assertSame(50000.0, (float) $santri->fresh()->daily_limit);
    }
}
