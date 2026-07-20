# Audit POS dan Wallet — 20 Juli 2026

## Current POS flow

POS memakai Blade + vanilla JavaScript/Vite. Client mengambil lokasi, kategori, produk, dan santri melalui API Sanctum lalu mengirim checkout ke `POST /api/pos/transactions`. Sebelum hardening, payload dapat menentukan harga, diskon, total pembayaran, cash/wallet/gateway, status, waktu proses, dan dapat dimasukkan ke antrean offline.

## Current wallet flow

Saldo tersimpan pada `santris.wallet_balance` dan mutasi pada `wallet_transactions`. `WalletService` sudah mengunci santri pada debit/kredit dalam perubahan worktree awal, tetapi nominal masih bertipe decimal/float dan ledger belum memiliki UUID/idempotency/reversal reference. Limit berasal dari ledger debit completed.

## Frontend architecture

Laravel Blade (`resources/views/pos.blade.php`) dengan DOM API (`resources/js/pos/index.js`), Axios, localStorage cache, PWA service worker, dan sebelumnya localStorage offline transaction queue. Tidak memakai Vue/Inertia sebagaimana dokumentasi arsitektur lama.

## Authentication mechanism

Sanctum bearer token untuk API dan session-role bridge untuk halaman `/pos`. Middleware API mengizinkan kasir/admin/super_admin. Penugasan lokasi kasir belum memiliki relasi/kolom kanonik sehingga pembatasan lokasi per kasir belum dapat ditegakkan; produk tetap divalidasi terhadap lokasi request.

## Financial transaction boundary

Boundary final berada pada `PosService::createTransaction`: idempotency lookup, lock santri, lock produk terurut, validasi status/saldo/limit/kategori/lokasi/stok, server price, transaksi dan item, inventory movement, wallet debit/ledger, serta jurnal sinkron berada dalam satu `DB::transaction(..., attempts: 5)`.

## Critical findings and fixes

| Severity | Temuan | Dampak | Perbaikan |
|---|---|---|---|
| Critical | Harga dan payment breakdown dipercaya dari client | Manipulasi nilai finansial | Checkout wallet-only dan harga dihitung dari produk terkunci |
| Critical | Produk tidak di-lock | Overselling stok terakhir | `lockForUpdate()` dengan urutan ID deterministik |
| Critical | Tidak ada idempotency database | Debit/stok ganda | UUID client + unique constraints pada transaksi dan ledger |
| Critical | Wallet checkout dapat diantre offline | Double spending lintas kasir | Checkout offline dan sync ditolak; katalog/keranjang tetap tersedia |
| High | Jurnal boleh tidak terbentuk | Transaksi completed tanpa jurnal | Kegagalan konfigurasi jurnal me-rollback transaksi |
| High | Produk lintas lokasi tidak ditolak service | Stok lokasi salah | Validasi lokasi server-side |
| High | App boot membaca tabel setting sebelum migration | Test/deploy awal gagal boot | Guard `Schema::hasTable()` |
| Medium | Decimal/float menjadi skema historis | Risiko pembulatan | Boundary POS membulatkan ke integer rupiah; konversi kolom fisik ditunda karena perlu audit data production |

## Architecture drift

- `SMART_POS_WALLET.md` tidak tersedia; `UPDATE.md` menjadi spesifikasi POS/wallet yang tersedia.
- Frontend aktual vanilla JS, bukan Inertia/Vue.
- Stok berada langsung pada `products`, bukan tabel stok per lokasi; aman karena setiap produk dimiliki satu lokasi.
- User belum memiliki assignment lokasi formal.
- Skema uang production masih `decimal(12,2)`; perubahan langsung ke integer berisiko tanpa pemeriksaan pecahan data.

## Files involved and implementation order

Route/controller → transaction service → wallet/accounting service → migration constraints → POS JavaScript/Blade → feature tests → build/full regression. Perubahan dibuat incremental di atas worktree pengguna yang sudah kotor.

## Verification update

- Idempotency race sekarang menangani unique-constraint violation hanya untuk SQLSTATE unique (`23505`, atau `23000` dengan vendor code MySQL `1062`/SQLite unique), lalu memastikan record dengan `client_transaction_id` yang sama benar-benar tersedia. Error database lain tetap dilempar.
- Payload canonical disimpan sebagai SHA-256 fingerprint; UUID yang digunakan ulang dengan payload berbeda ditolak `IDEMPOTENCY_KEY_REUSED`.
- Kalkulasi boundary POS/wallet/accounting memakai `Rupiah` dan menolak float serta decimal dengan pecahan non-zero.
- Limit harian/mingguan/bulanan dihitung dengan batas kalender `Asia/Jakarta`, dikonversi ke timezone penyimpanan, dan memakai current/locking read setelah santri dikunci.
- Kasir memakai assignment kanonik `users.location_id`; nilai nullable memerlukan backfill eksplisit dan checkout tanpa assignment ditolak.
- Ledger dilindungi dari update/delete melalui model event. Pembatalan membuat credit reversal dengan `reversed_transaction_id`, bukan mengubah debit lama.
- API POS juga memerlukan Sanctum ability `pos:manage` selain role.
- Command read-only tersedia: `smart:audit-money-columns`, `smart:reconcile-wallets`, dan `smart:pos-wallet-preflight`.

## Authentication risk

Bearer token POS masih disimpan pada `localStorage` dan belum memiliki expiry eksplisit ketika diterbitkan. Logout mencabut current token server-side, token tidak ditempatkan pada query string, dan API POS memerlukan ability `pos:manage`. Risiko XSS terhadap long-lived bearer token tetap **High**. Rencana lanjutan adalah migrasi first-party POS ke Sanctum stateful HttpOnly/SameSite cookie disertai CSRF protection dan expiry/session rotation.

## Local preflight evidence

Preflight read-only pada database lokal 20 Juli 2026 keluar dengan status gagal dan **60 critical records/findings**: tiga migration hardening belum diterapkan, 50 cached wallet/ledger tidak konsisten, satu kasir belum memiliki assignment lokasi, dan enam jurnal tidak seimbang. Audit nominal lulus tanpa pecahan non-zero. Database lokal tidak diubah. Kondisi ini merupakan deployment blocker sampai ditinjau dan direkonsiliasi dengan audit trail.
