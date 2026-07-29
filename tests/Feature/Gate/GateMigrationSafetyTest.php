<?php

namespace Tests\Feature\Gate;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class GateMigrationSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_schema_migration_preserves_450_existing_users_credentials_roles_and_tokens(): void
    {
        $migration = require database_path('migrations/2026_07_29_000001_add_gate_sync_fields_and_tables.php');
        $migration->down();

        $password = Hash::make('existing-password');
        User::factory()->count(450)->sequence(fn ($sequence) => [
            'email' => 'existing-'.$sequence->index.'@example.test',
            'password' => $password,
            'role' => $sequence->index % 2 === 0 ? 'admin' : 'kasir',
        ])->create();
        $token = User::first()->createToken('existing-token')->accessToken;
        $before = User::query()->orderBy('id')->get(['id', 'password', 'role'])->map(fn ($user) => [$user->id, $user->password, $user->role?->value ?? $user->role])->all();

        $migration->up();

        $after = User::query()->orderBy('id')->get(['id', 'password', 'role'])->map(fn ($user) => [$user->id, $user->password, $user->role?->value ?? $user->role])->all();
        $this->assertSame($before, $after);
        $this->assertSame(450, User::count());
        $this->assertSame(450, User::whereNull('gate_user_uuid')->count());
        $this->assertSame(450, User::where('status', 'active')->count());
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $token->id]);
        $this->assertTrue(Schema::hasTable('gate_sync_batches'));
        $this->assertTrue(Schema::hasTable('gate_sync_items'));
        $this->assertSame(0, DB::table('gate_sync_batches')->count());
        $this->assertSame(0, DB::table('gate_sync_items')->count());
    }

    public function test_migration_preflight_is_read_only_and_supports_pre_migration_schema(): void
    {
        $migration = require database_path('migrations/2026_07_29_000001_add_gate_sync_fields_and_tables.php');
        $migration->down();
        $user = User::factory()->create(['role' => 'admin']);
        $snapshot = DB::table('users')->where('id', $user->id)->first();

        $this->artisan('gate:migration-preflight')->assertSuccessful();

        $this->assertEquals($snapshot, DB::table('users')->where('id', $user->id)->first());
        $migration->up();
    }
}
