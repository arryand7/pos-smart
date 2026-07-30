<?php

namespace Tests\Feature\Gate;

use App\Models\User;
use App\Services\Gate\GateUserReconciliationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class GateUserReconciliationTest extends TestCase
{
    use RefreshDatabase;

    public function test_reconciliation_categories_are_deterministic_and_identifiers_never_auto_merge(): void
    {
        $uuid = fn () => (string) Str::uuid();
        $matched = User::factory()->create(['gate_user_uuid' => $uuid(), 'name' => 'Same', 'email' => 'same@example.test', 'role' => 'admin', 'status' => 'active']);
        $update = User::factory()->create(['gate_user_uuid' => $uuid(), 'name' => 'Old', 'email' => 'update@example.test', 'role' => 'admin']);
        $revoked = User::factory()->create(['gate_user_uuid' => $uuid(), 'email' => 'revoked@example.test', 'role' => 'kasir']);
        $inactive = User::factory()->create(['gate_user_uuid' => $uuid(), 'email' => 'inactive@example.test', 'role' => 'wali']);
        $reactivate = User::factory()->create(['gate_user_uuid' => $uuid(), 'email' => 'back@example.test', 'role' => 'admin', 'status' => 'suspended']);
        $localOnly = User::factory()->create(['email' => 'local@example.test', 'role' => 'admin']);
        $conflict = User::factory()->create(['email' => 'conflict@example.test', 'role' => 'admin']);
        $gate = [
            $this->gate($matched->gate_user_uuid, 'Same', $matched->email, 'admin'),
            $this->gate($update->gate_user_uuid, 'New', $update->email, 'admin'),
            $this->gate($uuid(), 'Missing', 'missing@example.test', 'admin'),
            $this->gate($revoked->gate_user_uuid, $revoked->name, $revoked->email, 'kasir', true, false),
            $this->gate($inactive->gate_user_uuid, $inactive->name, $inactive->email, 'wali', false, true),
            $this->gate($reactivate->gate_user_uuid, $reactivate->name, $reactivate->email, 'admin'),
            $this->gate($uuid(), 'Conflict', $conflict->email, 'admin'),
        ];
        $items = collect(app(GateUserReconciliationService::class)->reconcile($gate));
        $this->assertEqualsCanonicalizing(['matched', 'needs_update', 'missing_in_application', 'access_revoked', 'inactive_in_gate', 'reactivation_required', 'local_manual', 'conflict'], $items->pluck('category')->unique()->all());
        $conflictItem = $items->firstWhere('category', 'conflict');
        $this->assertSame('manual_review', $conflictItem['recommended_action']);
        $this->assertNull($conflict->fresh()->gate_user_uuid);
        $this->assertSame($localOnly->id, $items->firstWhere('category', 'local_manual')['local_user_id']);
    }

    private function gate(string $uuid, string $name, string $email, string $role, bool $identity = true, bool $access = true): array
    {
        return ['gate_user_uuid' => $uuid, 'name' => $name, 'email' => $email, 'role' => $role, 'status' => $identity ? 'active' : 'inactive', 'application_access' => $access];
    }
}
