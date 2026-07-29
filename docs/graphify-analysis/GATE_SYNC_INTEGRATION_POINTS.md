# Gate Sync Integration Points

The integration uses a superadmin-only group in `routes/web.php`, a thin `GateUserSyncController`, server-side reconciliation, batch/item audit tables, `DB::transaction` plus `lockForUpdate` for apply, and post-commit Gate reporting. Navigation is added under the existing Pengguna group. Suspension is enforced in both session and API role middleware and at password/OAuth login.
