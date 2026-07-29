<?php

namespace Tests\Feature\Gate;

use App\Models\GateSyncBatch;
use App\Models\Santri;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class GateUserSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.gate.url' => 'https://gate.test',
            'services.gate.provisioning_client_id' => 'smart',
            'services.gate.provisioning_client_secret' => 'secret',
            'services.gate.sync_enabled' => true,
            'services.gate.dry_run' => false,
            'services.gate.max_suspend_percent' => 100,
            'services.gate.max_role_change_percent' => 100,
            'services.gate.max_create_percent' => 100,
        ]);
    }

    public function test_preview_is_dry_run_and_superadmin_only(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'roles' => ['admin']]);
        $super = User::factory()->create(['role' => 'super_admin', 'roles' => ['super_admin']]);
        $before = User::count();
        Http::fake(['*/api/provisioning/users' => Http::response(['data' => [['gate_user_uuid' => (string) Str::uuid(), 'name' => 'New', 'email' => 'new@gate.test', 'role' => 'admin', 'status' => 'active', 'application_access' => true]]])]);
        $this->actingAs($admin)->withSession(['smart_user' => ['id' => $admin->id, 'role' => 'admin']])->post(route('admin.gate-sync.preview'))->assertForbidden();
        $this->assertSame($before, User::count());
        $this->actingAs($super)->withSession(['smart_user' => ['id' => $super->id, 'role' => 'super_admin']])->post(route('admin.gate-sync.preview'))->assertRedirect();
        $this->assertSame($before, User::count());
        $this->assertDatabaseHas('gate_sync_items', ['category' => 'missing_in_application']);
    }

    public function test_apply_is_once_only_preserves_password_and_report_failure_is_retryable(): void
    {
        $super = User::factory()->create(['role' => 'super_admin', 'roles' => ['super_admin']]);
        $target = User::factory()->create(['gate_user_uuid' => (string) Str::uuid(), 'name' => 'Old', 'email' => 'old@gate.test', 'role' => 'admin']);
        $password = $target->password;
        Http::fake([
            '*/api/provisioning/users' => Http::response(['data' => [['gate_user_uuid' => $target->gate_user_uuid, 'name' => 'Canonical', 'email' => $target->email, 'role' => 'admin', 'status' => 'active', 'application_access' => true]]]),
            '*/api/provisioning/sync-results' => Http::response([], 500),
        ]);
        $this->actingAs($super)->withSession(['smart_user' => ['id' => $super->id, 'role' => 'super_admin']])->post(route('admin.gate-sync.preview'));
        $batch = GateSyncBatch::latest()->first();
        $item = $batch->items()->where('local_user_id', $target->id)->first();
        $this->post(route('admin.gate-sync.apply', $batch), ['items' => [['id' => $item->id, 'action' => 'update_identity']]])->assertRedirect();
        $this->assertSame('Canonical', $target->fresh()->name);
        $this->assertSame($password, $target->fresh()->password);
        $this->assertSame('report_pending', $batch->fresh()->status);
        $this->post(route('admin.gate-sync.apply', $batch), ['items' => [['id' => $item->id, 'action' => 'update_identity']]])->assertSessionHasErrors('batch');
    }

    public function test_identity_update_does_not_change_financial_or_inventory_domains(): void
    {
        $super = User::factory()->create(['role' => 'super_admin', 'roles' => ['super_admin']]);
        $target = User::factory()->create(['gate_user_uuid' => (string) Str::uuid(), 'name' => 'Old', 'email' => 'santri@gate.test', 'role' => 'santri']);
        $santri = Santri::create(['user_id' => $target->id, 'nis' => 'GATE-001', 'name' => 'Old', 'wallet_balance' => 125000, 'daily_limit' => 30000, 'monthly_limit' => 400000]);
        $tables = ['wallet_transactions', 'transactions', 'transaction_items', 'products', 'inventory_movements', 'journal_entries', 'journal_lines', 'daily_closings'];
        $counts = collect($tables)->mapWithKeys(fn ($table) => [$table => DB::table($table)->count()]);
        Http::fake([
            '*/api/provisioning/users' => Http::response(['data' => [['gate_user_uuid' => $target->gate_user_uuid, 'name' => 'Canonical', 'email' => $target->email, 'role' => 'santri', 'nis' => 'GATE-001', 'status' => 'active', 'application_access' => true]]]),
            '*/api/provisioning/sync-results' => Http::response([], 200),
        ]);
        $this->actingAs($super)->withSession(['smart_user' => ['id' => $super->id, 'role' => 'super_admin']])->post(route('admin.gate-sync.preview'));
        $batch = GateSyncBatch::latest()->first();
        $item = $batch->items()->where('local_user_id', $target->id)->first();
        $this->post(route('admin.gate-sync.apply', $batch), ['items' => [['id' => $item->id, 'action' => 'update_identity']]])->assertRedirect();
        $santri->refresh();
        $this->assertSame('125000.00', $santri->wallet_balance);
        $this->assertSame('30000.00', $santri->daily_limit);
        $this->assertSame('400000.00', $santri->monthly_limit);
        foreach ($counts as $table => $count) {
            $this->assertSame($count, DB::table($table)->count(), $table.' changed during identity sync');
        }
    }

    public function test_empty_gate_response_is_a_hard_stop_without_mutation_or_batch(): void
    {
        $super = User::factory()->create(['role' => 'super_admin', 'roles' => ['super_admin']]);
        $before = User::count();
        Http::fake(['*/api/provisioning/users' => Http::response(['data' => []])]);

        $this->actingAs($super)
            ->withSession(['smart_user' => ['id' => $super->id, 'role' => 'super_admin']])
            ->post(route('admin.gate-sync.preview'))
            ->assertSessionHas('error');

        $this->assertSame($before, User::count());
        $this->assertDatabaseCount('gate_sync_batches', 0);
    }

    public function test_apply_is_blocked_when_suspend_threshold_is_exceeded(): void
    {
        config(['services.gate.max_suspend_percent' => 10]);
        $super = User::factory()->create(['role' => 'super_admin', 'roles' => ['super_admin']]);
        $target = User::factory()->create(['gate_user_uuid' => (string) Str::uuid(), 'status' => 'active']);
        Http::fake(['*/api/provisioning/users' => Http::response(['data' => [[
            'gate_user_uuid' => $target->gate_user_uuid,
            'name' => $target->name,
            'email' => $target->email,
            'role' => $target->role->value,
            'status' => 'active',
            'application_access' => false,
        ]]])]);

        $this->actingAs($super)->withSession(['smart_user' => ['id' => $super->id, 'role' => 'super_admin']]);
        $this->post(route('admin.gate-sync.preview'));
        $batch = GateSyncBatch::latest()->firstOrFail();
        $item = $batch->items()->where('local_user_id', $target->id)->firstOrFail();
        $this->post(route('admin.gate-sync.apply', $batch), ['items' => [['id' => $item->id, 'action' => 'suspend_local_user']]])
            ->assertSessionHasErrors('batch');

        $this->assertSame('active', $target->fresh()->status);
        $this->assertSame('ready', $batch->fresh()->status);
    }

    public function test_valid_suspend_revokes_tokens_and_reactivation_is_idempotent(): void
    {
        $super = User::factory()->create(['role' => 'super_admin', 'roles' => ['super_admin']]);
        $target = User::factory()->create(['gate_user_uuid' => (string) Str::uuid(), 'status' => 'active']);
        $tokenId = $target->createToken('gate-test')->accessToken->id;
        Http::fake([
            '*/api/provisioning/users' => Http::sequence()
                ->push(['data' => [['gate_user_uuid' => $target->gate_user_uuid, 'name' => $target->name, 'email' => $target->email, 'role' => $target->role->value, 'status' => 'inactive', 'application_access' => true]]])
                ->push(['data' => [['gate_user_uuid' => $target->gate_user_uuid, 'name' => $target->name, 'email' => $target->email, 'role' => $target->role->value, 'status' => 'active', 'application_access' => true]]]),
            '*/api/provisioning/sync-results' => Http::response([], 200),
        ]);
        $this->actingAs($super)->withSession(['smart_user' => ['id' => $super->id, 'role' => 'super_admin']]);

        $this->post(route('admin.gate-sync.preview'));
        $suspendBatch = GateSyncBatch::latest()->firstOrFail();
        $suspendItem = $suspendBatch->items()->where('local_user_id', $target->id)->firstOrFail();
        $this->post(route('admin.gate-sync.apply', $suspendBatch), ['items' => [['id' => $suspendItem->id, 'action' => 'suspend_local_user']]]);
        $this->assertSame('suspended', $target->fresh()->status);
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $tokenId]);

        $this->post(route('admin.gate-sync.preview'));
        $reactivateBatch = GateSyncBatch::orderByDesc('id')->firstOrFail();
        $reactivateItem = $reactivateBatch->items()->where('local_user_id', $target->id)->firstOrFail();
        $this->post(route('admin.gate-sync.apply', $reactivateBatch), ['items' => [['id' => $reactivateItem->id, 'action' => 'reactivate_local_user']]])
            ->assertSessionHasNoErrors();
        $this->assertSame('active', $target->fresh()->status);
        $this->post(route('admin.gate-sync.apply', $reactivateBatch), ['items' => [['id' => $reactivateItem->id, 'action' => 'reactivate_local_user']]])
            ->assertSessionHasErrors('batch');
    }

    public function test_partial_item_failure_is_persisted_without_rolling_back_successful_items(): void
    {
        config(['services.gate.max_create_percent' => 300]);
        $super = User::factory()->create(['role' => 'super_admin', 'roles' => ['super_admin']]);
        Http::fake([
            '*/api/provisioning/users' => Http::response(['data' => [
                ['gate_user_uuid' => (string) Str::uuid(), 'name' => 'Valid', 'email' => 'valid@gate.test', 'role' => 'admin', 'status' => 'active', 'application_access' => true],
                ['gate_user_uuid' => (string) Str::uuid(), 'name' => 'Unknown', 'email' => 'unknown@gate.test', 'role' => 'unknown-role', 'status' => 'active', 'application_access' => true],
            ]]),
            '*/api/provisioning/sync-results' => Http::response([], 200),
        ]);
        $this->actingAs($super)->withSession(['smart_user' => ['id' => $super->id, 'role' => 'super_admin']]);

        $this->post(route('admin.gate-sync.preview'));
        $batch = GateSyncBatch::latest()->firstOrFail();
        $this->post(route('admin.gate-sync.apply', $batch))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', ['email' => 'valid@gate.test', 'role' => 'admin']);
        $this->assertDatabaseMissing('users', ['email' => 'unknown@gate.test']);
        $this->assertDatabaseHas('gate_sync_items', ['batch_id' => $batch->id, 'result_status' => 'failed', 'error_code' => 'SYNC_ROLE_MAPPING_FAILED']);
        $this->assertSame('completed', $batch->fresh()->status);
    }
}
