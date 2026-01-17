# SMART Architecture Overview

This document captures the high-level architecture derived from `app-summary.md`. It acts as a living reference while the SMART platform is being implemented.

## Core Modules

- **Identity & Access**
  - `users` table augmented with a `role` enum (`admin`, `bendahara`, `kasir`, `santri`, `wali`).
  - Separate profile tables for `Santri` and `Wali` linked to `users`.
  - Simple RBAC handled via middleware and Laravel policies.
- **Point of Sale**
  - Optimised for mobile-first workflows using Inertia/Vue with cache-friendly APIs.
  - Offline queue stored locally on the PWA client; Laravel endpoint accepts batched sync payloads.
  - Transactions reference products, locations, and payment breakdown (cash, wallet, gateway).
- **Wallet & Limits**
  - Each santri has a `wallet_balance`, `daily_limit`, and optional whitelist/blacklist of categories.
  - `wallet_transactions` capture debits/credits with balance-after snapshots and actor metadata.
- **Payments**
  - Strategy interface `PaymentProvider` with concrete classes for iPaymu (default) and stubs for Midtrans and Doku.
  - Webhooks stored in `payment_webhook_logs` for idempotency and audit.
  - Payments linked to wallet top-ups or POS settlements.
- **Accounting Engine**
  - Chart of accounts stored in `accounts`.
  - `journal_entries` + `journal_lines` enforce double-entry.
  - Event listeners map domain events (transaction settled, wallet top-up, inventory purchase) into composed journal entries.
- **Inventory**
  - `products` belong to a `product_category` and `location`.
  - `inventory_movements` track restock, adjustment, and depletion events; POS penjualan otomatis mengurangi stok dan mencatat movement bertipe `sale`.
- **Analytics & Reporting**
  - Pre-aggregated materialised tables (`sales_aggregates`, `wallet_spend_stats`) refreshed via scheduled jobs.
  - API endpoints expose filterable datasets for dashboards.
- **Frontend PWA**
  - POS shell `/pos` dibangun dengan vanilla JS (Vite) dan antrean offline berbasis `localStorage`.
  - Service worker mem-cache shell dan fallback rute utama.
  - Axios + Sanctum token helper menjaga Authorization header & auto logout ketika token invalid.
- **Auth Bridge**
  - Form `/login` memanfaatkan controller `AuthBridgeController` untuk menukar kredensial menjadi token Sanctum, menyuntikkannya ke localStorage, dan me-redirect ke POS.
  - `/logout` mencabut personal access token dan membersihkan storage.

## Data Model Snapshot

| Table | Purpose |
| --- | --- |
| `users` | Core authentication with role enum |
| `santris` | Domain profile for students, includes wallet balance and limits |
| `walis` | Guardian profile linked to `users`, contact information |
| `locations` | Organisational units (kantin, koperasi, laundry, dll.) |
| `product_categories` | Product grouping with whitelist flag |
| `products` | SKU, pricing, stock, category, and location |
| `inventory_movements` | Stock adjustments with type (`restock`, `sale`, `adjustment`) |
| `transactions` | POS orders with payment summary and offline sync flags |
| `transaction_items` | Line items referencing products |
| `wallet_transactions` | Ledger for wallet debits/credits with balance-after |
| `payments` | Gateway interaction records, status, and payload metadata |
| `payment_webhook_logs` | Raw webhook payloads for replay & audit |
| `accounts` | Chart of accounts definitions |
| `journal_entries` | Double-entry journal headers tied to domain events |
| `journal_lines` | Debit/credit rows pointing to accounts |
| `payment_provider_configs` | Provider settings, API keys, priority queue |
| `daily_closings` | Kasir end-of-day cash up, totals, and approvals |

## Application Layers

- **HTTP Layer:** Laravel routes returning Inertia responses for SPA workflows and JSON endpoints for mobile/Android wrapper.
- **Service Layer:** POSService, WalletService, AccountingService encapsulate transactional logic; PaymentService delegates to `PaymentProvider`.
- **Event Driven:** Domain events (`TransactionCompleted`, `WalletTopUpConfirmed`, `InventoryReplenished`) trigger listeners that update analytics tables and accounting entries.
- **Jobs & Queues:** Redis-backed queues process webhook verifications, PDF exports, and heavy analytics updates.

## Non-Functional Notes

- PWA service worker caches shell assets, provides offline cart, and syncs transactions when restored.
- Audit logging via Laravel Telescope and dedicated `activity_logs` table for sensitive events (limit change, manual journal).
- Configuration exposed through `config/smart.php` to centralise provider and feature toggles.

This document will evolve as features are implemented. Keep it aligned with the implementation to ensure architecture and code stay in sync.
