<?php

namespace Tests\Feature\Pos;

use App\Enums\UserRole;
use App\Models\Location;
use App\Models\Product;
use App\Models\Santri;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CreatePosTransactionTest extends TestCase
{
    use RefreshDatabase;

    public function test_wallet_payment_creates_transaction_wallet_debit_and_journal(): void
    {
        $location = Location::factory()->create();
        $product = Product::factory()->for($location)->create([
            'sale_price' => 5000,
        ]);

        $santriUser = User::factory()->create([
            'role' => UserRole::SANTRI->value,
        ]);
        $santri = Santri::factory()->for($santriUser)->create([
            'wallet_balance' => 50000,
        ]);

        $kasir = User::factory()->create([
            'role' => UserRole::KASIR->value,
        ]);

        Sanctum::actingAs($kasir, ['pos:manage']);

        $payload = [
            'reference' => 'POS-TEST-001',
            'location_id' => $location->id,
            'santri_id' => $santri->id,
            'payments' => [
                'cash' => 0,
                'wallet' => 15000,
                'gateway' => 0,
            ],
            'items' => [
                [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'quantity' => 3,
                    'unit_price' => 5000,
                    'discount_amount' => 0,
                ],
            ],
        ];

        $response = $this->postJson('/api/pos/transactions', $payload);

        $response->assertCreated()
            ->assertJsonFragment([
                'reference' => 'POS-TEST-001',
                'total_amount' => '15000.00',
                'wallet_amount' => '15000.00',
            ]);

        $transaction = Transaction::where('reference', 'POS-TEST-001')->first();
        $this->assertNotNull($transaction, 'Transaction should be persisted');
        $this->assertCount(1, $transaction->items);

        $this->assertSame(35000.00, (float) $santri->fresh()->wallet_balance);

        $walletTransaction = $santri->walletTransactions()->latest()->first();
        $this->assertNotNull($walletTransaction);
        $this->assertSame('debit', $walletTransaction->type);
        $this->assertEquals(15000.00, (float) $walletTransaction->amount);
        $this->assertEquals(Transaction::class, $walletTransaction->reference_type);
        $this->assertEquals($transaction->id, $walletTransaction->reference_id);

        $journal = $transaction->journalEntries()->first();
        $this->assertNotNull($journal, 'Journal entry should be created');
        $this->assertEquals('POS-POS-TEST-001', $journal->reference);
        $this->assertEquals(15000.00, (float) $journal->total_debit);
        $this->assertEquals(15000.00, (float) $journal->total_credit);
        $this->assertCount(2, $journal->lines);

        $this->assertDatabaseCount('journal_lines', 2);
        $this->assertDatabaseHas('journal_lines', [
            'journal_entry_id' => $journal->id,
            'type' => 'debit',
            'amount' => 15000.00,
        ]);
        $this->assertDatabaseHas('journal_lines', [
            'journal_entry_id' => $journal->id,
            'type' => 'credit',
            'amount' => 15000.00,
        ]);
    }

    public function test_mixed_cash_wallet_payment_updates_inventory_and_balances(): void
    {
        $location = Location::factory()->create();
        $product = Product::factory()->for($location)->create([
            'stock' => 20,
            'sale_price' => 5000,
            'cost_price' => 2500,
        ]);

        $santriUser = User::factory()->create([
            'role' => UserRole::SANTRI->value,
        ]);
        $santri = Santri::factory()->for($santriUser)->create([
            'wallet_balance' => 20000,
        ]);

        $kasir = User::factory()->create([
            'role' => UserRole::KASIR->value,
        ]);

        Sanctum::actingAs($kasir, ['pos:manage']);

        $payload = [
            'reference' => 'POS-TEST-002',
            'location_id' => $location->id,
            'santri_id' => $santri->id,
            'payments' => [
                'cash' => 5000,
                'wallet' => 5000,
                'gateway' => 0,
            ],
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 2,
                    'unit_price' => 5000,
                    'discount_amount' => 0,
                ],
            ],
        ];

        $response = $this->postJson('/api/pos/transactions', $payload);

        $response->assertCreated()
            ->assertJsonFragment([
                'reference' => 'POS-TEST-002',
                'total_amount' => '10000.00',
                'wallet_amount' => '5000.00',
                'cash_amount' => '5000.00',
            ]);

        $transaction = Transaction::where('reference', 'POS-TEST-002')->firstOrFail();

        $this->assertSame(15000.00, (float) $santri->fresh()->wallet_balance);

        $walletEntry = $santri->walletTransactions()->latest()->first();
        $this->assertEquals(5000.00, (float) $walletEntry->amount);
        $this->assertSame('debit', $walletEntry->type);

        $this->assertSame(18, $product->fresh()->stock);

        $movement = $product->inventoryMovements()->where('reference_id', $transaction->id)->first();
        $this->assertNotNull($movement);
        $this->assertEquals(-2, $movement->quantity_change);

        $journal = $transaction->journalEntries()->first();
        $this->assertNotNull($journal);
        $this->assertCount(3, $journal->lines);
        $this->assertEquals(10000.00, (float) $journal->total_debit);
        $this->assertEquals(10000.00, (float) $journal->total_credit);
    }
}
