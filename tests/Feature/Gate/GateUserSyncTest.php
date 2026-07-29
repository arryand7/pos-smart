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
        config(['services.gate.url' => 'https://gate.test', 'services.gate.provisioning_client_id' => 'smart', 'services.gate.provisioning_client_secret' => 'secret']);
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
}
