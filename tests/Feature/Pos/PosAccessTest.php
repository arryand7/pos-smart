<?php

namespace Tests\Feature\Pos;

use App\Enums\UserRole;
use App\Models\Location;
use App\Models\Santri;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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

    public function test_cashier_history_is_read_only_and_scoped_to_assigned_location(): void
    {
        $assignedLocation = Location::factory()->create(['name' => 'Kantin Kasir']);
        $otherLocation = Location::factory()->create(['name' => 'Kantin Lain']);
        $kasir = User::factory()->create([
            'role' => UserRole::KASIR->value,
            'location_id' => $assignedLocation->id,
        ]);

        $visible = Transaction::create($this->transactionData($assignedLocation, $kasir, 'POS-VISIBLE'));
        Transaction::create($this->transactionData($otherLocation, $kasir, 'POS-HIDDEN'));

        Sanctum::actingAs($kasir, ['pos:manage']);

        $this->getJson('/api/pos/transactions?per_page=20')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $visible->id)
            ->assertJsonPath('data.0.reference', 'POS-VISIBLE')
            ->assertJsonPath('data.0.location.name', 'Kantin Kasir');
    }

    public function test_pos_renders_clickable_history_between_latest_receipt_and_print_button(): void
    {
        $kasir = User::factory()->create(['role' => UserRole::KASIR->value]);

        $response = $this->withSession([
            'smart_user' => [
                'id' => $kasir->id,
                'name' => $kasir->name,
                'role' => UserRole::KASIR->value,
            ],
            'smart_token' => 'test-pos-token',
        ])->get('/pos');

        $response->assertOk()
            ->assertSeeInOrder([
                'id="receipt-summary"',
                'id="transaction-history-btn"',
                'id="print-receipt-btn"',
            ], false)
            ->assertSee('Lihat riwayat transaksi')
            ->assertSee('id="transaction-history-modal"', false)
            ->assertSee('hanya lihat dan cetak ulang nota');
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

    public function test_pos_token_requires_pos_manage_ability(): void
    {
        $kasir = User::factory()->create(['role' => UserRole::KASIR->value]);
        Sanctum::actingAs($kasir, ['wallet:view']);

        $this->getJson('/api/pos/transactions')->assertForbidden();
    }

    public function test_santri_lookup_exposes_real_photo_url_for_cashier_verification(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('santris/student.jpg', 'photo-content');

        $santri = Santri::factory()->create([
            'name' => 'Santri Foto',
            'photo_path' => 'santris/student.jpg',
        ]);
        $kasir = User::factory()->create(['role' => UserRole::KASIR->value]);
        Sanctum::actingAs($kasir, ['pos:manage']);

        $this->getJson('/api/pos/santris?search=Santri+Foto')
            ->assertOk()
            ->assertJsonPath('data.0.id', $santri->id)
            ->assertJsonPath('data.0.photo_path', 'santris/student.jpg')
            ->assertJsonPath('data.0.photo_url', url('/media/santris/student.jpg'));
    }

    private function transactionData(Location $location, User $kasir, string $reference): array
    {
        return [
            'reference' => $reference,
            'client_transaction_id' => Str::uuid()->toString(),
            'type' => 'sale',
            'channel' => 'pos',
            'location_id' => $location->id,
            'kasir_id' => $kasir->id,
            'status' => 'completed',
            'sub_total' => 10000,
            'total_amount' => 10000,
            'cash_amount' => 10000,
            'paid_amount' => 10000,
            'primary_payment_method' => 'cash',
            'processed_at' => now(),
        ];
    }
}
