<?php

namespace Tests\Feature\Wallet;

use App\Enums\UserRole;
use App\Models\Santri;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Models\Wali;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WalletVisibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function seedWalletForSantri(Santri $santri): void
    {
        WalletTransaction::create([
            'santri_id' => $santri->id,
            'performed_by' => null,
            'type' => 'credit',
            'channel' => 'cash',
            'amount' => 10000,
            'balance_before' => 0,
            'balance_after' => 10000,
            'status' => 'completed',
            'description' => 'Setoran awal',
            'occurred_at' => now(),
        ]);
    }

    public function test_santri_can_view_their_own_wallet_history(): void
    {
        $santriUser = User::factory()->create([
            'role' => UserRole::SANTRI->value,
        ]);

        $santri = Santri::factory()->for($santriUser)->create();
        $this->seedWalletForSantri($santri);

        Sanctum::actingAs($santriUser, ['wallet:view-self']);

        $response = $this->getJson("/api/wallets/{$santri->id}/transactions");

        $response->assertOk()->assertJsonStructure(['data']);
    }

    public function test_other_santri_cannot_view_unrelated_wallet_history(): void
    {
        $santriAUser = User::factory()->create(['role' => UserRole::SANTRI->value]);
        $santriA = Santri::factory()->for($santriAUser)->create();
        $this->seedWalletForSantri($santriA);

        $santriBUser = User::factory()->create(['role' => UserRole::SANTRI->value]);
        Santri::factory()->for($santriBUser)->create();

        Sanctum::actingAs($santriBUser, ['wallet:view-self']);

        $this->getJson("/api/wallets/{$santriA->id}/transactions")->assertForbidden();
    }

    public function test_wali_can_view_associated_santri_wallet_history(): void
    {
        $waliUser = User::factory()->create(['role' => UserRole::WALI->value]);
        $wali = Wali::factory()->for($waliUser)->create();

        $santriUser = User::factory()->create(['role' => UserRole::SANTRI->value]);
        $santri = Santri::factory()->for($santriUser)->for($wali)->create();
        $this->seedWalletForSantri($santri);

        Sanctum::actingAs($waliUser, ['wallet:view']);

        $this->getJson("/api/wallets/{$santri->id}/transactions")->assertOk();
    }
}
