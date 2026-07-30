<?php

namespace App\Console\Commands;

use App\Services\Gate\GateIdentityBridgeService;
use App\Services\Gate\GateProvisioningClient;
use App\Services\Gate\GateProvisioningException;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

class GateBridgeIdentities extends Command
{
    protected $signature = 'gate:bridge-identities
        {--preview : Analyze identity candidates without changing SMART users}
        {--apply : Link conflict-free candidates by filling null gate_user_uuid values}';

    protected $description = 'Preview or explicitly apply the SMART-to-Gate identity bridge';

    public function handle(GateProvisioningClient $client, GateIdentityBridgeService $bridge): int
    {
        if ((bool) $this->option('preview') === (bool) $this->option('apply')) {
            $this->error('Choose exactly one of --preview or --apply.');

            return self::INVALID;
        }

        try {
            $gateUsers = $client->users();
            if ($gateUsers === []) {
                $this->error('GATE_EMPTY_ASSIGNMENT_RESPONSE: identity bridge requires a reviewed non-empty Gate population.');

                return self::FAILURE;
            }

            if ($this->option('preview')) {
                $preview = $bridge->preview($gateUsers);
                $this->table(['Metric', 'Count'], collect($preview['counts'])->map(fn ($count, $metric) => [$metric, $count])->values()->all());
                $this->warn('Preview only: no UUID, ownership, password, token, role, status, wallet, transaction, journal, inventory, or assignment was changed.');

                return self::SUCCESS;
            }

            $result = $bridge->apply($gateUsers);
            $this->info($result['status'].': '.$result['updated'].' gate_user_uuid update(s).');

            return self::SUCCESS;
        } catch (GateProvisioningException|ValidationException $exception) {
            $message = $exception instanceof GateProvisioningException
                ? $exception->errorCode.': '.$exception->getMessage()
                : collect($exception->errors())->flatten()->implode(' ');
            $this->error($message);

            return self::FAILURE;
        }
    }
}
