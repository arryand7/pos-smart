# Graph Report - smart  (2026-07-29)

## Corpus Check
- 294 files · ~99,267 words
- Verdict: corpus is large enough that graph structure adds value.

## Summary
- 1642 nodes · 3480 edges · 218 communities (172 shown, 46 thin omitted)
- Extraction: 92% EXTRACTED · 8% INFERRED · 0% AMBIGUOUS · INFERRED: 294 edges (avg confidence: 0.8)
- Token cost: 0 input · 0 output

## Graph Freshness
- Built from commit: `57657f98`
- Run `git rev-parse HEAD` and compare to check if the graph is stale.
- Run `graphify update .` after code changes (no API cost).

## Community Hubs (Navigation)
- index.js
- Carbon\Carbon
- dependencies
- User
- .redirect
- Controller
- Illuminate\Http\JsonResponse
- Illuminate\Http\Request
- Illuminate\Database\Eloquent\Model
- Payment
- Product
- UserRole.php
- Illuminate\View\View
- ProductCategory
- Illuminate\Http\RedirectResponse
- Illuminate\Database\Eloquent\Factories\Factory
- TestCase
- .config
- PaymentService
- MidtransProvider
- WalletTransaction
- Account
- Illuminate\Database\Eloquent\Relations\HasMany
- MainActivity
- ActivityLog
- .url
- scripts
- MoneyColumnAuditor
- DokuProvider.php
- IpaymuProvider
- composer.json
- QrScannerModule
- JournalEntry
- PosService
- EnsureSessionRole.php
- Wali
- SmartDemoSeeder
- PrinterModule
- ImageOptimizer
- DokuProvider
- UPDATE.md
- What You Must Do When Invoked
- require
- require-dev
- ProductManagementTest
- WaliController
- setup
- CODEX_GATE_USER_SYNC.md
- RuntimeException
- config
- PosConcurrencyTest
- Rupiah
- AuthServiceProvider
- psr-4
- Santri
- GateSyncBatch
- extra
- post-autoload-dump
- README.md
- Audit POS dan Wallet — 20 Juli 2026
- sw.js
- categories/create.blade.php
- categories/edit.blade.php
- locations/create.blade.php
- locations/edit.blade.php
- products/create.blade.php
- products/edit.blade.php
- dashboards/admin.blade.php
- dashboards/finance.blade.php
- layouts/admin.blade.php
- layouts/finance.blade.php
- portal.blade.php
- web.php
- 30. Output akhir
- DESIGN.md
- Gate User Synchronization
- SMART Debugging Playbook
- AuthBridgeController
- SMART Blueprint
- GateUserReconciliationService
- graphify reference: extra exports and benchmark
- 25. Automated tests
- 9. Delapan kategori reconciliation
- PosAccessTest
- .handle
- ProfileController
- graphify reference: query, path, explain
- Colors
- Components
- SMART Architecture Overview
- 3) Alur Aplikasi (User Flow)
- 5) Modul Utama
- 20. UI berdasarkan DESIGN.md
- 3. Query Graphify wajib
- 5. Prinsip integrasi wajib
- WalletController
- ApplyGateUserSyncRequest
- Responsive Behavior
- Typography
- 8. Service architecture
- SMART Android Wrapper
- MoneyValueException.php
- graphify reference: add a URL and watch a folder
- graphify reference: commit hook and native CLAUDE.md integration
- graphify reference: incremental update and cluster-only
- Layout
- 10. Algoritma reconciliation
- 14. Membuat user SMART
- graphify reference: GitHub clone and cross-repo merge
- graphify reference: transcribe video and audio
- 7. Database migration
- Route utama
- AGENTS.md
- extraction-spec.md
- CREDITS.md
- GATE_SYNC_INTEGRATION_POINTS.md
- GATE_SYNC_RISK_MAP.md
- GATE_USER_IDENTITY_MAP.md

## God Nodes (most connected - your core abstractions)
1. `User` - 143 edges
2. `Santri` - 91 edges
3. `Controller` - 76 edges
4. `Payment` - 68 edges
5. `Product` - 55 edges
6. `Location` - 50 edges
7. `Transaction` - 46 edges
8. `PaymentService` - 36 edges
9. `TestCase` - 36 edges
10. `Wali` - 35 edges

## Surprising Connections (you probably didn't know these)
- `renderReceiptQr()` --references--> `qrcode`  [EXTRACTED]
  resources/js/receipt.js → package.json
- `createCharge()` --references--> `Payment`  [EXTRACTED]
  app/Contracts/PaymentProvider.php → app/Models/Payment.php
- `checkTransaction()` --references--> `Payment`  [EXTRACTED]
  app/Contracts/PaymentProvider.php → app/Models/Payment.php
- `AccountingSettingController` --inherits--> `Controller`  [EXTRACTED]
  app/Http/Controllers/Admin/AccountingSettingController.php → app/Http/Controllers/Controller.php
- `ActivityLogController` --inherits--> `Controller`  [EXTRACTED]
  app/Http/Controllers/Admin/ActivityLogController.php → app/Http/Controllers/Controller.php

## Import Cycles
- None detected.

## Communities (218 total, 46 thin omitted)

### Community 0 - "index.js"
Cohesion: 0.06
Nodes (74): csrf, addToCart(), applyWalletAutoAmount(), buildPayload(), buildReceipt(), cartSubtotal(), clearSantri(), closeScanner() (+66 more)

### Community 1 - "Carbon\Carbon"
Cohesion: 0.07
Nodes (20): FinancialReportsExport, BalanceSheetSheet, CashFlowSheet, Carbon, ProfitLossSheet, TableExport, Carbon, ReportController (+12 more)

### Community 2 - "dependencies"
Cohesion: 0.04
Nodes (43): axios, chart.js, concurrently, datatables.net, datatables.net-buttons-dt, datatables.net-dt, datatables.net-responsive-dt, flatpickr (+35 more)

### Community 3 - "User"
Cohesion: 0.09
Nodes (8): BelongsTo, User, LocationPolicy, ProductPolicy, Illuminate\Database\Eloquent\Relations\HasOne, Illuminate\Foundation\Auth\User, Illuminate\Notifications\Notifiable, Laravel\Sanctum\HasApiTokens

### Community 4 - ".redirect"
Cohesion: 0.12
Nodes (4): LocationController, WalletManagementController, RedirectResponse, SsoController

### Community 5 - "Controller"
Cohesion: 0.09
Nodes (11): AppSettingManager, DashboardController, SsoSettingController, Controller, DashboardController, MidtransRedirectController, GuardianCategoryController, GuardianSantriController (+3 more)

### Community 6 - "Illuminate\Http\JsonResponse"
Cohesion: 0.15
Nodes (5): LookupController, TransactionController, AnalyticsController, Carbon, Illuminate\Http\JsonResponse

### Community 7 - "Illuminate\Http\Request"
Cohesion: 0.18
Nodes (5): ActivityLogController, SantriController, UserController, exportType(), Illuminate\Http\Request

### Community 8 - "Illuminate\Database\Eloquent\Model"
Cohesion: 0.07
Nodes (7): DailyClosing, GateSyncItem, TransactionItem, Illuminate\Database\Eloquent\Factories\HasFactory, Illuminate\Database\Eloquent\Model, Illuminate\Database\Eloquent\Relations\BelongsTo, Illuminate\Database\Eloquent\Relations\MorphTo

### Community 9 - "Payment"
Cohesion: 0.10
Nodes (7): checkTransaction(), createCharge(), handleWebhook(), Payment, DokuProviderTest, IpaymuProviderTest, MidtransProviderTest

### Community 10 - "Product"
Cohesion: 0.18
Nodes (4): ProductController, Location, Product, CreatePosTransactionTest

### Community 12 - "Illuminate\View\View"
Cohesion: 0.17
Nodes (4): ReportController, Transaction, Illuminate\Database\Eloquent\Relations\MorphMany, Illuminate\View\View

### Community 13 - "ProductCategory"
Cohesion: 0.11
Nodes (5): CategoryController, CatalogController, WaliPortalController, ProductCategory, ProductCategoryPolicy

### Community 14 - "Illuminate\Http\RedirectResponse"
Cohesion: 0.13
Nodes (7): AccountingSettingController, BrandingSettingController, EmailSettingController, PaymentSettingController, AppSetting, PaymentProviderConfig, Illuminate\Http\RedirectResponse

### Community 15 - "Illuminate\Database\Eloquent\Factories\Factory"
Cohesion: 0.12
Nodes (7): LocationFactory, ProductFactory, SantriFactory, UserFactory, WaliFactory, Illuminate\Database\Eloquent\Factories\Factory, static

### Community 16 - "TestCase"
Cohesion: 0.12
Nodes (9): CreatesApplication, Illuminate\Foundation\Testing\RefreshDatabase, Illuminate\Foundation\Testing\TestCase, AuthLoginTest, GateUserReconciliationTest, GateUserSyncTest, PaymentWebhookControllerTest, SantriPortalTest (+1 more)

### Community 18 - "PaymentService"
Cohesion: 0.13
Nodes (5): PaymentWebhookController, PaymentRedirectController, GuardianPaymentController, PaymentWebhookLog, PaymentService

### Community 20 - "WalletTransaction"
Cohesion: 0.23
Nodes (4): WalletTransaction, WalletService, Carbon\CarbonImmutable, CarbonImmutable

### Community 23 - "MainActivity"
Cohesion: 0.17
Nodes (7): ActivityResultLauncher, MainActivity, SmartBridge, AppCompatActivity, Bundle, ScanOptions, WebView

### Community 26 - "scripts"
Cohesion: 0.13
Nodes (15): scripts, dev, post-create-project-cmd, post-update-cmd, pre-package-uninstall, test, Composer\\Config::disableProcessTimeout, Illuminate\\Foundation\\ComposerScripts::prePackageUninstall (+7 more)

### Community 27 - "MoneyColumnAuditor"
Cohesion: 0.16
Nodes (6): AuditMoneyColumns, PosWalletPreflight, ReconcileWallets, MoneyColumnAuditor, WalletLedgerReconciler, Illuminate\Console\Command

### Community 28 - "DokuProvider.php"
Cohesion: 0.29
Nodes (5): InvalidSignatureException, PaymentProviderException, PaymentProviderHttpException, Exception, Illuminate\Http\Client\Response

### Community 30 - "composer.json"
Cohesion: 0.14
Nodes (13): autoload-dev, psr-4, description, keywords, license, minimum-stability, name, prefer-stable (+5 more)

### Community 33 - "PosService"
Cohesion: 0.16
Nodes (4): InventoryMovement, InventoryService, PosService, Illuminate\Database\QueryException

### Community 34 - "EnsureSessionRole.php"
Cohesion: 0.19
Nodes (6): MediaController, SantriMediaController, EnsureRole, EnsureSessionRole, Closure, Symfony\Component\HttpFoundation\Response

### Community 36 - "SmartDemoSeeder"
Cohesion: 0.26
Nodes (4): DatabaseSeeder, SmartDemoSeeder, Illuminate\Database\Console\Seeds\WithoutModelEvents, Illuminate\Database\Seeder

### Community 37 - "PrinterModule"
Cohesion: 0.18
Nodes (4): PrinterModule, BluetoothAdapter, BluetoothDevice, BluetoothSocket

### Community 40 - "UPDATE.md"
Cohesion: 0.07
Nodes (28): 1. Audit summary, 2. Daftar file, 3. Database changes, 4. Transaction flow final, 5. Test results, 6. Deployment commands, 7. Verification checklist, Accounting (+20 more)

### Community 41 - "What You Must Do When Invoked"
Cohesion: 0.08
Nodes (24): For /graphify add and --watch, For /graphify query, For the commit hook and native CLAUDE.md integration, For --update and --cluster-only, /graphify, Honesty Rules, Interpreter guard for subcommands, Part A - Structural extraction for code files (+16 more)

### Community 42 - "require"
Cohesion: 0.22
Nodes (9): require, barryvdh/laravel-dompdf, endroid/qr-code, laravel/framework, laravel/sanctum, laravel/tinker, maatwebsite/excel, php (+1 more)

### Community 43 - "require-dev"
Cohesion: 0.22
Nodes (9): require-dev, fakerphp/faker, laravel/pail, laravel/pint, laravel/sail, mockery/mockery, nunomaduro/collision, pestphp/pest (+1 more)

### Community 46 - "setup"
Cohesion: 0.25
Nodes (8): post-root-package-install, setup, composer install, npm install, npm run build, @php artisan key:generate, @php artisan migrate --force, @php -r \"file_exists('.env') || copy('.env.example', '.env');\

### Community 47 - "CODEX_GATE_USER_SYNC.md"
Cohesion: 0.09
Nodes (21): 11. Alur dry-run preview, 12. Pemilihan tindakan, 13. Apply synchronization, 15. Update identitas, 16. Suspend dan reactivate, 17. Reporting kembali ke Gate, 18. Foto profil, 19. QR code (+13 more)

### Community 48 - "RuntimeException"
Cohesion: 0.18
Nodes (5): GatePhotoSyncService, GateProvisioningException, PosTransactionException, WalletException, RuntimeException

### Community 49 - "config"
Cohesion: 0.29
Nodes (7): pestphp/pest-plugin, php-http/discovery, config, allow-plugins, optimize-autoloader, preferred-install, sort-packages

### Community 51 - "Rupiah"
Cohesion: 0.16
Nodes (4): WalletTopupController, Rupiah, PHPUnit\Framework\TestCase, RupiahTest

### Community 53 - "psr-4"
Cohesion: 0.40
Nodes (5): autoload, psr-4, App\\, Database\\Factories\\, Database\\Seeders\\

### Community 54 - "Santri"
Cohesion: 0.15
Nodes (5): Santri, SantriPolicy, Illuminate\Database\Eloquent\SoftDeletes, SantriPhotoTest, WalletVisibilityTest

### Community 55 - "GateSyncBatch"
Cohesion: 0.21
Nodes (3): GateUserSyncController, GateSyncBatch, GateUserSyncService

### Community 56 - "extra"
Cohesion: 0.67
Nodes (3): extra, laravel, dont-discover

### Community 57 - "post-autoload-dump"
Cohesion: 0.67
Nodes (3): post-autoload-dump, Illuminate\\Foundation\\ComposerScripts::postAutoloadDump, @php artisan package:discover --ansi

### Community 60 - "README.md"
Cohesion: 0.12
Nodes (16): Alur transaksi POS, Audit operasional, Copyright, Deployment aman, Foto santri dan produk, Instalasi lokal, Konfigurasi environment, Metode pembayaran (+8 more)

### Community 66 - "Audit POS dan Wallet — 20 Juli 2026"
Cohesion: 0.13
Nodes (14): Architecture decisions, Audit findings dan perbaikan, Audit POS dan Wallet — 20 Juli 2026, Authentication mechanism, Current POS flow, Current wallet flow, Database changes, Deployment gate (+6 more)

### Community 168 - "30. Output akhir"
Cohesion: 0.15
Nodes (13): 30. Output akhir, Changed files, Database changes, Domain isolation proof, Graphify verification, Implementation summary, Production prerequisites, Reconciliation proof (+5 more)

### Community 169 - "DESIGN.md"
Cohesion: 0.17
Nodes (11): Border Radius Scale, Decorative Depth, Do, Do's and Don'ts, Don't, Elevation & Depth, Iteration Guide, Known Gaps (+3 more)

### Community 170 - "Gate User Synchronization"
Cohesion: 0.17
Nodes (11): Architecture, Authorization and logs, Commands and testing, Configuration, Database schema, Deployment, rollback, troubleshooting, limitations, Gate User Synchronization, Photos and QR (+3 more)

### Community 171 - "SMART Debugging Playbook"
Cohesion: 0.18
Nodes (10): 1. Referensi Dokumen, 2. Backend Laravel, 3. Frontend, Vite, dan PWA, 4. Database & Seed Data, 5. Autentikasi & Sanctum, 6. Pembayaran & Webhook, 7. POS Offline Queue, 8. Infrastruktur & VPS (+2 more)

### Community 173 - "SMART Blueprint"
Cohesion: 0.20
Nodes (10): 10) Troubleshooting Singkat, 11) Kredit, 1) Gambaran Umum, 2) Peran & Akses, 4) Struktur Kode (Blueprint), 6) API Overview, 7) PWA & Frontend, 8) Konfigurasi & ENV (+2 more)

### Community 175 - "graphify reference: extra exports and benchmark"
Cohesion: 0.22
Nodes (8): graphify reference: extra exports and benchmark, Step 6b - Wiki (only if --wiki flag), Step 7 - Neo4j export (only if --neo4j or --neo4j-push flag), Step 7a - FalkorDB export (only if --falkordb or --falkordb-push flag), Step 7b - SVG export (only if --svg flag), Step 7c - GraphML export (only if --graphml flag), Step 7d - MCP server (only if --mcp flag), Step 8 - Token reduction benchmark (only if total_words > 5000)

### Community 176 - "25. Automated tests"
Cohesion: 0.22
Nodes (9): 25.1 Reconciliation tests, 25.2 Preview tests, 25.3 Apply tests, 25.4 Domain isolation tests, 25.5 Reporting tests, 25.6 Authorization tests, 25.7 Suspended user tests, 25.8 Photo and QR tests (+1 more)

### Community 177 - "9. Delapan kategori reconciliation"
Cohesion: 0.22
Nodes (9): 9.1 matched, 9.2 needs_update, 9.3 missing_in_application, 9.4 access_revoked, 9.5 inactive_in_gate, 9.6 reactivation_required, 9.7 local_only, 9.8 conflict (+1 more)

### Community 182 - "graphify reference: query, path, explain"
Cohesion: 0.33
Nodes (5): For /graphify explain, For /graphify path, graphify reference: query, path, explain, Step 0 — Constrained query expansion (REQUIRED before traversal), Step 1 — Traversal

### Community 183 - "Colors"
Cohesion: 0.33
Nodes (6): Brand & Accent, Brand Gradient, Colors, Hairlines & Borders, Surface, Text

### Community 184 - "Components"
Cohesion: 0.33
Nodes (6): Buttons, Cards & Containers, Components, Footer, Inputs & Forms, Top Navigation

### Community 185 - "SMART Architecture Overview"
Cohesion: 0.33
Nodes (5): Application Layers, Core Modules, Data Model Snapshot, Non-Functional Notes, SMART Architecture Overview

### Community 186 - "3) Alur Aplikasi (User Flow)"
Cohesion: 0.33
Nodes (6): 3.1 Login & Portal, 3.2 POS (Kasir), 3.3 Dompet & Topup, 3.4 Akuntansi Otomatis, 3.5 Portal Santri / Wali, 3) Alur Aplikasi (User Flow)

### Community 187 - "5) Modul Utama"
Cohesion: 0.33
Nodes (6): 5.1 Inventori & POS, 5.2 Dompet Santri, 5.3 Pembayaran, 5.4 Akuntansi, 5.5 Portal, 5) Modul Utama

### Community 188 - "20. UI berdasarkan DESIGN.md"
Cohesion: 0.33
Nodes (6): 20.1 Halaman index, 20.2 Halaman preview, 20.3 Konfirmasi apply, 20.4 Result page, 20.5 Perubahan visual wajib terlihat, 20. UI berdasarkan DESIGN.md

### Community 189 - "3. Query Graphify wajib"
Cohesion: 0.33
Nodes (6): 3.1 Arsitektur identitas SMART, 3.2 Risiko terhadap data finansial, 3.3 Titik integrasi Gate, 3.4 UI admin aktual, 3.5 User creation flow, 3. Query Graphify wajib

### Community 190 - "5. Prinsip integrasi wajib"
Cohesion: 0.33
Nodes (6): 5.1 Gate sebagai identity source of truth, 5.2 Dilarang auto-merge, 5.3 Pull-based, 5.4 Password isolation, 5.5 Non-destructive revocation, 5. Prinsip integrasi wajib

### Community 193 - "Responsive Behavior"
Cohesion: 0.40
Nodes (5): Breakpoints, Collapsing Strategy, Image Behavior, Responsive Behavior, Touch Targets

### Community 194 - "Typography"
Cohesion: 0.40
Nodes (5): Font Family, Hierarchy, Note on Font Substitutes, Principles, Typography

### Community 195 - "8. Service architecture"
Cohesion: 0.40
Nodes (5): 8.1 GateProvisioningClient, 8.2 GateUserReconciliationService, 8.3 GateUserSyncService, 8.4 GatePhotoSyncService, 8. Service architecture

### Community 196 - "SMART Android Wrapper"
Cohesion: 0.50
Nodes (3): Cara pakai, Catatan, SMART Android Wrapper

### Community 198 - "graphify reference: add a URL and watch a folder"
Cohesion: 0.50
Nodes (3): For /graphify add, For --watch, graphify reference: add a URL and watch a folder

### Community 199 - "graphify reference: commit hook and native CLAUDE.md integration"
Cohesion: 0.50
Nodes (3): For git commit hook, For native CLAUDE.md integration, graphify reference: commit hook and native CLAUDE.md integration

### Community 200 - "graphify reference: incremental update and cluster-only"
Cohesion: 0.50
Nodes (3): For --cluster-only, For --update (incremental re-extraction), graphify reference: incremental update and cluster-only

### Community 201 - "Layout"
Cohesion: 0.50
Nodes (4): Grid & Container, Layout, Spacing System, Whitespace Philosophy

### Community 202 - "10. Algoritma reconciliation"
Cohesion: 0.50
Nodes (4): 10.1 Index lokal, 10.2 Prioritas pencocokan, 10.3 Field diff, 10. Algoritma reconciliation

### Community 203 - "14. Membuat user SMART"
Cohesion: 0.50
Nodes (4): 14.1 User santri, 14.2 User wali, 14.3 Role administratif, 14. Membuat user SMART

### Community 207 - "7. Database migration"
Cohesion: 0.67
Nodes (3): 7.1 gate_sync_batches, 7.2 gate_sync_items, 7. Database migration

### Community 208 - "Route utama"
Cohesion: 0.67
Nodes (3): API, Route utama, Web

## Knowledge Gaps
- **337 isolated node(s):** `$schema`, `name`, `type`, `description`, `laravel` (+332 more)
  These have ≤1 connection - possible missing edges or undocumented components.
- **46 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `User` connect `User` to `.redirect`, `Controller`, `Illuminate\Http\JsonResponse`, `Illuminate\Http\Request`, `Illuminate\Database\Eloquent\Model`, `Product`, `UserRole.php`, `ProductCategory`, `TestCase`, `WalletTransaction`, `Illuminate\Database\Eloquent\Relations\HasMany`, `ActivityLog`, `PosService`, `EnsureSessionRole.php`, `Wali`, `SmartDemoSeeder`, `AuthBridgeController`, `WaliController`, `GateUserReconciliationService`, `ProductManagementTest`, `RuntimeException`, `PosAccessTest`, `Transaction.php`, `.handle`, `PosConcurrencyTest`, `Santri`, `GateSyncBatch`, `WalletController`?**
  _High betweenness centrality (0.067) - this node is a cross-community bridge._
- **Why does `Santri` connect `Santri` to `User`, `.redirect`, `Controller`, `Illuminate\Http\JsonResponse`, `Illuminate\Http\Request`, `Illuminate\Database\Eloquent\Model`, `Product`, `UserRole.php`, `Illuminate\View\View`, `TestCase`, `PaymentService`, `WalletTransaction`, `Illuminate\Database\Eloquent\Relations\HasMany`, `.url`, `MoneyColumnAuditor`, `PosService`, `Wali`, `SmartDemoSeeder`, `web.php`, `PosAccessTest`, `Rupiah`, `.handle`, `PosConcurrencyTest`, `GateSyncBatch`, `WalletController`?**
  _High betweenness centrality (0.038) - this node is a cross-community bridge._
- **Why does `Payment` connect `Payment` to `JournalEntry`, `User`, `.redirect`, `Controller`, `Wali`, `web.php`, `Illuminate\Database\Eloquent\Model`, `DokuProvider`, `UserRole.php`, `Illuminate\View\View`, `TestCase`, `.config`, `PaymentService`, `Rupiah`, `MidtransProvider`, `.url`, `DokuProvider.php`, `IpaymuProvider`?**
  _High betweenness centrality (0.032) - this node is a cross-community bridge._
- **Are the 34 inferred relationships involving `User` (e.g. with `.handle()` and `.index()`) actually correct?**
  _`User` has 34 INFERRED edges - model-reasoned connections that need verification._
- **Are the 35 inferred relationships involving `Santri` (e.g. with `.handle()` and `.handle()`) actually correct?**
  _`Santri` has 35 INFERRED edges - model-reasoned connections that need verification._
- **Are the 16 inferred relationships involving `Payment` (e.g. with `.storeTopUp()` and `.__invoke()`) actually correct?**
  _`Payment` has 16 INFERRED edges - model-reasoned connections that need verification._
- **What connects `$schema`, `name`, `type` to the rest of the system?**
  _337 weakly-connected nodes found - possible documentation gaps or missing edges._