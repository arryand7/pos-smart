# Gate Provisioning Production Runbook

This runbook is for a controlled database rehearsal and production release. Gate assignments are managed by a Gate administrator; SMART never creates or changes them. Do not run synchronization from deployment hooks, migrations, schedulers, or application boot.

## 1. Client configuration

Create a dedicated Gate provisioning client authorized to read users assigned to the SMART application and report sync results. Configure values through the deployment secret store, never Git:

```dotenv
GATE_URL=https://gate.example.com
GATE_PROVISIONING_CLIENT_ID=
GATE_PROVISIONING_CLIENT_SECRET=
GATE_SYNC_ENABLED=false
GATE_SYNC_DRY_RUN=true
GATE_SYNC_PHOTO=false
```

Keep the remaining timeout, batch-size, photo-size, redirect, and threshold defaults from `.env.example` unless rehearsal evidence supports a reviewed change.

## 2. SMART application assignment

The provisioning API returns only users with an active SMART application assignment. A Gate administrator must create and maintain that assignment in Gate. SMART does not infer, auto-merge, or create Gate assignments. HTTP 401/403 means the client is missing or lacks access; HTTP success with zero users may mean the SMART assignment is absent or empty and is treated as a blocker while SMART has active users.

## 3. Migration preflight and preview

Before the schema migration, run the read-only duplicate check against the rehearsal database:

```bash
php artisan gate:migration-preflight
```

After migration and local readiness checks, create a preview as a known local superadmin:

```bash
php artisan smart:gate-sync-preflight
php artisan gate:sync-users --preview --actor=<SUPERADMIN_ID>
```

Preview persists only an expiring audit batch. It does not mutate users, roles, passwords, tokens, photos, wallet balances, transactions, limits, inventory, or accounting data. Review counts for received, matched, create, update, role-change, suspend, reactivate, unmatched, duplicate, and errors.

## 4. Apply

Apply is disabled by default. After reviewing the exact preview batch, deliberately set `GATE_SYNC_ENABLED=true` and `GATE_SYNC_DRY_RUN=false` for the controlled operation, then run:

```bash
php artisan gate:sync-users --apply --batch=<PREVIEW_UUID> --actor=<SUPERADMIN_ID>
```

Apply is locked and once-only. It creates or updates identity fields, suspends or reactivates according to a reviewed Gate record, and records every item. It never deletes a SMART user or changes password hashes. Suspension revokes that user's API tokens but preserves all business history. Unknown role mappings fail the item without replacing the existing role. Duplicate identity conflicts block apply. A failed Gate result report remains retryable without repeating local mutations.

## 5. Photo synchronization

Keep `GATE_SYNC_PHOTO=false` until a separate rehearsal validates storage permissions and the Gate photo endpoint. When enabled, only HTTPS URLs on the exact `GATE_URL` hostname are accepted. Private/reserved addresses, URL credentials, unsafe redirects, oversized responses, and content whose MIME does not match a supported image signature are rejected. Preview never downloads photos.

## 6. Threshold guards

The following percentage limits stop apply and require review:

- `GATE_SYNC_MAX_SUSPEND_PERCENT`
- `GATE_SYNC_MAX_ROLE_CHANGE_PERCENT`
- `GATE_SYNC_MAX_CREATE_PERCENT`

Percentages are calculated against current local/active user counts. Do not raise a limit merely to bypass a surprising preview; reconcile assignments and identity conflicts first. There is intentionally no deployment-time override for an empty Gate response.

## 7. Troubleshooting an empty or invalid response

- `GATE_EMPTY_ASSIGNMENT_RESPONSE`: verify active SMART assignments in Gate and the selected application/client. No local suspension occurred.
- `GATE_AUTHENTICATION_FAILED` or HTTP 401/403: verify the client ID, secret injection, and provisioning scope without printing the secret.
- `GATE_INVALID_RESPONSE`: compare the Gate response schema with the provisioning contract; do not apply.
- Duplicate identity errors: resolve UUID/email/SSO duplicates through a separately reviewed data correction. SMART does not auto-link by email, name, username, NIS, NIP, photo, or QR.
- Threshold errors: inspect the preview and Gate assignment state before changing configuration.

## 8. Operational rollback

Before migration or apply, take and verify a database backup through the normal operations process. For an incorrect identity update, stop further applies, preserve the batch/item and activity audit records, restore identity/status from the reviewed backup or an approved corrective batch, and reissue tokens only through the normal authentication process. Do not delete users or restore wallet, transaction, inventory, limit, or accounting tables unless an independent incident investigation proves they changed. Do not roll back the schema after sync usage without first exporting audit records and reviewing foreign-key impact.
