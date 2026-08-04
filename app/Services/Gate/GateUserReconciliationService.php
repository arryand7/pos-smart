<?php

namespace App\Services\Gate;

use App\Enums\UserRole;
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
        'local_manual' => ['manual_review'], 'missing_student_in_gate' => ['manual_review'],
        'local_only' => ['manual_review'], 'conflict' => ['manual_review'],
    ];

    public function reconcile(array $gateUsers, ?Collection $localUsers = null): array
    {
        $locals = ($localUsers ?? User::query()->get())->values();
        $byUuid = $locals->filter(fn ($u) => $u->gate_user_uuid)->groupBy(fn ($u) => strtolower($u->gate_user_uuid));
        $byEmail = $locals->filter(fn ($u) => $u->email)->groupBy(fn ($u) => $this->key($u->email));
        $byNis = $locals->filter(fn ($u) => filled($u->santri?->nis))->groupBy(fn ($u) => $this->key($u->santri->nis));
        $gateNisCounts = collect($gateUsers)->map(fn ($raw) => $this->normalizeGate($raw))->filter(fn ($u) => filled($u['nis']))->countBy(fn ($u) => $this->key($u['nis']));
        $gateEmailCounts = collect($gateUsers)->map(fn ($raw) => $this->normalizeGate($raw))->filter(fn ($u) => filled($u['email']))->countBy(fn ($u) => $this->key($u['email']));
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
            if (! in_array($gate['type'], ['student', 'teacher', 'staff', 'admin', 'parent'], true)) {
                $error = $gate['type'] === '' ? 'missing_user_type' : 'unsupported_user_type';
                $items[] = $this->item($gate, null, 'conflict', 'manual_review', [], $error);

                continue;
            }
            $uuidCandidates = $byUuid->get($uuidKey, collect());
            if ($uuidCandidates->count() > 1) {
                $items[] = $this->item($gate, null, 'conflict', 'manual_review', [], 'multiple_local_candidates');

                continue;
            }
            $local = $uuidCandidates->first();
            if ($local) {
                if (($local->santri && $gate['type'] !== 'student') || ($local->santri && filled($gate['nis']) && $this->key($local->santri->nis) !== $this->key($gate['nis']))) {
                    $items[] = $this->item($gate, $local, 'conflict', 'manual_review', [], 'linked_student_integrity_conflict');
                    $seenLocal[$local->id] = true;

                    continue;
                }
                $nisCandidate = filled($gate['nis']) ? $byNis->get($this->key($gate['nis']), collect()) : collect();
                $emailCandidate = filled($gate['email']) ? $byEmail->get($this->key($gate['email']), collect()) : collect();
                if (($nisCandidate->isNotEmpty() && ! $nisCandidate->contains(fn ($candidate) => $candidate->is($local))) || ($emailCandidate->isNotEmpty() && ! $emailCandidate->contains(fn ($candidate) => $candidate->is($local)))) {
                    $items[] = $this->item($gate, $local, 'conflict', 'manual_review', [], 'uuid_integrity_conflict');
                    $seenLocal[$local->id] = true;

                    continue;
                }
                $seenLocal[$local->id] = true;
                $category = $this->linkedCategory($gate, $local);
                $items[] = $this->item($gate, $local, $category, self::ACTIONS[$category][0], $this->differences($gate, $local));

                continue;
            }
            $nisCandidates = filled($gate['nis']) ? $byNis->get($this->key($gate['nis']), collect()) : collect();
            if (filled($gate['nis']) && (($gateNisCounts[$this->key($gate['nis'])] ?? 0) > 1 || $nisCandidates->count() > 1)) {
                $items[] = $this->item($gate, null, 'conflict', 'manual_review', [], 'duplicate_nis');

                continue;
            }
            if ($nisCandidates->count() === 1) {
                $candidate = $nisCandidates->first();
                $seenLocal[$candidate->id] = true;
                $emailCandidate = filled($gate['email']) ? $byEmail->get($this->key($gate['email']), collect()) : collect();
                $error = $gate['type'] !== 'student' || ! $candidate->santri
                    ? 'student_type_mismatch'
                    : ($emailCandidate->isNotEmpty() && ! $emailCandidate->contains(fn ($user) => $user->is($candidate)) ? 'nis_email_conflict' : 'bridge_required_by_nis');
                $items[] = $this->item($gate, $candidate, 'conflict', 'manual_review', [], $error);

                continue;
            }
            $emailCandidates = $gate['email'] ? $byEmail->get($this->key($gate['email']), collect()) : collect();
            if ($emailCandidates->isNotEmpty()) {
                foreach ($emailCandidates as $candidate) {
                    $seenLocal[$candidate->id] = true;
                }
                $code = $emailCandidates->count() > 1 || ($gateEmailCounts[$this->key($gate['email'])] ?? 0) > 1
                    ? 'multiple_local_candidates'
                    : ($emailCandidates->first()->gate_user_uuid ? 'uuid_mismatch' : ($gate['email_verified'] ? 'bridge_required_by_verified_email' : 'review_email_unverified'));
                $items[] = $this->item($gate, $emailCandidates->count() === 1 ? $emailCandidates->first() : null, 'conflict', 'manual_review', [], $code);
            } else {
                $items[] = $this->item($gate, null, 'missing_in_application', 'create_local_user');
            }
        }
        foreach ($locals as $local) {
            if (! isset($seenLocal[$local->id])) {
                $category = $local->identityOwnership() === 'local_manual' ? 'local_manual' : ($local->santri ? 'missing_student_in_gate' : 'local_only');
                $items[] = $this->item([], $local, $category, 'manual_review');
            }
        }

        return $items;
    }

    public function normalizeGate(array $raw): array
    {
        $access = data_get($raw, 'application_access.status', data_get($raw, 'application_access.smart', data_get($raw, 'applications.smart', data_get($raw, 'access_active', data_get($raw, 'application_access', true)))));

        return [
            'gate_user_uuid' => data_get($raw, 'gate_user_uuid', data_get($raw, 'uuid')),
            'name' => trim((string) data_get($raw, 'name', '')),
            'email' => strtolower(trim((string) data_get($raw, 'email', ''))),
            'username' => strtolower(trim((string) data_get($raw, 'username', ''))),
            'type' => $this->normalizedType($raw),
            'email_verified' => filter_var(data_get($raw, 'email_verified', filled(data_get($raw, 'email_verified_at'))), FILTER_VALIDATE_BOOL),
            'legacy_sso_sub' => trim((string) data_get($raw, 'legacy_sso_sub', '')),
            'role' => strtolower((string) data_get($raw, 'application_access.role', data_get($raw, 'application_role', data_get($raw, 'role', data_get($raw, 'type', ''))))),
            'identity_active' => in_array(strtolower((string) data_get($raw, 'status', data_get($raw, 'identity_status', 'active'))), ['active', 'enabled', '1'], true),
            'access_active' => is_string($access) ? in_array(strtolower($access), ['active', 'enabled', '1', 'true'], true) : filter_var($access, FILTER_VALIDATE_BOOL),
            'photo' => data_get($raw, 'photo'), 'qr_code' => data_get($raw, 'qr_code'),
            'nis' => data_get($raw, 'nis'), 'nip' => data_get($raw, 'nip'),
        ];
    }

    private function linkedCategory(array $gate, User $local): string
    {
        if ($local->hasRole(UserRole::SUPER_ADMIN)) {
            return 'matched';
        }

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
        if ($local->hasRole(UserRole::SUPER_ADMIN)) {
            return [];
        }

        $diff = [];
        foreach (['name', 'email'] as $field) {
            if ($this->key((string) $local->{$field}) !== $this->key((string) $gate[$field])) {
                $diff[$field] = ['local' => $local->{$field}, 'gate' => $gate[$field]];
            }
        }

        $localRole = $local->role?->value ?? $local->role;
        $resolvedRole = $this->resolvedRole($gate, $local);
        if ($resolvedRole !== null && $this->key((string) $localRole) !== $this->key($resolvedRole)) {
            $diff['role'] = ['local' => $localRole, 'gate' => $resolvedRole];
        }

        return $diff;
    }

    public function resolvedRole(array $gate, ?User $local = null): ?string
    {
        $localRole = $local?->role?->value ?? $local?->role;

        if ($localRole === UserRole::SUPER_ADMIN->value) {
            return UserRole::SUPER_ADMIN->value;
        }

        if (($gate['type'] ?? '') === 'student') {
            return UserRole::SANTRI->value;
        }

        if (in_array($localRole, [UserRole::ADMIN->value, UserRole::BENDAHARA->value, UserRole::KASIR->value], true)) {
            return $localRole;
        }

        if (! in_array(($gate['type'] ?? ''), ['teacher', 'staff', 'admin', 'parent'], true)) {
            return null;
        }

        return UserRole::MEMBER->value;
    }

    private function item(array $gate, ?User $local, string $category, string $action, array $differences = [], ?string $error = null): array
    {
        return ['gate_user_uuid' => $gate['gate_user_uuid'] ?? null, 'local_user_id' => $local?->id, 'category' => $category, 'recommended_action' => $action, 'gate_payload' => $gate ?: null, 'local_payload' => $local ? ['id' => $local->id, 'name' => $local->name, 'email' => $local->email, 'role' => $local->role?->value ?? $local->role, 'status' => $local->status] : null, 'differences' => $differences ?: null, 'error_code' => $error];
    }

    private function key(string $value): string
    {
        return mb_strtolower(trim($value));
    }

    private function normalizedType(array $raw): string
    {
        $type = strtolower(trim((string) data_get($raw, 'type', '')));
        if ($type !== '') {
            return $type;
        }

        $legacyRole = strtolower(trim((string) data_get($raw, 'role', '')));

        return match ($legacyRole) {
            UserRole::SANTRI->value => 'student',
            UserRole::WALI->value => 'parent',
            UserRole::KASIR->value, UserRole::BENDAHARA->value => 'staff',
            UserRole::SUPER_ADMIN->value => 'admin',
            default => $legacyRole,
        };
    }
}
