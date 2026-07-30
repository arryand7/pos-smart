<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class GateSetUserOwnership extends Command
{
    protected $signature = 'gate:set-user-ownership {user : Local SMART user ID} {ownership : local_manual or gate_managed}';

    protected $description = 'Explicitly classify ownership of one SMART user';

    public function handle(): int
    {
        $user = User::find($this->argument('user'));
        $ownership = (string) $this->argument('ownership');
        if (! $user || ! in_array($ownership, ['local_manual', 'gate_managed'], true)) {
            $this->error('A valid user and ownership (local_manual|gate_managed) are required.');

            return self::INVALID;
        }
        if ($ownership === 'gate_managed' && ! $user->gate_user_uuid) {
            $this->error('gate_managed requires an existing gate_user_uuid; use the identity bridge first.');

            return self::FAILURE;
        }
        if ($ownership === 'local_manual' && $user->gate_user_uuid) {
            $this->error('A user with gate_user_uuid is always gate_managed; unlinking requires a separately reviewed correction.');

            return self::FAILURE;
        }

        $user->update(['identity_source' => $ownership]);
        $this->info("User {$user->id} ownership set to {$ownership}.");

        return self::SUCCESS;
    }
}
