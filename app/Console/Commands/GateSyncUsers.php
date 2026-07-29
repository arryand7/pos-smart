<?php

namespace App\Console\Commands;

use App\Models\GateSyncBatch;
use App\Models\User;
use App\Services\Gate\GateProvisioningException;
use App\Services\Gate\GateUserSyncService;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

class GateSyncUsers extends Command
{
    protected $signature = 'gate:sync-users
        {--preview : Fetch Gate users and persist an auditable dry-run batch}
        {--apply : Apply a previously reviewed preview batch}
        {--batch= : UUID of the preview batch to apply}
        {--actor= : Local superadmin user ID responsible for this operation}';

    protected $description = 'Explicit preview/apply workflow for Gate user synchronization';

    public function handle(GateUserSyncService $service): int
    {
        if ((bool) $this->option('preview') === (bool) $this->option('apply')) {
            $this->error('Choose exactly one of --preview or --apply.');

            return self::INVALID;
        }

        $actor = User::find($this->option('actor'));
        if (! $actor?->hasRole('super_admin')) {
            $this->error('--actor must identify a local superadmin.');

            return self::INVALID;
        }

        try {
            if ($this->option('preview')) {
                $batch = $service->preview($actor);
                $this->renderPreview($batch);

                return self::SUCCESS;
            }

            $batch = GateSyncBatch::where('uuid', $this->option('batch'))->first();
            if (! $batch) {
                $this->error('--batch must identify an existing preview batch.');

                return self::INVALID;
            }

            $service->apply($batch, [], $actor);
            $this->info('Applied batch '.$batch->uuid.'. Report status: '.$batch->fresh()->report_status);

            return self::SUCCESS;
        } catch (GateProvisioningException|ValidationException $exception) {
            $message = $exception instanceof GateProvisioningException
                ? $exception->errorCode.': '.$exception->getMessage()
                : collect($exception->errors())->flatten()->implode(' ');
            $this->error($message);

            return self::FAILURE;
        }
    }

    private function renderPreview(GateSyncBatch $batch): void
    {
        $items = $batch->items;
        $stats = [
            'received' => $items->whereNotNull('gate_user_uuid')->count(),
            'matched' => $items->where('category', 'matched')->count(),
            'create' => $items->where('category', 'missing_in_application')->count(),
            'update' => $items->where('category', 'needs_update')->count(),
            'role-change' => $items->filter(fn ($item) => isset($item->differences['role']))->count(),
            'suspend' => $items->whereIn('category', ['access_revoked', 'inactive_in_gate'])->count(),
            'reactivate' => $items->where('category', 'reactivation_required')->count(),
            'unmatched' => $items->where('category', 'local_only')->count(),
            'duplicate' => $items->where('category', 'conflict')->count(),
            'errors' => $items->whereNotNull('error_code')->count(),
        ];

        $this->info('Preview batch: '.$batch->uuid.' (expires '.$batch->expires_at->toIso8601String().')');
        $this->table(['Metric', 'Count'], collect($stats)->map(fn ($value, $key) => [$key, $value])->values()->all());
        $this->warn('No user, password, token, photo, wallet, transaction, inventory, or accounting data was changed.');
    }
}
