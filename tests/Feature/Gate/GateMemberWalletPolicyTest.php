<?php

namespace Tests\Feature\Gate;

use App\Models\Santri;
use App\Models\User;
use App\Services\Gate\GateUserReconciliationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class GateMemberWalletPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_application_role_does_not_change_existing_student_or_operational_role(): void
    {
        $student = User::factory()->create(['role' => 'santri', 'roles' => ['santri'], 'gate_user_uuid' => (string) Str::uuid()]);
        Santri::create(['user_id' => $student->id, 'nis' => 'NIS-001', 'name' => $student->name]);
        $cashier = User::factory()->create(['role' => 'kasir', 'roles' => ['kasir'], 'gate_user_uuid' => (string) Str::uuid()]);

        $items = collect(app(GateUserReconciliationService::class)->reconcile([
            $this->gate($student, 'student', 'NIS-001'),
            $this->gate($cashier, 'staff'),
        ]));

        $this->assertSame('matched', $items->firstWhere('local_user_id', $student->id)['category']);
        $this->assertSame('matched', $items->firstWhere('local_user_id', $cashier->id)['category']);
    }

    public function test_new_student_becomes_santri_and_new_nonstudent_becomes_member_with_wallets(): void
    {
        config([
            'services.gate.url' => 'https://gate.test',
            'services.gate.provisioning_client_id' => 'smart',
            'services.gate.provisioning_client_secret' => 'secret',
            'services.gate.sync_enabled' => true,
            'services.gate.dry_run' => false,
            'services.gate.max_create_percent' => 500,
        ]);
        $super = User::factory()->create(['role' => 'super_admin', 'roles' => ['super_admin']]);
        Http::fake([
            '*/api/provisioning/users' => Http::response(['data' => [
                $this->newGate('student', 'student@gate.test', 'NIS-NEW'),
                $this->newGate('teacher', 'teacher@gate.test'),
            ]]),
            '*/api/provisioning/sync-results' => Http::response([], 200),
        ]);

        $this->actingAs($super)->withSession(['smart_user' => ['id' => $super->id, 'role' => 'super_admin']]);
        $this->post(route('admin.gate-sync.preview'));
        $batch = \App\Models\GateSyncBatch::latest()->firstOrFail();
        $this->post(route('admin.gate-sync.apply', $batch))->assertSessionHasNoErrors();

        $student = User::where('email', 'student@gate.test')->firstOrFail();
        $member = User::where('email', 'teacher@gate.test')->firstOrFail();
        $this->assertTrue($student->hasRole('santri'));
        $this->assertNotNull($student->santri);
        $this->assertNotNull($student->wallet);
        $this->assertTrue($member->hasRole('member'));
        $this->assertNotNull($member->wallet);
        $this->assertNull($super->fresh()->wallet);
    }

    public function test_wallet_backfill_is_previewable_idempotent_and_excludes_superadmin(): void
    {
        User::factory()->create(['role' => 'super_admin']);
        User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create(['role' => 'santri']);
        Santri::create([
            'user_id' => $student->id,
            'nis' => 'BACKFILL-001',
            'name' => $student->name,
            'wallet_balance' => 125000,
            'daily_limit' => 30000,
            'weekly_limit' => 200000,
            'monthly_limit' => 400000,
        ]);

        Artisan::call('smart:backfill-user-wallets', ['--preview' => true]);
        $this->assertDatabaseCount('user_wallets', 0);
        Artisan::call('smart:backfill-user-wallets', ['--apply' => true]);
        $this->assertDatabaseCount('user_wallets', 2);
        $this->assertDatabaseHas('user_wallets', [
            'user_id' => $student->id,
            'balance' => 125000,
            'daily_limit' => 30000,
            'weekly_limit' => 200000,
            'monthly_limit' => 400000,
        ]);
        Artisan::call('smart:backfill-user-wallets', ['--apply' => true]);
        $this->assertDatabaseCount('user_wallets', 2);
    }

    public function test_superadmin_is_never_changed_by_gate_reconciliation(): void
    {
        $super = User::factory()->create(['role' => 'super_admin', 'roles' => ['super_admin'], 'gate_user_uuid' => (string) Str::uuid(), 'name' => 'Local Owner']);
        $item = collect(app(GateUserReconciliationService::class)->reconcile([
            array_merge($this->gate($super, 'admin'), ['name' => 'Remote Different', 'status' => 'inactive']),
        ]))->firstWhere('local_user_id', $super->id);

        $this->assertSame('matched', $item['category']);
        $this->assertNull($item['differences']);
    }

    private function gate(User $user, string $type, ?string $nis = null): array
    {
        return ['gate_user_uuid' => $user->gate_user_uuid, 'name' => $user->name, 'email' => $user->email, 'email_verified' => true, 'type' => $type, 'nis' => $nis, 'role' => '', 'status' => 'active', 'application_access' => true];
    }

    private function newGate(string $type, string $email, ?string $nis = null): array
    {
        return ['gate_user_uuid' => (string) Str::uuid(), 'name' => 'New User', 'email' => $email, 'email_verified' => true, 'type' => $type, 'nis' => $nis, 'role' => '', 'status' => 'active', 'application_access' => true];
    }
}
