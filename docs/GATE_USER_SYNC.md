# Gate User Synchronization

## Source of truth and ownership

Gate is the source of truth for Gate-managed identity. SMART owns wallet balances and ledgers, transactions, payments, journals, inventory, operational profile fields, preferences, limits, and business relations. Identity synchronization must never overwrite SMART-owned data.

`users.identity_source` records explicit ownership. Any user with `gate_user_uuid` is `gate_managed`; deliberately local accounts can be `local_manual`. Existing unlinked rows remain unclassified after migration so ownership is not guessed from role. New locally created users default to `local_manual`. Use `php artisan gate:set-user-ownership <USER_ID> local_manual` when evidence confirms local ownership. Marking `gate_managed` requires an existing UUID.

## Identity priority

The deterministic priority is:

1. exact `gate_user_uuid`;
2. exact unique NIS for SMART `santri` ↔ Gate `student`;
3. exact unique verified email;
4. unique unverified email for explicit review only;
5. an official `legacy_sso_sub` field, if Gate publishes it.

Names are display-only and never match users. UUID/NIS/email candidates that disagree, duplicate identifiers, type mismatches, or an existing different UUID are conflicts and never mutate.

The current Gate provisioning implementation publishes UUID, username, name, email, type, NIS/NIP, status, application access, role, photo, and optional QR. It does not currently publish email verification or a legacy numeric OIDC subject. SMART accepts explicit `email_verified`/`email_verified_at` and `legacy_sso_sub` fields if Gate formally adds them, but does not infer those facts. Historical numeric SMART `sso_sub` values therefore remain unchanged and are not automatically linked.

## Identity bridge

Run:

```bash
php artisan gate:bridge-identities --preview
```

Preview reports ownership, UUID/NIS/email/legacy matches, missing students, conflicts, multiple matches, and proposed updates without changing SMART data. Apply requires the explicit command plus `GATE_IDENTITY_BRIDGE_ENABLED=true`:

```bash
php artisan gate:bridge-identities --apply
```

Apply only fills null `gate_user_uuid` for unique conflict-free candidates and marks them `gate_managed` in a transaction. It verifies affected rows and is idempotent; a second run returns `NO-CHANGE`. It does not change `sso_sub`, password, role, status, tokens, profiles, wallet, transactions, payments, journals, inventory, limits, or Gate assignments.

## Reconciliation

After linkage, UUID is primary and NIS/email are integrity checks. Gate-managed identity fields may follow Gate only through approved role/type mappings. SMART `santri` maps to Gate `student`; unknown roles require review.

Local-manual users—including local wali and approved operational accounts—are not considered missing and are never suspended merely because they are absent. An unclassified santri absent from Gate is `MISSING-STUDENT-IN-GATE`, not automatically local. Absence from one payload never suspends anyone. Suspension requires a linked Gate-managed UUID and an explicit inactive/revoked Gate record. Empty provisioning payloads remain a hard stop.

## Configuration and safety

`GATE_SYNC_ENABLED=false`, `GATE_SYNC_DRY_RUN=true`, `GATE_IDENTITY_BRIDGE_ENABLED=false`, `GATE_SYNC_PHOTO=false`, and `GATE_SYNC_QR=false` are safe defaults. Deployment, boot, and migrations never start a bridge or sync. Photo downloads retain hostname, DNS/IP, redirect, timeout, size, MIME/signature, and safe-path protections.

`php artisan gate:migration-preflight` performs read-only migration checks. `php artisan smart:gate-sync-preflight` checks post-migration readiness. Reconciliation uses `php artisan gate:sync-users --preview --actor=<SUPERADMIN_ID>` and a separately approved `--apply --batch=<UUID>` command.

## Assignment and rollback

SMART never creates Gate assignments. Assignment population must be selected on the Gate side from users entitled to SMART, especially students and approved operational roles, rather than blindly using all SMART accounts. Preserve bridge/sync audit evidence and a verified backup. To roll back an incorrect link, stop all apply operations and use a separately reviewed data correction; never restore or delete business-domain data unless an independent incident investigation proves it changed.
