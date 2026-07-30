<?php

namespace App\Services\Gate;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GateIdentityBridgeService
{
    public const LINKABLE = [
        'PROPOSED-LINK-BY-NIS',
        'PROPOSED-LINK-BY-VERIFIED-EMAIL',
        'LEGACY-SUB-MATCH',
    ];

    public function __construct(private GateUserReconciliationService $reconciler) {}

    public function preview(array $gateUsers, ?Collection $localUsers = null): array
    {
        $locals = ($localUsers ?? User::query()->with('santri')->get())->values();
        $gate = collect($gateUsers)->map(fn (array $row) => $this->reconciler->normalizeGate($row))->values();
        $byUuid = $locals->filter->gate_user_uuid->groupBy(fn (User $user) => $this->key($user->gate_user_uuid));
        $byEmail = $locals->filter->email->groupBy(fn (User $user) => $this->key($user->email));
        $byNis = $locals->filter(fn (User $user) => filled($user->santri?->nis))->groupBy(fn (User $user) => $this->key($user->santri->nis));
        $byLegacy = $locals->filter->sso_sub->groupBy(fn (User $user) => $this->key($user->sso_sub));
        $gateUuidCounts = $gate->filter(fn ($row) => filled($row['gate_user_uuid']))->countBy(fn ($row) => $this->key($row['gate_user_uuid']));
        $gateNisCounts = $gate->filter(fn ($row) => filled($row['nis']))->countBy(fn ($row) => $this->key($row['nis']));
        $gateEmailCounts = $gate->filter(fn ($row) => filled($row['email']))->countBy(fn ($row) => $this->key($row['email']));
        $seenLocal = [];
        $items = [];

        foreach ($gate as $row) {
            $result = $this->matchGateUser($row, $byUuid, $byNis, $byEmail, $byLegacy, $gateUuidCounts, $gateNisCounts, $gateEmailCounts);
            if ($result['local_user_id']) {
                if (isset($seenLocal[$result['local_user_id']]) && $seenLocal[$result['local_user_id']] !== $row['gate_user_uuid']) {
                    $result['classification'] = 'MULTIPLE-MATCH';
                    $result['reason'] = 'SMART_USER_MATCHED_TO_MULTIPLE_GATE_USERS';
                    $result['linkable'] = false;
                }
                $seenLocal[$result['local_user_id']] = $row['gate_user_uuid'];
            }
            $items[] = $result;
        }

        foreach ($locals as $local) {
            if (isset($seenLocal[$local->id])) {
                continue;
            }
            $ownership = $local->identityOwnership();
            $classification = $ownership === 'local_manual'
                ? 'LOCAL-MANUAL'
                : ($local->santri ? 'MISSING-STUDENT-IN-GATE' : 'NOT-FOUND');
            $items[] = $this->result($classification, null, $local, $classification, false);
        }

        $items = collect($items);

        return [
            'items' => $items->all(),
            'counts' => [
                'TOTAL_SMART_USERS' => $locals->count(),
                'GATE_MANAGED_EXISTING' => $locals->filter(fn ($user) => $user->identityOwnership() === 'gate_managed')->count(),
                'LOCAL_MANUAL' => $items->where('classification', 'LOCAL-MANUAL')->count(),
                'MATCHED_BY_UUID' => $items->where('classification', 'MATCHED-BY-UUID')->count(),
                'PROPOSED_BY_NIS' => $items->where('classification', 'PROPOSED-LINK-BY-NIS')->count(),
                'PROPOSED_BY_VERIFIED_EMAIL' => $items->where('classification', 'PROPOSED-LINK-BY-VERIFIED-EMAIL')->count(),
                'REVIEW_UNVERIFIED_EMAIL' => $items->where('classification', 'REVIEW-EMAIL-UNVERIFIED')->count(),
                'MATCHED_BY_LEGACY_SUB' => $items->where('classification', 'LEGACY-SUB-MATCH')->count(),
                'MISSING_STUDENT_IN_GATE' => $items->where('classification', 'MISSING-STUDENT-IN-GATE')->count(),
                'NOT_FOUND_LOCAL_CANDIDATE' => $items->whereIn('classification', ['NOT-FOUND', 'NOT-FOUND-LOCAL-CANDIDATE'])->count(),
                'CONFLICT' => $items->where('classification', 'CONFLICT')->count(),
                'MULTIPLE_MATCH' => $items->where('classification', 'MULTIPLE-MATCH')->count(),
                'PROPOSED_GATE_UUID_UPDATES' => $items->where('linkable', true)->count(),
            ],
        ];
    }

    public function apply(array $gateUsers): array
    {
        if (! config('services.gate.identity_bridge_enabled', false)) {
            throw ValidationException::withMessages(['bridge' => 'GATE_IDENTITY_BRIDGE_DISABLED']);
        }

        return DB::transaction(function () use ($gateUsers) {
            $locals = User::query()->with('santri')->lockForUpdate()->get();
            $preview = $this->preview($gateUsers, $locals);
            $linkable = collect($preview['items'])->where('linkable', true);
            $updated = 0;
            foreach ($linkable as $item) {
                $affected = User::query()
                    ->whereKey($item['local_user_id'])
                    ->whereNull('gate_user_uuid')
                    ->update(['gate_user_uuid' => $item['gate_user_uuid'], 'identity_source' => 'gate_managed']);
                if ($affected !== 1) {
                    throw ValidationException::withMessages(['bridge' => 'IDENTITY_BRIDGE_AFFECTED_ROWS_MISMATCH']);
                }
                $updated += $affected;
            }

            if ($updated !== $linkable->count()) {
                throw ValidationException::withMessages(['bridge' => 'IDENTITY_BRIDGE_AFFECTED_ROWS_MISMATCH']);
            }

            return ['updated' => $updated, 'status' => $updated === 0 ? 'NO-CHANGE' : 'APPLIED', 'preview' => $preview];
        });
    }

    private function matchGateUser($gate, $byUuid, $byNis, $byEmail, $byLegacy, $uuidCounts, $nisCounts, $emailCounts): array
    {
        $uuid = $this->key((string) $gate['gate_user_uuid']);
        if (! Str::isUuid($uuid)) {
            return $this->result('CONFLICT', $gate, null, 'INVALID_OR_MISSING_GATE_UUID', false);
        }
        if (($uuidCounts[$uuid] ?? 0) > 1 || ($byUuid[$uuid] ?? collect())->count() > 1) {
            return $this->result('MULTIPLE-MATCH', $gate, null, 'DUPLICATE_GATE_UUID', false);
        }

        $uuidUser = ($byUuid[$uuid] ?? collect())->first();
        $nis = $this->key((string) $gate['nis']);
        $email = $this->key((string) $gate['email']);
        $nisUsers = $nis !== '' ? ($byNis[$nis] ?? collect()) : collect();
        $emailUsers = $email !== '' ? ($byEmail[$email] ?? collect()) : collect();
        if (($nis !== '' && (($nisCounts[$nis] ?? 0) > 1 || $nisUsers->count() > 1)) || ($email !== '' && (($emailCounts[$email] ?? 0) > 1 || $emailUsers->count() > 1))) {
            return $this->result('MULTIPLE-MATCH', $gate, null, 'DUPLICATE_NIS_OR_EMAIL', false);
        }

        $nisUser = $nisUsers->first();
        $emailUser = $emailUsers->first();
        if (($uuidUser && $nisUser && ! $uuidUser->is($nisUser)) || ($uuidUser && $emailUser && ! $uuidUser->is($emailUser)) || ($nisUser && $emailUser && ! $nisUser->is($emailUser))) {
            return $this->result('CONFLICT', $gate, $uuidUser ?? $nisUser ?? $emailUser, 'UUID_NIS_EMAIL_POINT_TO_DIFFERENT_USERS', false);
        }
        if ($uuidUser) {
            if (($uuidUser->santri && $gate['type'] !== 'student') || ($uuidUser->santri && $nis !== '' && $this->key($uuidUser->santri->nis) !== $nis)) {
                return $this->result('CONFLICT', $gate, $uuidUser, 'LINKED_STUDENT_INTEGRITY_CONFLICT', false);
            }

            return $this->result('MATCHED-BY-UUID', $gate, $uuidUser, 'EXACT_GATE_UUID', false);
        }
        if (($nisUser || $nis !== '') && ($gate['type'] !== 'student' || ($nisUser && ! $nisUser->santri))) {
            return $this->result('CONFLICT', $gate, $nisUser, 'STUDENT_TYPE_MISMATCH', false);
        }
        if ($nisUser) {
            return $this->candidate('PROPOSED-LINK-BY-NIS', $gate, $nisUser);
        }
        if ($emailUser) {
            return $this->candidate($gate['email_verified'] ? 'PROPOSED-LINK-BY-VERIFIED-EMAIL' : 'REVIEW-EMAIL-UNVERIFIED', $gate, $emailUser, $gate['email_verified']);
        }
        if ($gate['legacy_sso_sub'] !== '') {
            $legacyUsers = $byLegacy[$this->key($gate['legacy_sso_sub'])] ?? collect();
            if ($legacyUsers->count() > 1) {
                return $this->result('MULTIPLE-MATCH', $gate, null, 'DUPLICATE_LEGACY_SUB', false);
            }
            if ($legacyUsers->count() === 1) {
                return $this->candidate('LEGACY-SUB-MATCH', $gate, $legacyUsers->first());
            }
        }

        return $this->result('NOT-FOUND-LOCAL-CANDIDATE', $gate, null, 'NO_SAFE_LOCAL_CANDIDATE', false);
    }

    private function candidate(string $classification, $gate, User $user, bool $linkable = true): array
    {
        if ($user->gate_user_uuid && $this->key($user->gate_user_uuid) !== $this->key($gate['gate_user_uuid'])) {
            return $this->result('CONFLICT', $gate, $user, 'EXISTING_GATE_UUID_MISMATCH', false);
        }

        return $this->result($classification, $gate, $user, $classification, $linkable && ! $user->gate_user_uuid);
    }

    private function result(string $classification, $gate, ?User $user, string $reason, bool $linkable): array
    {
        return [
            'classification' => $classification,
            'reason' => $reason,
            'local_user_id' => $user?->id,
            'gate_user_uuid' => $gate['gate_user_uuid'] ?? null,
            'gate_name' => $gate['name'] ?? null,
            'gate_type' => $gate['type'] ?? null,
            'nis' => $gate['nis'] ?? $user?->santri?->nis,
            'email' => $gate['email'] ?? $user?->email,
            'linkable' => $linkable,
        ];
    }

    private function key(?string $value): string
    {
        return mb_strtolower(trim((string) $value));
    }
}
