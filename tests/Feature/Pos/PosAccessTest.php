<?php

namespace Tests\Feature\Pos;

use App\Enums\UserRole;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PosAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_kasir_can_access_pos_transactions(): void
    {
        Location::factory()->create();

        $kasir = User::factory()->create([
            'role' => UserRole::KASIR->value,
        ]);

        Sanctum::actingAs($kasir, ['pos:manage']);

        $response = $this->getJson('/api/pos/transactions');

        $response->assertOk();
    }

    public function test_non_kasir_roles_are_blocked_from_pos_routes(): void
    {
        Location::factory()->create();

        $wali = User::factory()->create([
            'role' => UserRole::WALI->value,
        ]);

        Sanctum::actingAs($wali, ['wallet:view']);

        $response = $this->getJson('/api/pos/transactions');

        $response->assertForbidden();
    }
}
