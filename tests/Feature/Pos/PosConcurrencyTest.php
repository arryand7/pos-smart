<?php

namespace Tests\Feature\Pos;

use App\Enums\UserRole;
use App\Models\Account;
use App\Models\Location;
use App\Models\Product;
use App\Models\Santri;
use App\Models\User;
use App\Services\POS\PosService;
use App\Services\POS\PosTransactionException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class PosConcurrencyTest extends TestCase
{
    use DatabaseMigrations {
        runDatabaseMigrations as private runMigrations;
    }

    public function runDatabaseMigrations(): void
    {
        $driver = config('database.default');
        $database = (string) config("database.connections.{$driver}.database");
        if ($driver === 'sqlite') {
            $this->markTestSkipped('Concurrency test tidak menggunakan SQLite karena tidak memiliki row-level locking yang dibutuhkan.');
        }
        if (! preg_match('/(^|[_-])(test|testing)([_-]|$)/i', $database)) {
            $this->markTestSkipped('Concurrency migration hanya boleh berjalan pada database test terisolasi.');
        }
        $this->runMigrations();
    }

    public function test_concurrent_duplicate_uuid_returns_same_transaction_once(): void
    {
        $this->requireRowLockingDatabase();
        [$location, $product, $santri, $cashier] = $this->fixtures();
        $payload = ['client_transaction_id' => Str::uuid()->toString(), 'location_id' => $location->id,
            'santri_id' => $santri->id, 'items' => [['product_id' => $product->id, 'quantity' => 1]]];

        $results = $this->concurrently([$payload, $payload], $cashier->id);

        $this->assertSame(['success', 'success'], collect($results)->pluck('status')->sort()->values()->all(), json_encode($results));
        $this->assertCount(1, collect($results)->pluck('transaction_id')->unique());
        $this->assertDatabaseCount('transactions', 1);
        $this->assertDatabaseCount('wallet_transactions', 1);
        $this->assertDatabaseCount('inventory_movements', 1);
        $this->assertDatabaseCount('journal_entries', 1);
        $this->assertSame(4, $product->fresh()->stock);
        $this->assertSame(43000.0, (float) $santri->fresh()->wallet_balance);
    }

    public function test_concurrent_spend_over_daily_limit_allows_only_one(): void
    {
        $this->requireRowLockingDatabase();
        [$location, $product, $santri, $cashier] = $this->fixtures([
            'sale_price' => 6000, 'stock' => 2,
        ], ['wallet_balance' => 20000, 'daily_limit' => 10000]);
        $make = fn () => ['client_transaction_id' => Str::uuid()->toString(), 'location_id' => $location->id,
            'santri_id' => $santri->id, 'items' => [['product_id' => $product->id, 'quantity' => 1]]];

        $results = $this->concurrently([$make(), $make()], $cashier->id);

        $this->assertSame(['DAILY_LIMIT_EXCEEDED', 'success'], collect($results)->pluck('status')->sort()->values()->all(), json_encode($results));
        $this->assertDatabaseCount('transactions', 1);
        $this->assertDatabaseCount('wallet_transactions', 1);
        $this->assertDatabaseCount('inventory_movements', 1);
        $this->assertDatabaseCount('journal_entries', 1);
        $this->assertSame(1, $product->fresh()->stock);
        $this->assertSame(14000.0, (float) $santri->fresh()->wallet_balance);
    }

    private function requireRowLockingDatabase(): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'pgsql'], true) || ! function_exists('pcntl_fork')) {
            $this->markTestSkipped('Memerlukan MySQL/PostgreSQL dan ekstensi pcntl.');
        }
    }

    private function fixtures(array $productAttributes = [], array $santriAttributes = []): array
    {
        Account::create(['code' => '101', 'name' => 'Kas', 'type' => 'asset', 'is_active' => true]);
        Account::create(['code' => '202', 'name' => 'Liabilitas Wallet', 'type' => 'liability', 'is_active' => true]);
        Account::create(['code' => '401', 'name' => 'Pendapatan Penjualan', 'type' => 'revenue', 'is_active' => true]);
        $location = Location::factory()->create();
        $product = Product::factory()->for($location)->create(array_merge([
            'sale_price' => 7000, 'stock' => 5,
        ], $productAttributes));
        $santri = Santri::factory()->create(array_merge(['wallet_balance' => 50000], $santriAttributes));
        $cashier = User::factory()->create(['role' => UserRole::KASIR->value, 'location_id' => $location->id]);

        return [$location, $product, $santri, $cashier];
    }

    private function concurrently(array $payloads, int $cashierId): array
    {
        $directory = sys_get_temp_dir().'/smart-pos-'.Str::uuid();
        mkdir($directory, 0700, true);
        $children = [];

        foreach ($payloads as $index => $payload) {
            $pid = pcntl_fork();
            if ($pid === 0) {
                DB::disconnect();
                DB::reconnect();
                file_put_contents("{$directory}/ready-{$index}", '1');
                while (! file_exists("{$directory}/start")) {
                    usleep(1000);
                }
                try {
                    $transaction = app(PosService::class)->createTransaction($payload, User::findOrFail($cashierId));
                    $result = ['status' => 'success', 'transaction_id' => $transaction->id];
                } catch (PosTransactionException $exception) {
                    $result = ['status' => $exception->errorCode];
                } catch (\Throwable $exception) {
                    $result = ['status' => 'unexpected', 'class' => $exception::class, 'message' => $exception->getMessage()];
                }
                file_put_contents("{$directory}/result-{$index}.json", json_encode($result, JSON_THROW_ON_ERROR));
                exit(0);
            }
            $children[] = $pid;
        }

        $deadline = microtime(true) + 10;
        while (count(glob("{$directory}/ready-*")) < count($payloads) && microtime(true) < $deadline) {
            usleep(1000);
        }
        file_put_contents("{$directory}/start", '1');
        foreach ($children as $pid) {
            pcntl_waitpid($pid, $status);
        }

        $results = [];
        foreach (array_keys($payloads) as $index) {
            $results[] = json_decode(file_get_contents("{$directory}/result-{$index}.json"), true, flags: JSON_THROW_ON_ERROR);
        }
        foreach (glob("{$directory}/*") as $file) {
            unlink($file);
        }
        rmdir($directory);
        DB::disconnect();
        DB::reconnect();

        return $results;
    }
}
