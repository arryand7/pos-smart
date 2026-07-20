<?php

namespace Tests\Feature\Pos;

use App\Enums\UserRole;
use App\Models\Account;
use App\Models\InventoryMovement;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\Location;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Santri;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\Payment\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Mockery\MockInterface;
use Tests\TestCase;

class CreatePosTransactionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Account::create(['code' => '101', 'name' => 'Kas', 'type' => 'asset', 'is_active' => true]);
        Account::create(['code' => '202', 'name' => 'Liabilitas Wallet', 'type' => 'liability', 'is_active' => true]);
        Account::create(['code' => '401', 'name' => 'Pendapatan Penjualan', 'type' => 'revenue', 'is_active' => true]);
    }

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
            'location_id' => $location->id,
        ]);

        Sanctum::actingAs($kasir, ['pos:manage']);

        $payload = [
            'client_transaction_id' => Str::uuid()->toString(),
            'location_id' => $location->id,
            'santri_id' => $santri->id,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 3,
                ],
            ],
        ];

        $response = $this->postJson('/api/pos/transactions', $payload);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonFragment([
                'total_amount' => '15000.00',
                'wallet_amount' => '15000.00',
            ]);

        $transaction = Transaction::where('client_transaction_id', $payload['client_transaction_id'])->first();
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
        $this->assertStringStartsWith('POS-POS-', $journal->reference);
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

    public function test_wallet_payment_updates_inventory_and_balances(): void
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
            'location_id' => $location->id,
        ]);

        Sanctum::actingAs($kasir, ['pos:manage']);

        $payload = [
            'client_transaction_id' => Str::uuid()->toString(),
            'location_id' => $location->id,
            'santri_id' => $santri->id,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 2,
                ],
            ],
        ];

        $response = $this->postJson('/api/pos/transactions', $payload);

        $response->assertCreated()
            ->assertJsonFragment([
                'total_amount' => '10000.00',
                'wallet_amount' => '10000.00',
                'cash_amount' => '0.00',
            ]);

        $transaction = Transaction::where('client_transaction_id', $payload['client_transaction_id'])->firstOrFail();

        $this->assertSame(10000.00, (float) $santri->fresh()->wallet_balance);

        $walletEntry = $santri->walletTransactions()->latest()->first();
        $this->assertEquals(10000.00, (float) $walletEntry->amount);
        $this->assertSame('debit', $walletEntry->type);

        $this->assertSame(18, $product->fresh()->stock);

        $movement = $product->inventoryMovements()->where('reference_id', $transaction->id)->first();
        $this->assertNotNull($movement);
        $this->assertEquals(-2, $movement->quantity_change);

        $journal = $transaction->journalEntries()->first();
        $this->assertNotNull($journal);
        $this->assertCount(2, $journal->lines);
        $this->assertEquals(10000.00, (float) $journal->total_debit);
        $this->assertEquals(10000.00, (float) $journal->total_credit);
    }

    public function test_runtime_wallet_checkout_commits_all_required_records_and_response_contract(): void
    {
        $location = Location::factory()->create();
        $product = Product::factory()->for($location)->create([
            'stock' => 10,
            'sale_price' => 15000,
            'cost_price' => 7000,
        ]);
        $santri = Santri::factory()->create(['wallet_balance' => 100000]);
        $kasir = User::factory()->create([
            'role' => UserRole::KASIR->value,
            'location_id' => $location->id,
        ]);
        Sanctum::actingAs($kasir, ['pos:manage']);

        $before = [
            'transactions' => Transaction::count(),
            'items' => TransactionItem::count(),
            'wallet' => WalletTransaction::count(),
            'movements' => InventoryMovement::count(),
            'journals' => JournalEntry::count(),
            'lines' => JournalLine::count(),
        ];

        $response = $this->postJson('/api/pos/transactions', [
            'client_transaction_id' => Str::uuid()->toString(),
            'location_id' => $location->id,
            'santri_id' => $santri->id,
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.total_amount', '30000.00')
            ->assertJsonPath('data.wallet_balance_before', 100000)
            ->assertJsonPath('data.wallet_balance_after', 70000);

        $this->assertSame(70000.0, (float) $santri->fresh()->wallet_balance);
        $this->assertSame(8, $product->fresh()->stock);
        $this->assertSame($before['transactions'] + 1, Transaction::count());
        $this->assertSame($before['items'] + 1, TransactionItem::count());
        $this->assertSame($before['wallet'] + 1, WalletTransaction::count());
        $this->assertSame($before['movements'] + 1, InventoryMovement::count());
        $this->assertSame($before['journals'] + 1, JournalEntry::count());
        $this->assertSame($before['lines'] + 2, JournalLine::count());
        $this->assertDatabaseHas('wallet_transactions', ['santri_id' => $santri->id, 'type' => 'debit', 'amount' => 30000]);
        $this->assertDatabaseHas('inventory_movements', ['product_id' => $product->id, 'quantity_change' => -2]);
        $this->assertDatabaseHas('journal_entries', ['total_debit' => 30000, 'total_credit' => 30000]);
    }

    public function test_cash_checkout_is_completed_without_debiting_wallet(): void
    {
        $location = Location::factory()->create();
        $product = Product::factory()->for($location)->create(['stock' => 3, 'sale_price' => 15000]);
        $kasir = User::factory()->create(['role' => UserRole::KASIR->value, 'location_id' => $location->id]);
        Sanctum::actingAs($kasir, ['pos:manage']);

        $this->postJson('/api/pos/transactions', [
            'client_transaction_id' => Str::uuid()->toString(),
            'location_id' => $location->id,
            'payment_method' => 'cash',
            'cash_received' => 20000,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertCreated()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.primary_payment_method', 'cash')
            ->assertJsonPath('data.cash_amount', '15000.00')
            ->assertJsonPath('data.paid_amount', '20000.00')
            ->assertJsonPath('data.change_amount', '5000.00');

        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->assertDatabaseCount('journal_entries', 1);
        $this->assertSame(2, $product->fresh()->stock);
    }

    public function test_gateway_checkout_returns_pending_redirect_without_wallet_debit_or_journal(): void
    {
        $location = Location::factory()->create();
        $product = Product::factory()->for($location)->create(['stock' => 3, 'sale_price' => 15000]);
        $kasir = User::factory()->create(['role' => UserRole::KASIR->value, 'location_id' => $location->id]);

        $this->mock(PaymentService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('initiatePosGateway')->once()->andReturnUsing(function (Transaction $transaction, int $amount): Payment {
                $payment = new Payment([
                    'provider' => 'test',
                    'amount' => $amount,
                    'currency' => 'IDR',
                    'status' => 'pending',
                    'metadata' => ['redirect_url' => 'https://gateway.test/pay/123'],
                ]);
                $payment->payable()->associate($transaction);
                $payment->save();

                return $payment;
            });
        });

        Sanctum::actingAs($kasir, ['pos:manage']);

        $this->postJson('/api/pos/transactions', [
            'client_transaction_id' => Str::uuid()->toString(),
            'location_id' => $location->id,
            'payment_method' => 'gateway',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.primary_payment_method', 'gateway')
            ->assertJsonPath('data.gateway_amount', '15000.00')
            ->assertJsonPath('data.payment_redirect_url', 'https://gateway.test/pay/123');

        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->assertDatabaseCount('journal_entries', 0);
        $this->assertSame(2, $product->fresh()->stock);
    }

    public function test_server_price_is_authoritative_and_duplicate_request_is_idempotent(): void
    {
        $location = Location::factory()->create();
        $product = Product::factory()->for($location)->create(['sale_price' => 7000, 'stock' => 5]);
        $santri = Santri::factory()->create(['wallet_balance' => 20000]);
        $kasir = User::factory()->create(['role' => UserRole::KASIR->value, 'location_id' => $location->id]);
        Sanctum::actingAs($kasir, ['pos:manage']);
        $payload = ['client_transaction_id' => Str::uuid()->toString(), 'location_id' => $location->id,
            'santri_id' => $santri->id, 'items' => [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 1]]];

        $this->postJson('/api/pos/transactions', $payload)->assertCreated()->assertJsonPath('data.total_amount', '7000.00');
        $this->postJson('/api/pos/transactions', $payload)->assertCreated()->assertJsonPath('data.total_amount', '7000.00');
        $changedPayload = $payload;
        $changedPayload['items'][0]['quantity'] = 2;
        $this->postJson('/api/pos/transactions', $changedPayload)
            ->assertConflict()->assertJsonPath('code', 'IDEMPOTENCY_KEY_REUSED');

        $this->assertDatabaseCount('transactions', 1);
        $this->assertDatabaseCount('wallet_transactions', 1);
        $this->assertSame(13000.0, (float) $santri->fresh()->wallet_balance);
        $this->assertSame(4, $product->fresh()->stock);
    }

    public function test_insufficient_balance_rolls_back_inventory_and_financial_records(): void
    {
        $location = Location::factory()->create();
        $product = Product::factory()->for($location)->create(['sale_price' => 10000, 'stock' => 1]);
        $santri = Santri::factory()->create(['wallet_balance' => 5000]);
        $kasir = User::factory()->create(['role' => UserRole::KASIR->value, 'location_id' => $location->id]);
        Sanctum::actingAs($kasir, ['pos:manage']);

        $this->postJson('/api/pos/transactions', ['client_transaction_id' => Str::uuid()->toString(),
            'location_id' => $location->id, 'santri_id' => $santri->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1]]])
            ->assertUnprocessable()->assertJsonPath('code', 'INSUFFICIENT_WALLET_BALANCE');

        $this->assertDatabaseCount('transactions', 0);
        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertSame(1, $product->fresh()->stock);
    }

    public function test_cashier_cannot_checkout_at_unassigned_location(): void
    {
        $assigned = Location::factory()->create();
        $requested = Location::factory()->create();
        $product = Product::factory()->for($requested)->create(['sale_price' => 5000, 'stock' => 1]);
        $santri = Santri::factory()->create(['wallet_balance' => 10000]);
        $kasir = User::factory()->create(['role' => UserRole::KASIR->value, 'location_id' => $assigned->id]);
        Sanctum::actingAs($kasir, ['pos:manage']);

        $this->postJson('/api/pos/transactions', ['client_transaction_id' => Str::uuid()->toString(),
            'location_id' => $requested->id, 'santri_id' => $santri->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1]]])
            ->assertForbidden()->assertJsonPath('code', 'CASHIER_LOCATION_NOT_ALLOWED');

        $this->assertDatabaseCount('transactions', 0);
        $this->assertSame(10000.0, (float) $santri->fresh()->wallet_balance);
        $this->assertSame(1, $product->fresh()->stock);
    }

    public function test_daily_limit_failure_rolls_back_second_transaction(): void
    {
        $location = Location::factory()->create();
        $product = Product::factory()->for($location)->create(['sale_price' => 6000, 'stock' => 2]);
        $santri = Santri::factory()->create(['wallet_balance' => 20000, 'daily_limit' => 10000]);
        $kasir = User::factory()->create(['role' => UserRole::KASIR->value, 'location_id' => $location->id]);
        Sanctum::actingAs($kasir, ['pos:manage']);
        $payload = fn () => ['client_transaction_id' => Str::uuid()->toString(), 'location_id' => $location->id,
            'santri_id' => $santri->id, 'items' => [['product_id' => $product->id, 'quantity' => 1]]];

        $this->postJson('/api/pos/transactions', $payload())->assertCreated();
        $this->postJson('/api/pos/transactions', $payload())
            ->assertUnprocessable()->assertJsonPath('code', 'DAILY_LIMIT_EXCEEDED');

        $this->assertDatabaseCount('transactions', 1);
        $this->assertDatabaseCount('wallet_transactions', 1);
        $this->assertDatabaseCount('inventory_movements', 1);
        $this->assertDatabaseCount('journal_entries', 1);
        $this->assertSame(14000.0, (float) $santri->fresh()->wallet_balance);
        $this->assertSame(1, $product->fresh()->stock);
    }

    public function test_fractional_database_price_is_rejected_without_changes(): void
    {
        $location = Location::factory()->create();
        $product = Product::factory()->for($location)->create(['sale_price' => '5000.50', 'stock' => 1]);
        $santri = Santri::factory()->create(['wallet_balance' => 10000]);
        $kasir = User::factory()->create(['role' => UserRole::KASIR->value, 'location_id' => $location->id]);
        Sanctum::actingAs($kasir, ['pos:manage']);

        $this->postJson('/api/pos/transactions', ['client_transaction_id' => Str::uuid()->toString(),
            'location_id' => $location->id, 'santri_id' => $santri->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1]]])
            ->assertStatus(500)->assertJsonPath('code', 'INVALID_MONEY_VALUE');

        $this->assertDatabaseCount('transactions', 0);
        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->assertSame(1, $product->fresh()->stock);
    }

    public function test_missing_account_configuration_rolls_back_everything(): void
    {
        config(['smart.accounting.accounts.wallet_liability' => null]);
        $location = Location::factory()->create();
        $product = Product::factory()->for($location)->create(['sale_price' => 5000, 'stock' => 1]);
        $santri = Santri::factory()->create(['wallet_balance' => 10000]);
        $kasir = User::factory()->create(['role' => UserRole::KASIR->value, 'location_id' => $location->id]);
        Sanctum::actingAs($kasir, ['pos:manage']);

        $this->postJson('/api/pos/transactions', ['client_transaction_id' => Str::uuid()->toString(),
            'location_id' => $location->id, 'santri_id' => $santri->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1]]])
            ->assertStatus(500)->assertJsonPath('code', 'ACCOUNTING_CONFIGURATION_MISSING')
            ->assertJsonPath('message', 'Transaksi belum dapat diproses karena konfigurasi akun keuangan belum lengkap. Hubungi administrator.');

        $this->assertDatabaseCount('transactions', 0);
        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertDatabaseCount('journal_entries', 0);
        $this->assertSame(10000.0, (float) $santri->fresh()->wallet_balance);
        $this->assertSame(1, $product->fresh()->stock);
    }

    public function test_offline_sync_endpoint_rejects_wallet_transactions(): void
    {
        $kasir = User::factory()->create(['role' => UserRole::KASIR->value]);
        Sanctum::actingAs($kasir, ['pos:manage']);

        $this->postJson('/api/pos/transactions/offline-sync', ['transactions' => []])
            ->assertConflict()->assertJsonPath('code', 'OFFLINE_WALLET_NOT_ALLOWED');
    }

    public function test_cancellation_creates_wallet_and_journal_reversals_without_mutating_debit(): void
    {
        $location = Location::factory()->create();
        $product = Product::factory()->for($location)->create(['sale_price' => 5000, 'cost_price' => 2000, 'stock' => 1]);
        $santri = Santri::factory()->create(['wallet_balance' => 10000]);
        $cashier = User::factory()->create(['role' => UserRole::KASIR->value, 'location_id' => $location->id]);
        Sanctum::actingAs($cashier, ['pos:manage']);
        $payload = ['client_transaction_id' => Str::uuid()->toString(), 'location_id' => $location->id,
            'santri_id' => $santri->id, 'items' => [['product_id' => $product->id, 'quantity' => 1]]];
        $this->postJson('/api/pos/transactions', $payload)->assertCreated();

        $transaction = Transaction::where('client_transaction_id', $payload['client_transaction_id'])->firstOrFail();
        $debit = $transaction->walletTransactions()->where('type', 'debit')->firstOrFail();
        app(\App\Services\POS\PosService::class)->cancelTransaction($transaction, $cashier, 'Test reversal');

        $credit = $transaction->walletTransactions()->where('type', 'credit')->firstOrFail();
        $this->assertSame($debit->id, $credit->reversed_transaction_id);
        $this->assertSame('debit', $debit->fresh()->type);
        $this->assertSame(10000.0, (float) $santri->fresh()->wallet_balance);
        $this->assertSame(1, $product->fresh()->stock);
        $this->assertDatabaseHas('journal_entries', [
            'source_type' => Transaction::class, 'source_id' => $transaction->id, 'journal_type' => 'reversal',
        ]);

        $this->expectException(\LogicException::class);
        $debit->update(['description' => 'Tidak boleh diubah']);
    }
}
