<?php

namespace App\Services\Gate;

use App\Models\ActivityLog;
use App\Models\GateSyncBatch;
use App\Models\GateSyncItem;
use App\Models\Santri;
use App\Models\User;
use App\Models\Wali;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GateUserSyncService
{
    public function __construct(private GateProvisioningClient $client, private GateUserReconciliationService $reconciler, private GatePhotoSyncService $photos) {}

    public function preview(User $actor): GateSyncBatch
    {
        $rawUsers = $this->client->users();
        if ($rawUsers === [] && User::where('status', 'active')->exists()) {
            throw new GateProvisioningException(
                'GATE_EMPTY_ASSIGNMENT_RESPONSE',
                'Gate mengembalikan 0 user sementara SMART memiliki user aktif. Periksa assignment aplikasi SMART di Gate.',
                409,
            );
        }
        $items = $this->reconciler->reconcile($rawUsers);

        return DB::transaction(function () use ($actor, $rawUsers, $items) {
            $batch = GateSyncBatch::create([
                'uuid' => (string) Str::uuid(), 'initiated_by' => $actor->id, 'status' => 'ready',
                'gate_response_checksum' => hash('sha256', json_encode($rawUsers, JSON_UNESCAPED_SLASHES)),
                'total_items' => count($items), 'expires_at' => now()->addMinutes((int) config('services.gate.preview_ttl_minutes', 30)),
            ]);
            $batch->items()->createMany($items);
            ActivityLog::log('GATE_SYNC_PREVIEW_CREATED', 'Preview sinkronisasi Gate dibuat.', $batch, ['batch_uuid' => $batch->uuid, 'actor_id' => $actor->id, 'result' => 'ready']);

            return $batch->load('items');
        });
    }

    public function apply(GateSyncBatch $batch, array $selections, User $actor): GateSyncBatch
    {
        if (! config('services.gate.sync_enabled') || config('services.gate.dry_run', true)) {
            throw ValidationException::withMessages(['batch' => 'GATE_SYNC_APPLY_DISABLED']);
        }

        $result = DB::transaction(function () use ($batch, $selections, $actor) {
            $locked = GateSyncBatch::query()->whereKey($batch->id)->lockForUpdate()->firstOrFail();
            if ($locked->expires_at->isPast()) {
                throw ValidationException::withMessages(['batch' => 'SYNC_PREVIEW_EXPIRED']);
            }
            if ($locked->status !== 'ready' || $locked->applied_at) {
                throw ValidationException::withMessages(['batch' => 'SYNC_ALREADY_APPLIED']);
            }
            $locked->update(['status' => 'applying']);
            $items = $locked->items()->lockForUpdate()->get();
            $selectionMap = collect($selections)->mapWithKeys(fn ($row) => [(int) $row['id'] => $row['action']]);
            if ($selectionMap->keys()->diff($items->pluck('id'))->isNotEmpty()) {
                throw ValidationException::withMessages(['items' => 'Item bukan bagian dari batch ini.']);
            }

            $this->assertApplyThresholds($items, $selectionMap);

            foreach ($items as $item) {
                $action = $selectionMap->get($item->id, $item->recommended_action);
                if (! in_array($action, GateUserReconciliationService::ACTIONS[$item->category] ?? [], true)) {
                    throw ValidationException::withMessages(["items.{$item->id}" => 'SYNC_INVALID_ACTION']);
                }
                $this->applyItem($item, $action, $actor);
            }
            $locked->update(['status' => 'applied', 'applied_at' => now(), 'report_status' => 'pending']);
            ActivityLog::log('GATE_SYNC_APPLIED', 'Sinkronisasi Gate diterapkan.', $locked, ['batch_uuid' => $locked->uuid, 'actor_id' => $actor->id, 'result' => 'applied']);

            return $locked->fresh('items');
        });

        return $this->report($result);
    }

    public function retryReport(GateSyncBatch $batch): GateSyncBatch
    {
        if (! $batch->applied_at) {
            throw ValidationException::withMessages(['batch' => 'Batch belum diterapkan.']);
        }
        if ($batch->report_status === 'sent') {
            return $batch->fresh('items');
        }

        return $this->report($batch->fresh('items'));
    }

    private function applyItem(GateSyncItem $item, string $action, User $actor): void
    {
        $gate = $item->gate_payload ?? [];
        $user = $item->local_user_id ? User::query()->find($item->local_user_id) : null;
        $result = 'skipped';
        $event = null;
        if ($action === 'create_local_user') {
            $role = $this->mappedRole($gate['role'] ?? '');
            if (! $role) {
                $this->fail($item, 'SYNC_ROLE_MAPPING_FAILED');

                return;
            }
            if ($role === 'santri' && empty($gate['nis'])) {
                $this->fail($item, 'SANTRI_PROFILE_DATA_INCOMPLETE');

                return;
            }
            if ($role === 'santri' && config('services.gate.sync_qr') && filled($gate['qr_code'] ?? null) && Santri::where('qr_code', $gate['qr_code'])->exists()) {
                $this->fail($item, 'QR_CODE_CONFLICT');

                return;
            }
            $user = User::create(['name' => $gate['name'], 'email' => $gate['email'], 'role' => $role, 'roles' => [$role], 'gate_user_uuid' => $item->gate_user_uuid, 'status' => 'active', 'last_gate_synced_at' => now(), 'password' => Hash::make(Str::random(64))]);
            if ($role === 'santri') {
                Santri::create(['user_id' => $user->id, 'nis' => $gate['nis'], 'name' => $gate['name'], 'qr_code' => config('services.gate.sync_qr') ? ($gate['qr_code'] ?? null) : null, 'wallet_balance' => 0, 'daily_limit' => 0, 'monthly_limit' => 0]);
            }
            if ($role === 'wali') {
                Wali::create(['user_id' => $user->id, 'name' => $gate['name'], 'email' => $gate['email']]);
            }
            $result = 'created';
            $event = 'GATE_USER_CREATED';
        } elseif ($action === 'update_identity' && $user) {
            $role = $this->mappedRole($gate['role'] ?? '');
            if (! $role) {
                $this->fail($item, 'SYNC_ROLE_MAPPING_FAILED');

                return;
            }
            $user->update(['name' => $gate['name'], 'email' => $gate['email'], 'role' => $role, 'roles' => [$role], 'last_gate_synced_at' => now()]);
            $result = 'updated';
            $event = 'GATE_USER_UPDATED';
        } elseif ($action === 'suspend_local_user' && $user) {
            $user->update(['status' => 'suspended', 'last_gate_synced_at' => now()]);
            $user->tokens()->delete();
            $result = 'suspended';
            $event = 'GATE_USER_SUSPENDED';
        } elseif ($action === 'reactivate_local_user' && $user) {
            $user->update(['status' => 'active', 'last_gate_synced_at' => now()]);
            $result = 'reactivated';
            $event = 'GATE_USER_REACTIVATED';
        } elseif ($action === 'no_change') {
            if ($user) {
                $user->forceFill(['last_gate_synced_at' => now()])->save();
            }
            $result = 'matched';
        } elseif ($action === 'manual_review') {
            $result = 'conflict';
            $event = 'GATE_SYNC_CONFLICT';
        }
        $item->update(['selected_action' => $action, 'result_status' => $result, 'local_user_id' => $user?->id, 'external_user_id' => $user ? (string) $user->id : null]);
        if ($user && $user->hasRole('santri')) {
            try {
                if ($this->photos->sync($user, $gate['photo'] ?? null)) {
                    ActivityLog::log('GATE_PHOTO_SYNCED', 'Foto Gate disinkronkan.', $user, ['batch_uuid' => $item->batch->uuid, 'gate_user_uuid' => $item->gate_user_uuid, 'result' => 'synced']);
                }
            } catch (\Throwable $e) {
                ActivityLog::log('GATE_PHOTO_FAILED', 'Sinkronisasi foto Gate gagal.', $user, ['batch_uuid' => $item->batch->uuid, 'gate_user_uuid' => $item->gate_user_uuid, 'result' => 'warning']);
            }
        }
        if ($event) {
            ActivityLog::log($event, 'Hasil item sinkronisasi Gate.', $user ?? $item, ['batch_uuid' => $item->batch->uuid, 'actor_id' => $actor->id, 'gate_user_uuid' => $item->gate_user_uuid, 'local_user_id' => $user?->id, 'action' => $action, 'result' => $result]);
        }
    }

    private function report(GateSyncBatch $batch): GateSyncBatch
    {
        $payload = $batch->items->map(fn ($item) => ['gate_user_uuid' => $item->gate_user_uuid, 'status' => $item->result_status ?? $item->category, 'external_user_id' => $item->external_user_id, 'error_code' => $item->error_code, 'error_message' => $item->error_message])->values()->all();
        try {
            $this->client->report($payload);
            $batch->update(['status' => 'completed', 'report_status' => 'sent', 'report_attempts' => $batch->report_attempts + 1, 'last_report_error' => null, 'reported_at' => now()]);
            ActivityLog::log('GATE_SYNC_REPORT_SENT', 'Hasil sinkronisasi dilaporkan ke Gate.', $batch, ['batch_uuid' => $batch->uuid, 'result' => 'sent']);
        } catch (GateProvisioningException $e) {
            $batch->update(['status' => 'report_pending', 'report_status' => 'pending', 'report_attempts' => $batch->report_attempts + 1, 'last_report_error' => $e->errorCode]);
            ActivityLog::log('GATE_SYNC_REPORT_FAILED', 'Pelaporan hasil Gate tertunda.', $batch, ['batch_uuid' => $batch->uuid, 'result' => 'pending']);
        }

        return $batch->fresh('items');
    }

    private function mappedRole(string $role): ?string
    {
        return config('services.gate.role_mapping')[$role] ?? null;
    }

    private function fail(GateSyncItem $item, string $code): void
    {
        $item->update(['selected_action' => $item->recommended_action, 'result_status' => 'failed', 'error_code' => $code, 'error_message' => $code]);
    }

    private function assertApplyThresholds($items, $selectionMap): void
    {
        $criticalConflicts = ['duplicate_gate_uuid', 'multiple_local_candidates', 'multiple_gate_candidates', 'uuid_mismatch'];
        if ($items->where('category', 'conflict')->whereIn('error_code', $criticalConflicts)->isNotEmpty()) {
            throw ValidationException::withMessages(['batch' => 'SYNC_DUPLICATE_IDENTITY_BLOCKED']);
        }

        $selected = $items->map(fn (GateSyncItem $item) => [
            'item' => $item,
            'action' => $selectionMap->get($item->id, $item->recommended_action),
        ]);
        $localCount = max(User::count(), 1);
        $activeCount = max(User::where('status', 'active')->count(), 1);
        $suspendCount = $selected->where('action', 'suspend_local_user')->count();
        $createCount = $selected->where('action', 'create_local_user')->count();
        $roleChangeCount = $selected->filter(fn ($row) => $row['action'] === 'update_identity' && isset($row['item']->differences['role']))->count();

        $guards = [
            'SYNC_SUSPEND_THRESHOLD_EXCEEDED' => [$suspendCount, $activeCount, (float) config('services.gate.max_suspend_percent', 10)],
            'SYNC_ROLE_THRESHOLD_EXCEEDED' => [$roleChangeCount, $localCount, (float) config('services.gate.max_role_change_percent', 10)],
            'SYNC_CREATE_THRESHOLD_EXCEEDED' => [$createCount, $localCount, (float) config('services.gate.max_create_percent', 25)],
        ];

        foreach ($guards as $error => [$count, $denominator, $limit]) {
            if ($count > 0 && ($count / $denominator) * 100 > $limit) {
                throw ValidationException::withMessages(['batch' => $error]);
            }
        }
    }
}
