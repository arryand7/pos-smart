<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\GateSyncBatch;
use App\Models\Santri;
use App\Models\User;
use App\Models\Wali;
use App\Services\Gate\GateProvisioningClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class GateSyncPreflight extends Command
{
    protected $signature = 'smart:gate-sync-preflight {--check-connection}';

    protected $description = 'Read-only readiness checks for Gate user synchronization';

    public function handle(GateProvisioningClient $client): int
    {
        $checks = [];
        $this->check($checks, 'Gate URL configured', filled(config('services.gate.url')), true);
        $this->check($checks, 'Gate URL uses HTTPS', str_starts_with((string) config('services.gate.url'), 'https://'), true);
        $this->check($checks, 'Client ID configured', filled(config('services.gate.provisioning_client_id')), true);
        $this->check($checks, 'Client secret configured', filled(config('services.gate.provisioning_client_secret')), true);
        $this->check($checks, 'Duplicate Gate UUIDs', ! $this->duplicates('users', 'gate_user_uuid'), true);
        $this->check($checks, 'Duplicate emails', ! $this->duplicates('users', 'email'), true);
        $this->check($checks, 'Duplicate SSO subjects', ! $this->duplicates('users', 'sso_sub'), true);
        $this->check($checks, 'Duplicate QR codes', ! $this->duplicates('santris', 'qr_code'), false);
        $this->check($checks, 'Valid local statuses', User::whereNotIn('status', ['active', 'suspended'])->doesntExist(), true);
        $roles = array_keys(config('services.gate.role_mapping', []));
        $this->check($checks, 'Known role mappings', User::whereNotIn('role', array_column(UserRole::cases(), 'value'))->doesntExist() && count($roles) > 0, true);
        $this->check($checks, 'Santri records have users', Santri::whereNull('user_id')->doesntExist(), false);
        $this->check($checks, 'Wali records have users', Wali::whereNull('user_id')->doesntExist(), false);
        $this->line('Users without Gate UUID: '.User::whereNull('gate_user_uuid')->count());
        $this->line('Pending Gate reports: '.GateSyncBatch::where('report_status', 'pending')->count());
        $this->line('Expired unapplied batches: '.GateSyncBatch::whereNull('applied_at')->where('expires_at', '<', now())->count());
        $writable = is_dir(Storage::disk('public')->path('')) && is_writable(Storage::disk('public')->path(''));
        $this->check($checks, 'Photo storage writable', $writable, false);
        if ($this->option('check-connection')) {
            try {
                $client->checkConnection();
                $this->info('[OK] Gate connection');
            } catch (\Throwable $e) {
                $this->error('[CRITICAL] Gate connection: '.$e->getMessage());
                $checks[] = false;
            }
        }

        return in_array(false, $checks, true) ? self::FAILURE : self::SUCCESS;
    }

    private function duplicates(string $table, string $column): bool
    {
        return DB::table($table)->whereNotNull($column)->select($column)->groupBy($column)->havingRaw('COUNT(*) > 1')->exists();
    }

    private function check(array &$checks, string $label, bool $passed, bool $critical): void
    {
        $this->{$passed ? 'info' : ($critical ? 'error' : 'warn')}(sprintf('[%s] %s', $passed ? 'OK' : ($critical ? 'CRITICAL' : 'WARN'), $label));
        if ($critical) {
            $checks[] = $passed;
        }
    }
}
