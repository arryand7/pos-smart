<?php

namespace Tests\Feature\Portal;

use App\Enums\UserRole;
use App\Models\Location;
use App\Models\Payment;
use App\Models\Santri;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SantriPortalTest extends TestCase
{
    use RefreshDatabase;

    public function test_santri_can_view_portal_and_recent_activity(): void
    {
        $santriUser = User::factory()->create(['role' => UserRole::SANTRI->value]);
        $santri = Santri::factory()->for($santriUser)->create([
            'wallet_balance' => 80000,
            'daily_limit' => 20000,
            'monthly_limit' => 150000,
        ]);

        WalletTransaction::create([
            'santri_id' => $santri->id,
            'performed_by' => null,
            'type' => 'credit',
            'channel' => 'topup',
            'amount' => 80000,
            'balance_before' => 0,
            'balance_after' => 80000,
            'status' => 'completed',
            'description' => 'Setoran wali',
            'occurred_at' => now(),
        ]);

        $location = Location::factory()->create();
        $kasir = User::factory()->create(['role' => UserRole::KASIR->value]);

        Transaction::create([
            'reference' => 'POS-123',
            'type' => 'pos',
            'channel' => 'pos',
            'location_id' => $location->id,
            'kasir_id' => $kasir->id,
            'santri_id' => $santri->id,
            'status' => 'completed',
            'sub_total' => 12000,
            'total_amount' => 12000,
            'paid_amount' => 12000,
            'wallet_amount' => 12000,
            'processed_at' => now(),
        ]);

        Payment::create([
            'provider' => 'ipaymu',
            'status' => 'pending',
            'amount' => 45000,
            'currency' => 'IDR',
            'santri_id' => $santri->id,
        ]);

        $response = $this
            ->actingAs($santriUser)
            ->withSession($this->sessionPayload($santriUser))
            ->get(route('portal.santri'));

        $response->assertOk()
            ->assertSeeText($santri->nickname ?? $santri->name)
            ->assertSeeText('Saldo Dompet')
            ->assertSeeText('Riwayat Dompet')
            ->assertSeeText('Belanja POS Terakhir')
            ->assertSeeText('Status Top-up')
            ->assertSeeText('Pending');
    }

    protected function sessionPayload(User $user): array
    {
        $role = $user->role instanceof UserRole ? $user->role->value : $user->role;

        return [
            'smart_user' => [
                'id' => $user->id,
                'name' => $user->name,
                'role' => $role,
            ],
        ];
    }
}
