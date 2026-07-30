<?php

namespace Tests\Feature\Gate;

use App\Models\Santri;
use App\Models\User;
use App\Services\Gate\GateIdentityBridgeService;
use App\Services\Gate\GateUserReconciliationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class GateIdentityBridgeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.gate.identity_bridge_enabled' => true]);
    }

    public function test_exact_uuid_is_primary_and_existing_uuid_is_never_overwritten(): void
    {
        $uuid = (string) Str::uuid();
        $user = User::factory()->create(['gate_user_uuid' => $uuid, 'email' => 'uuid@smart.test']);
        $otherUuid = (string) Str::uuid();
        $other = User::factory()->create(['email' => 'other@smart.test', 'gate_user_uuid' => $otherUuid]);

        $differentUuid = (string) Str::uuid();
        $preview = $this->bridge()->preview([
            $this->gate($uuid, email: $user->email),
            $this->gate($differentUuid, email: $other->email),
        ]);

        $this->assertSame('MATCHED-BY-UUID', $preview['items'][0]['classification']);
        $this->assertSame('CONFLICT', $preview['items'][1]['classification']);
        $this->bridge()->apply([
            $this->gate($uuid, email: $user->email),
            $this->gate($differentUuid, email: $other->email),
        ]);
        $this->assertSame($uuid, $user->fresh()->gate_user_uuid);
        $this->assertSame($otherUuid, $other->fresh()->gate_user_uuid);
    }

    public function test_santri_matches_student_by_unique_nis_and_becomes_gate_managed(): void
    {
        $user = $this->santri('S-001', 'student@smart.test');
        $uuid = (string) Str::uuid();
        $gate = [$this->gate($uuid, 'student', 'S-001', 'different@gate.test')];

        $preview = $this->bridge()->preview($gate);
        $this->assertSame('PROPOSED-LINK-BY-NIS', $preview['items'][0]['classification']);
        $this->assertSame(1, $preview['counts']['PROPOSED_BY_NIS']);
        $this->assertSame(['updated' => 1, 'status' => 'APPLIED'], array_intersect_key($this->bridge()->apply($gate), array_flip(['updated', 'status'])));
        $this->assertSame($uuid, $user->fresh()->gate_user_uuid);
        $this->assertSame('gate_managed', $user->fresh()->identityOwnership());
    }

    public function test_verified_email_links_but_unverified_email_requires_review(): void
    {
        $verified = User::factory()->create(['email' => 'verified@smart.test']);
        $unverified = User::factory()->create(['email' => 'unverified@smart.test']);
        $gate = [
            $this->gate((string) Str::uuid(), 'staff', null, $verified->email, true),
            $this->gate((string) Str::uuid(), 'staff', null, $unverified->email, false),
        ];

        $preview = collect($this->bridge()->preview($gate)['items'])->keyBy('email');
        $this->assertSame('PROPOSED-LINK-BY-VERIFIED-EMAIL', $preview[$verified->email]['classification']);
        $this->assertSame('REVIEW-EMAIL-UNVERIFIED', $preview[$unverified->email]['classification']);
        $this->bridge()->apply($gate);
        $this->assertNotNull($verified->fresh()->gate_user_uuid);
        $this->assertNull($unverified->fresh()->gate_user_uuid);
    }

    public function test_nis_email_conflict_and_duplicate_gate_identifiers_are_not_linkable(): void
    {
        $nisUser = $this->santri('S-002', 'nis@smart.test');
        $emailUser = User::factory()->create(['email' => 'email@smart.test']);
        $duplicateUuidA = (string) Str::uuid();
        $duplicateUuidB = (string) Str::uuid();
        $gate = [
            $this->gate((string) Str::uuid(), 'student', 'S-002', $emailUser->email, true),
            $this->gate($duplicateUuidA, 'student', 'DUP-NIS', 'one@gate.test'),
            $this->gate($duplicateUuidB, 'student', 'DUP-NIS', 'two@gate.test'),
            $this->gate((string) Str::uuid(), 'staff', null, 'dup@gate.test', true),
            $this->gate((string) Str::uuid(), 'staff', null, 'dup@gate.test', true),
        ];

        $preview = $this->bridge()->preview($gate);
        $this->assertGreaterThanOrEqual(1, $preview['counts']['CONFLICT']);
        $this->assertGreaterThanOrEqual(4, $preview['counts']['MULTIPLE_MATCH']);
        $this->bridge()->apply($gate);
        $this->assertNull($nisUser->fresh()->gate_user_uuid);
        $this->assertNull($emailUser->fresh()->gate_user_uuid);
    }

    public function test_local_manual_and_wali_remain_untouched_while_unclassified_student_is_reported_missing(): void
    {
        $local = User::factory()->create(['identity_source' => 'local_manual', 'status' => 'active']);
        $wali = User::factory()->create(['identity_source' => 'local_manual', 'role' => 'wali', 'status' => 'active']);
        $student = $this->santri('S-MISSING', 'missing@smart.test');
        $student->update(['identity_source' => null]);

        $preview = $this->bridge()->preview([$this->gate((string) Str::uuid(), 'staff', null, 'nobody@smart.test')]);

        $this->assertSame(2, $preview['counts']['LOCAL_MANUAL']);
        $this->assertSame(1, $preview['counts']['MISSING_STUDENT_IN_GATE']);
        $this->assertSame('active', $local->fresh()->status);
        $this->assertSame('active', $wali->fresh()->status);
    }

    public function test_apply_is_idempotent_and_preserves_credentials_tokens_and_business_data(): void
    {
        $user = $this->santri('S-003', 'safe@smart.test');
        $user->santri->update(['wallet_balance' => 125000, 'daily_limit' => 30000]);
        $password = $user->password;
        $ssoSub = '221';
        $user->update(['sso_sub' => $ssoSub]);
        $token = $user->createToken('bridge')->accessToken;
        $gate = [$this->gate((string) Str::uuid(), 'student', 'S-003', $user->email)];
        $domainCounts = collect(['wallet_transactions', 'transactions', 'payments', 'journal_entries', 'journal_lines', 'inventory_movements'])
            ->mapWithKeys(fn ($table) => [$table => DB::table($table)->count()]);

        $this->assertSame('APPLIED', $this->bridge()->apply($gate)['status']);
        $this->assertSame('NO-CHANGE', $this->bridge()->apply($gate)['status']);
        $user->refresh();
        $this->assertSame($password, $user->password);
        $this->assertSame($ssoSub, $user->sso_sub);
        $this->assertSame('125000.00', $user->santri->fresh()->wallet_balance);
        $this->assertSame('30000.00', $user->santri->fresh()->daily_limit);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $token->id]);
        foreach ($domainCounts as $table => $count) {
            $this->assertSame($count, DB::table($table)->count(), $table.' changed during identity bridge');
        }
    }

    public function test_reconciliation_after_bridge_uses_uuid_and_empty_payload_remains_blocked(): void
    {
        $user = $this->santri('S-004', 'linked@smart.test');
        $gate = $this->gate((string) Str::uuid(), 'student', 'S-004', $user->email);
        $gate['name'] = $user->name;
        $this->bridge()->apply([$gate]);

        $items = collect(app(GateUserReconciliationService::class)->reconcile([$gate]));
        $this->assertSame('matched', $items->firstWhere('local_user_id', $user->id)['category']);
        $this->assertSame('gate_managed', $user->fresh()->identityOwnership());
    }

    public function test_bridge_command_preview_is_non_mutating_and_empty_payload_is_a_hard_stop(): void
    {
        config([
            'services.gate.url' => 'https://gate.test',
            'services.gate.provisioning_client_id' => 'smart',
            'services.gate.provisioning_client_secret' => 'test-only',
        ]);
        $user = $this->santri('S-005', 'command@smart.test');
        Http::fake(['*/api/provisioning/users' => Http::sequence()
            ->push(['users' => [$this->gate((string) Str::uuid(), 'student', 'S-005', $user->email)]])
            ->push(['users' => []])]);

        $this->artisan('gate:bridge-identities --preview')
            ->expectsOutputToContain('PROPOSED_BY_NIS')
            ->assertSuccessful();
        $this->assertNull($user->fresh()->gate_user_uuid);
        $this->artisan('gate:bridge-identities --preview')
            ->expectsOutputToContain('GATE_EMPTY_ASSIGNMENT_RESPONSE')
            ->assertFailed();
        $this->assertNull($user->fresh()->gate_user_uuid);
    }

    private function bridge(): GateIdentityBridgeService
    {
        return app(GateIdentityBridgeService::class);
    }

    private function santri(string $nis, string $email): User
    {
        $user = User::factory()->create(['role' => 'santri', 'roles' => ['santri'], 'email' => $email]);
        Santri::create(['user_id' => $user->id, 'nis' => $nis, 'name' => $user->name]);

        return $user->fresh('santri');
    }

    private function gate(string $uuid, string $type = 'staff', ?string $nis = null, ?string $email = null, bool $verified = false): array
    {
        return [
            'uuid' => $uuid,
            'name' => 'Gate Name',
            'email' => $email ?? Str::uuid().'@gate.test',
            'email_verified' => $verified,
            'type' => $type,
            'nis' => $nis,
            'status' => 'active',
            'application_access' => ['status' => 'active', 'role' => $type === 'student' ? 'santri' : 'admin'],
        ];
    }
}
