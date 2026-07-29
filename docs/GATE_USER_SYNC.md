# Gate User Synchronization

## Architecture

SMART pulls identities from Gate only when a superadmin starts a preview. `GateProvisioningClient` owns authenticated HTTP, `GateUserReconciliationService` produces a deterministic dry run, and `GateUserSyncService` applies reviewed actions transactionally. `gate_user_uuid` is the sole cross-application identity key. Email and username-like identifiers are conflict signals only and never auto-link accounts.

## Configuration

Set `GATE_URL` (HTTPS), `GATE_PROVISIONING_CLIENT_ID`, and `GATE_PROVISIONING_CLIENT_SECRET`. `GATE_SYNC_ENABLED=false`, `GATE_SYNC_DRY_RUN=true`, `GATE_SYNC_PHOTO=false`, and `GATE_SYNC_QR=false` are the safe defaults. A deployment, application boot, or migration never starts synchronization. Secrets remain server-side and must not be committed.

## Database schema

`users` gains nullable unique `gate_user_uuid`, active/suspended `status`, `last_gate_synced_at`, and a photo checksum. `gate_sync_batches` and `gate_sync_items` retain preview, selected actions, safe identity snapshots, results, expiry, apply state, and report retry state. Passwords, secrets, balances, transactions, limits, and signed photo URLs are not included in diffs or reporting.

## Reconciliation

Exactly eight categories are emitted: `matched`, `needs_update`, `missing_in_application`, `access_revoked`, `inactive_in_gate`, `reactivation_required`, `local_only`, and `conflict`. Exact UUID is checked first. Matching email without matching UUID is `conflict/manual_review`; unmatched local users are `local_only/manual_review`.

## Preview and apply

Preview fetches Gate users and stores an expiring audit batch without changing users, tokens, photos, or domain data. Apply requires both an explicit `--apply` operation and production opt-in configuration, accepts item IDs and allowed actions only, and reads identity payloads from the server-side batch. A locked local transaction prevents double apply. Duplicate identities block the entire apply. Item-level provisioning errors are recorded while other valid items can complete, so a partial result is auditable. Gate reporting starts only after commit, and a failed report becomes `report_pending`; retry never repeats local apply.

An empty successful Gate response is treated as suspicious when SMART has active users. It creates no preview and cannot suspend users. Apply also stops when configurable create, role-change, or suspension percentages exceed their limits.

## Provisioning and suspension

Administrative and wali accounts use minimum repository fields. Santri creation requires a canonical NIS; otherwise the item fails with `SANTRI_PROFILE_DATA_INCOMPLETE`. Initial wallet/limits remain repository defaults of zero and no ledger is created. Wali-santri relationships are never inferred. Suspension changes only account status and revokes API tokens; middleware blocks session/API/POS/portal/admin access while history remains intact.

## Photos and QR

Both capabilities are opt-in. Photo URLs must use the exact `GATE_URL` hostname; credentials, localhost, private/reserved DNS answers, unsafe redirects, excess redirects, and DNS failures are rejected. DNS is pinned for the request, response bytes and time are bounded, and JPEG/PNG/WebP MIME must match the file signature. A random path under the public storage disk is used and the old photo is removed only after successful storage. Signed URLs are not persisted. Gate QR values are never identity keys; duplicate values fail with `QR_CODE_CONFLICT` and are not overwritten.

## Authorization and logs

All sync routes use `session.role:super_admin`; other authenticated roles receive 403. Activity logs store batch/user/action/result identifiers only, never credentials or raw signed URLs.

## Commands and testing

`php artisan gate:migration-preflight` checks migration blockers without writes or Gate access. `php artisan smart:gate-sync-preflight` checks post-migration local readiness; add `--check-connection` only when a deliberate Gate connectivity check is authorized. Use `php artisan gate:sync-users --preview --actor=<SUPERADMIN_ID>` and review the emitted batch UUID. Apply only that reviewed batch with `php artisan gate:sync-users --apply --batch=<UUID> --actor=<SUPERADMIN_ID>` after safe configuration has been explicitly enabled.

## Deployment, rollback, troubleshooting, limitations

Before deployment: back up the database, audit duplicate identity fields, configure secrets, run the preflight and test/build suite, then run the new migration in the target environment through the normal release process. Rollback drops only sync tables/identity columns; do not roll back after operational use without exporting audit data. A pending report is safe to retry. Current limitations: no automatic legacy account linking; no inferred guardian relationships; Gate must provide NIS to provision santri.
