<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class GateMigrationPreflight extends Command
{
    protected $signature = 'gate:migration-preflight';

    protected $description = 'Read-only checks before applying the Gate synchronization schema migration';

    public function handle(): int
    {
        if (! Schema::hasTable('users')) {
            $this->error('[CRITICAL] users table does not exist.');

            return self::FAILURE;
        }

        $checks = [
            'email duplicates' => $this->duplicateCount('users', 'email'),
            'sso_sub duplicates' => Schema::hasColumn('users', 'sso_sub') ? $this->duplicateCount('users', 'sso_sub') : 0,
            'gate_user_uuid duplicates' => Schema::hasColumn('users', 'gate_user_uuid') ? $this->duplicateCount('users', 'gate_user_uuid') : 0,
        ];

        $failed = false;
        foreach ($checks as $label => $count) {
            $this->line(sprintf('[%s] %s: %d', $count === 0 ? 'OK' : 'CRITICAL', $label, $count));
            $failed = $failed || $count > 0;
        }

        $this->line('Existing users: '.DB::table('users')->count());
        $this->line('Gate UUID column: '.(Schema::hasColumn('users', 'gate_user_uuid') ? 'already present' : 'will be added nullable + unique'));
        $this->line('Schema-only check: no users, passwords, roles, tokens, or assignments were changed.');

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    private function duplicateCount(string $table, string $column): int
    {
        return DB::table($table)
            ->whereNotNull($column)
            ->where($column, '<>', '')
            ->select($column)
            ->groupBy($column)
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->count();
    }
}
