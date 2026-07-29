# Gate Sync Risk Map

Graphify traces user relationships into `Santri`, `Wali`, `Transaction`, `WalletTransaction`, `DailyClosing`, and POS/accounting services. The safe mutation boundary is the `users` identity fields plus newly created minimum profiles. Sync code must never call `WalletService`, `PosService`, `AccountingService`, wallet controllers, inventory models, or transaction models. Revocation changes only `users.status`, preserves all foreign keys and history, and revokes access tokens.
