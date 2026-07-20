<?php

namespace Tests\Feature\Console;

use App\Models\Account;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosWalletCommandsTest extends TestCase
{
    use RefreshDatabase;

    public function test_read_only_audit_commands_pass_on_empty_consistent_database(): void
    {
        Account::create(['code' => '101', 'name' => 'Kas', 'type' => 'asset', 'is_active' => true]);
        Account::create(['code' => '202', 'name' => 'Wallet', 'type' => 'liability', 'is_active' => true]);
        Account::create(['code' => '401', 'name' => 'Pendapatan', 'type' => 'revenue', 'is_active' => true]);
        $this->artisan('smart:audit-money-columns')->assertSuccessful();
        $this->artisan('smart:reconcile-wallets')->assertSuccessful();
        $this->artisan('smart:pos-wallet-preflight')->assertSuccessful();
    }

    public function test_money_audit_reports_fractional_price_without_changing_it(): void
    {
        $product = Product::factory()->create(['sale_price' => '1000.50']);

        $this->artisan('smart:audit-money-columns')->assertFailed();
        $this->assertSame('1000.50', $product->fresh()->sale_price);
    }
}
