# Gate Sync Final Flow

Post-update Graphify trace (1,645 nodes, 3,486 edges, 221 communities) resolves the implemented flow as follows:

1. Superadmin routes call `GateUserSyncController::preview`.
2. `GateUserSyncService::preview` calls `GateProvisioningClient::users`, then `GateUserReconciliationService::reconcile`, and persists an expiring batch/items without local user mutation.
3. `GateUserSyncController::apply` validates item IDs/actions and calls `GateUserSyncService::apply`.
4. Apply locks the batch inside `DB::transaction`, rejects expiry/double apply/cross-batch or category-invalid actions, and delegates allowed identity-only work to `applyItem`.
5. `applyItem` mutates only allowed `User` identity/status fields and minimum `Santri`/`Wali` profile fields. It does not call `WalletService`, `PosService`, `AccountingService`, transaction models, inventory models, or journal models.
6. After the local transaction commits, `report` sends batch-scoped safe results. Failure stores `report_pending`; `retryReport` sends the existing results without invoking apply again.
7. `EnsureSessionRole`, `EnsureRole`, password login, and SSO callback enforce suspended status; historical model relationships remain unchanged.

Key traced symbols: `GateUserSyncController::{preview,apply,result,retry}`, `GateUserReconciliationService::{reconcile,linkedCategory,differences}`, `GateUserSyncService::{preview,apply,applyItem,report,retryReport}`, and `GatePhotoSyncService::sync`.
