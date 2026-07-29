<?php

namespace App\Services\Gate;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class GateUserReconciliationService
{
    public const ACTIONS = [
        'matched' => ['no_change'], 'needs_update' => ['update_identity', 'skip'],
        'missing_in_application' => ['create_local_user', 'skip'],
        'access_revoked' => ['suspend_local_user'], 'inactive_in_gate' => ['suspend_local_user'],
        'reactivation_required' => ['reactivate_local_user', 'skip'],
        'local_only' => ['manual_review'], 'conflict' => ['manual_review'],
    ];

    public function reconcile(array $gateUsers, ?Collection $localUsers = null): array
    {
        $locals = ($localUsers ?? User::query()->get())->values();
        $byUuid = $locals->filter(fn ($u) => $u->gate_user_uuid)->groupBy(fn ($u) => strtolower($u->gate_user_uuid));
        $byEmail = $locals->filter(fn ($u) => $u->email)->groupBy(fn ($u) => $this->key($u->email));
        $seenLocal = [];
        $seenGate = [];
        $items = [];

        foreach ($gateUsers as $raw) {
            $gate = $this->normalizeGate($raw);
            $uuid = $gate['gate_user_uuid'];
            if (! $uuid || ! Str::isUuid($uuid)) {
                $items[] = $this->item($gate, null, 'conflict', 'manual_review', [], 'invalid_or_missing_gate_uuid');

                continue;
            }
            $uuidKey = strtolower($uuid);
            if (isset($seenGate[$uuidKey])) {
                $items[] = $this->item($gate, null, 'conflict', 'manual_review', [], 'duplicate_gate_uuid');

                continue;
            }
            $seenGate[$uuidKey] = true;
            $uuidCandidates = $byUuid->get($uuidKey, collect());
            if ($uuidCandidates->count() > 1) {
                $items[] = $this->item($gate, null, 'conflict', 'manual_review', [], 'multiple_local_candidates');

                continue;
            }
            $local = $uuidCandidates->first();
            if ($local) {
                $seenLocal[$local->id] = true;
                $category = $this->linkedCategory($gate, $local);
                $items[] = $this->item($gate, $local, $category, self::ACTIONS[$category][0], $this->differences($gate, $local));

                continue;
            }
            $emailCandidates = $gate['email'] ? $byEmail->get($this->key($gate['email']), collect()) : collect();
            if ($emailCandidates->isNotEmpty()) {
                foreach ($emailCandidates as $candidate) {
                    $seenLocal[$candidate->id] = true;
                }
                $code = $emailCandidates->count() > 1 ? 'multiple_local_candidates' : ($emailCandidates->first()->gate_user_uuid ? 'uuid_mismatch' : 'unlinked_matching_email');
                $items[] = $this->item($gate, $emailCandidates->count() === 1 ? $emailCandidates->first() : null, 'conflict', 'manual_review', [], $code);
            } else {
                $items[] = $this->item($gate, null, 'missing_in_application', 'create_local_user');
            }
        }
        foreach ($locals as $local) {
            if (! isset($seenLocal[$local->id])) {
                $items[] = $this->item([], $local, 'local_only', 'manual_review');
            }
        }

        return $items;
    }

    public function normalizeGate(array $raw): array
    {
        $access = data_get($raw, 'application_access.smart', data_get($raw, 'applications.smart', data_get($raw, 'access_active', data_get($raw, 'application_access', true))));

        return [
            'gate_user_uuid' => data_get($raw, 'gate_user_uuid', data_get($raw, 'uuid')),
            'name' => trim((string) data_get($raw, 'name', '')),
            'email' => strtolower(trim((string) data_get($raw, 'email', ''))),
            'username' => strtolower(trim((string) data_get($raw, 'username', ''))),
            'role' => strtolower((string) data_get($raw, 'application_role', data_get($raw, 'role', data_get($raw, 'type', '')))),
            'identity_active' => in_array(strtolower((string) data_get($raw, 'status', data_get($raw, 'identity_status', 'active'))), ['active', 'enabled', '1'], true),
            'access_active' => filter_var($access, FILTER_VALIDATE_BOOL),
            'photo' => data_get($raw, 'photo'), 'qr_code' => data_get($raw, 'qr_code'),
            'nis' => data_get($raw, 'nis'), 'nip' => data_get($raw, 'nip'),
        ];
    }

    private function linkedCategory(array $gate, User $local): string
    {
        if (! $gate['identity_active']) {
            return 'inactive_in_gate';
        }
        if (! $gate['access_active']) {
            return 'access_revoked';
        }
        if ($local->status === 'suspended') {
            return 'reactivation_required';
        }

        return $this->differences($gate, $local) ? 'needs_update' : 'matched';
    }

    private function differences(array $gate, User $local): array
    {
        $diff = [];
        foreach (['name', 'email', 'role'] as $field) {
            $lv = $field === 'role' ? ($local->role?->value ?? $local->role) : $local->{$field};
            if ($this->key((string) $lv) !== $this->key((string) $gate[$field])) {
                $diff[$field] = ['local' => $lv, 'gate' => $gate[$field]];
            }
        }

        return $diff;
    }

    private function item(array $gate, ?User $local, string $category, string $action, array $differences = [], ?string $error = null): array
    {
        return ['gate_user_uuid' => $gate['gate_user_uuid'] ?? null, 'local_user_id' => $local?->id, 'category' => $category, 'recommended_action' => $action, 'gate_payload' => $gate ?: null, 'local_payload' => $local ? ['id' => $local->id, 'name' => $local->name, 'email' => $local->email, 'role' => $local->role?->value ?? $local->role, 'status' => $local->status] : null, 'differences' => $differences ?: null, 'error_code' => $error];
    }

    private function key(string $value): string
    {
        return mb_strtolower(trim($value));
    }
}
