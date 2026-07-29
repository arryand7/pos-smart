# Gate User Synchronization

## Architecture

SMART pulls identities from Gate only when a superadmin starts a preview. `GateProvisioningClient` owns authenticated HTTP, `GateUserReconciliationService` produces a deterministic dry run, and `GateUserSyncService` applies reviewed actions transactionally. `gate_user_uuid` is the sole cross-application identity key. Email and username-like identifiers are conflict signals only and never auto-link accounts.

## Configuration

Set `GATE_URL` (HTTPS), `GATE_PROVISIONING_CLIENT_ID`, and `GATE_PROVISIONING_CLIENT_SECRET`. Optional `GATE_SYNC_PHOTO` and `GATE_SYNC_QR` default to false. Secrets remain server-side in `config/services.php`.

## Database schema

`users` gains nullable unique `gate_user_uuid`, active/suspended `status`, `last_gate_synced_at`, and a photo checksum. `gate_sync_batches` and `gate_sync_items` retain preview, selected actions, safe identity snapshots, results, expiry, apply state, and report retry state. Passwords, secrets, balances, transactions, limits, and signed photo URLs are not included in diffs or reporting.

## Reconciliation

Exactly eight categories are emitted: `matched`, `needs_update`, `missing_in_application`, `access_revoked`, `inactive_in_gate`, `reactivation_required`, `local_only`, and `conflict`. Exact UUID is checked first. Matching email without matching UUID is `conflict/manual_review`; unmatched local users are `local_only/manual_review`.

## Preview and apply

Preview fetches Gate users and stores an expiring batch without changing users or domain data. Apply accepts item IDs and allowed actions only; category and identity payload are read from the server-side batch. A locked local transaction prevents double apply. Conflict and local-only items cannot mutate. Gate reporting starts only after commit, and a failed report becomes `report_pending`; retry never repeats local apply.

## Provisioning and suspension

Administrative and wali accounts use minimum repository fields. Santri creation requires a canonical NIS; otherwise the item fails with `SANTRI_PROFILE_DATA_INCOMPLETE`. Initial wallet/limits remain repository defaults of zero and no ledger is created. Wali-santri relationships are never inferred. Suspension changes only account status and revokes API tokens; middleware blocks session/API/POS/portal/admin access while history remains intact.

## Photos and QR

Both capabilities are opt-in. Photos are downloaded only on checksum change over HTTPS, size/MIME/content validated, and swapped after successful storage. Signed URLs are not persisted. Gate QR values are never identity keys; duplicate values fail with `QR_CODE_CONFLICT` and are not overwritten.

## Authorization and logs

All sync routes use `session.role:super_admin`; other authenticated roles receive 403. Activity logs store batch/user/action/result identifiers only, never credentials or raw signed URLs.

## Commands and testing

`php artisan smart:gate-sync-preflight` performs read-only local readiness checks. Add `--check-connection` for the only network check. Tests use `Http::fake()` and cover all categories, dry-run behavior, authorization, password isolation, idempotent apply, and pending reports.

## Deployment, rollback, troubleshooting, limitations

Before deployment: back up the database, audit duplicate identity fields, configure secrets, run the preflight and test/build suite, then run the new migration in the target environment through the normal release process. Rollback drops only sync tables/identity columns; do not roll back after operational use without exporting audit data. A pending report is safe to retry. Current limitations: no automatic legacy account linking; no inferred guardian relationships; Gate must provide NIS to provision santri.
