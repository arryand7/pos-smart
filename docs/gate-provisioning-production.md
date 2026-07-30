# Gate Provisioning Production Runbook

Gate is the identity source of truth for Gate-managed users. SMART never creates Gate users or assignments and never synchronizes from deployment hooks, migrations, schedulers, or application boot.

## Configuration

Store credentials outside Git:

```dotenv
GATE_URL=https://gate.example.com
GATE_PROVISIONING_CLIENT_ID=
GATE_PROVISIONING_CLIENT_SECRET=
GATE_IDENTITY_BRIDGE_ENABLED=false
GATE_SYNC_ENABLED=false
GATE_SYNC_DRY_RUN=true
GATE_SYNC_PHOTO=false
```

Gate-managed identity fields are UUID, username when published, email, name, NIS/NIP, active status, type, and approved application role mappings. SMART-owned wallet, transaction, payment, journal, inventory, limits, preferences, operational profiles, and business relations are outside sync authority.

## Migration and ownership rehearsal

Run the read-only preflight before migration:

```bash
php artisan gate:migration-preflight
```

The ownership migration adds nullable `identity_source`; it does not classify existing users by role. A UUID-linked user is always Gate-managed. Explicitly classify a proven local account with:

```bash
php artisan gate:set-user-ownership <USER_ID> local_manual
```

Local wali and other approved local accounts remain active when absent from Gate. Unclassified missing santri are reported for investigation.

## Identity bridge preview and apply

SMART `santri` officially maps to Gate `student`. Matching priority is UUID, unique NIS, unique verified email, reviewed unverified email, then an official legacy subject if Gate publishes one. Names never match.

```bash
php artisan gate:bridge-identities --preview
```

Review all counts and conflicts. The current Gate provisioning payload does not contain email-verification evidence or legacy numeric subject, so Smart will not automatically use those paths until the API contract supplies them.

The provisioning endpoint currently returns only actively assigned SMART users. With zero assignments, bridge preview intentionally hard-stops. To satisfy the required ordering, Gate operations must first provide an authorized non-mutating population preview/export or extend the provisioning contract; SMART must not create assignments to bypass this dependency.

After a reviewed non-empty population and ownership decision, a Gate administrator previews and creates assignments only for Gate users entitled to SMART—not all 450 SMART rows. Re-run bridge preview. For a controlled bridge window, enable `GATE_IDENTITY_BRIDGE_ENABLED=true` and run:

```bash
php artisan gate:bridge-identities --apply
```

Apply fills only null UUIDs, changes no other identity or business field, retains tokens, verifies affected rows, and is idempotent.

## Reconciliation

After linkage:

```bash
php artisan smart:gate-sync-preflight
php artisan gate:sync-users --preview --actor=<SUPERADMIN_ID>
```

UUID is primary; NIS and email are integrity checks. Unknown role mappings, duplicate identifiers, type mismatch, and conflicting candidates require review. Empty payload is a blocker. Absence never suspends a user. Suspension requires a linked Gate-managed UUID and an explicit inactive/revoked Gate record.

Only after separate approval, enable `GATE_SYNC_ENABLED=true`, set `GATE_SYNC_DRY_RUN=false`, and apply the exact reviewed batch:

```bash
php artisan gate:sync-users --apply --batch=<PREVIEW_UUID> --actor=<SUPERADMIN_ID>
```

Threshold guards for create, role changes, and suspension remain mandatory. Photo synchronization stays disabled until separately rehearsed.

## Conflict handling and rollback

- Duplicate UUID/NIS/email or disagreeing UUID/NIS/email: resolve through reviewed source-data correction; do not auto-merge.
- `REVIEW-EMAIL-UNVERIFIED`: obtain Gate verification evidence or use a separately audited manual decision; default apply skips it.
- `MISSING-STUDENT-IN-GATE`: investigate Gate population and assignment; do not silently mark local.
- HTTP 401/403: verify client scope without printing secrets.
- Empty success response: verify assignment and application selection; no local mutation occurred.

Before any migration or apply, verify a database backup. For an incorrect link, stop further applies, preserve evidence, and use an approved identity-only correction. Do not delete users or restore wallet, transaction, payment, journal, inventory, or limit data unless an independent investigation proves those domains changed.

Controlled order: migration rehearsal; bridge preview; ownership decisions; Gate-side assignment preview; Gate administrator creates approved assignments; provisioning preview; bridge apply; reconciliation preview; separately approved reconciliation apply.
