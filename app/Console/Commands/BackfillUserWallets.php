<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use App\Models\UserWallet;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillUserWallets extends Command
{
    protected $signature = 'smart:backfill-user-wallets {--preview : Show expected changes only} {--apply : Create missing wallets}';

    protected $description = 'Idempotently create buyer wallets for every non-superadmin user';

    public function handle(): int
    {
        if ((bool) $this->option('preview') === (bool) $this->option('apply')) {
            $this->error('Choose exactly one of --preview or --apply.');

            return self::INVALID;
        }

        $eligible = User::query()->where('role', '!=', UserRole::SUPER_ADMIN->value);
        $missing = (clone $eligible)->whereDoesntHave('wallet');
        $expected = $missing->count();

        $this->table(['Metric', 'Count'], [
            ['eligible_non_superadmin', $eligible->count()],
            ['existing_wallets', UserWallet::count()],
            ['missing_wallets', $expected],
        ]);

        if ($this->option('preview')) {
            $this->warn('Preview only. No wallet or balance was changed.');

            return self::SUCCESS;
        }

        $created = DB::transaction(function () use ($missing): int {
            $created = 0;
            $missing->with('santri')->select('id')->orderBy('id')->chunkById(200, function ($users) use (&$created) {
                foreach ($users as $user) {
                    $santri = $user->santri;
                    UserWallet::firstOrCreate(['user_id' => $user->id], [
                        'balance' => $santri?->wallet_balance ?? 0,
                        'daily_limit' => $santri?->daily_limit ?? 0,
                        'weekly_limit' => $santri?->weekly_limit ?? 200000,
                        'monthly_limit' => $santri?->monthly_limit ?? 0,
                        'is_locked' => $santri?->is_wallet_locked ?? false,
                    ]);
                    $created++;
                }
            });

            return $created;
        });

        if ($created !== $expected) {
            $this->error("Expected {$expected} wallets, created {$created}.");

            return self::FAILURE;
        }

        $this->info("Created {$created} wallets. Existing balances were not changed.");

        return self::SUCCESS;
    }
}
