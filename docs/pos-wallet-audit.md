# Audit POS dan Wallet — 20 Juli 2026

Dokumen ini mencatat audit berbasis kode aktual dan keputusan implementasi POS/wallet. README operasional berada di [`README.md`](../README.md).

## Current POS flow

POS menggunakan Blade + vanilla JavaScript/Vite. Browser memuat lokasi, kategori, produk, santri, dan riwayat melalui API Sanctum. Checkout dikirim ke `POST /api/pos/transactions` dengan UUID, lokasi, santri opsional, metode bayar, product ID, dan quantity. Harga, total, status, stok, serta nilai finansial final dihitung server-side.

Metode pembayaran aktual adalah wallet, tunai, dan gateway. Seluruh checkout memerlukan koneksi server. Katalog/keranjang dapat tetap tersedia ketika offline, tetapi transaksi tidak diantrikan. Endpoint offline-sync dipertahankan untuk compatibility dan selalu menolak debit wallet.

POS menampilkan foto santri pada konfirmasi, receipt transaksi terakhir, serta modal riwayat 20 transaksi terbaru dengan aksi read-only dan cetak ulang. Kasir hanya melihat riwayat lokasi tugasnya.

## Current wallet flow

Cached balance berada pada `santris.wallet_balance`; ledger audit berada pada `wallet_transactions`. `WalletService` mengunci santri pada debit/kredit, memvalidasi integer rupiah, saldo, status lock, dan limit kalender harian/mingguan/bulanan. Ledger menyimpan UUID, idempotency key, before/after balance, actor, reference, dan reversal link. Model melarang update/delete; pembatalan menghasilkan credit reversal baru.

Nilai limit `0` dinormalisasi menjadi integer `0`, bukan `NULL`, lalu memakai default sistem. Boundary write produk, limit, top-up, adjustment, payment, refund, POS, wallet, inventory, dan accounting menolak pecahan.

## Frontend architecture

- Blade: `resources/views/pos.blade.php`.
- Vanilla DOM/Axios: `resources/js/pos/index.js`, dimuat dari `resources/js/pos/main.js`.
- Build: Vite 7 + Tailwind CSS 4.
- PWA: `public/sw.js` dan `resources/js/pwa/registerServiceWorker.js`.
- Cache offline hanya untuk shell/katalog; financial checkout tetap online-only.

Frontend aktual bukan Inertia/Vue sebagaimana rencana lama.

## Authentication mechanism

- API memakai Sanctum bearer token, role middleware, dan ability `pos:manage`.
- Halaman `/pos` memakai session-role bridge untuk kasir/super admin.
- Kasir memiliki assignment kanonik `users.location_id`; checkout dan riwayat dibatasi server-side.
- Admin/super admin API dapat memilih lokasi sesuai kewenangan.

## Financial transaction boundary

Boundary final berada pada `PosService::createTransaction()`:

1. Payload fingerprint dan idempotency lookup.
2. Validasi assignment lokasi kasir.
3. Lock santri.
4. Validasi status, wallet lock, saldo, limit, dan kategori.
5. Load/lock produk dengan urutan ID deterministik.
6. Validasi lokasi/status/stok dan server-side pricing.
7. Buat transaction dan immutable item snapshots.
8. Kurangi stok dan buat inventory movements.
9. Debit wallet dan buat ledger jika metode wallet.
10. Buat jurnal primary untuk transaksi completed.
11. Commit.
12. Inisialisasi provider untuk transaksi gateway pending; kegagalan mengaktifkan jalur cancellation/reversal.

Langkah finansial utama berjalan sinkron dalam `DB::transaction(..., attempts: 5)`; queue tidak digunakan untuk debit, stok, transaksi utama, atau jurnal utama.

## Audit findings dan perbaikan

| Severity | Temuan awal | Dampak | Perbaikan aktual |
| --- | --- | --- | --- |
| Critical | Harga/payment breakdown dipercaya dari client | Manipulasi nilai finansial | Request dipersempit; harga dan total dihitung dari produk terkunci |
| Critical | Produk tidak di-lock | Overselling stok terakhir | `lockForUpdate()` terurut |
| Critical | Tidak ada idempotency database | Debit/stok ganda | UUID unique, payload fingerprint, dan race recovery terbatas unique violation |
| Critical | Wallet checkout dapat diantrikan offline | Double spending | Semua checkout online-only; sync debit wallet ditolak |
| Critical | Limit `0` dapat tersimpan sebagai `NULL` | Error `NOT NULL` saat edit santri | Empty/`0` dinormalisasi ke integer `0`; pecahan ditolak |
| High | Jurnal boleh tidak terbentuk | Completed tanpa jurnal | Kegagalan akun/jurnal me-rollback transaksi completed |
| High | Produk lintas lokasi dapat diproses | Stok lokasi salah | Validasi lokasi kasir dan produk server-side |
| High | Ledger dapat diubah/dihapus | Audit trail hilang | Model immutable dan reversal entries |
| High | Foto identitas tidak tampil di checkout | Penyalahgunaan kartu sulit dideteksi | Upload/optimasi foto santri dan tampilan konfirmasi POS |
| Medium | Tidak ada cetak ulang dari POS | Petugas sulit memverifikasi transaksi terakhir | Riwayat read-only per lokasi dan cetak ulang nota |
| Medium | Decimal/float historis | Risiko pembulatan | `Rupiah` integer boundary dan audit seluruh money columns; skema fisik belum dikonversi |

## Architecture decisions

- Mempertahankan monolit Laravel dan frontend vanilla JS; tidak melakukan rewrite framework.
- Mempertahankan `products.stock` karena satu produk dimiliki satu lokasi; tidak menambah tabel stok baru.
- Memulihkan tunai dan gateway untuk compatibility, tetapi wallet tetap mendapat hardening terketat.
- Menjaga `decimal(12,2)` untuk kompatibilitas data, sementara semua write boundary memakai integer rupiah.
- Menggunakan migration baru; migration lama tidak diedit.
- Menyimpan foto sebagai JPEG fixed canvas dengan contain agar dimensi konsisten tanpa distorsi.
- Menyediakan riwayat POS lewat endpoint GET yang sudah ada, memperkaya relation minimal, tanpa menambah mutation endpoint.

## Database changes

Migration baru:

- `2026_07_20_000001_harden_wallet_pos_transactions.php`
  - unique `transactions.client_transaction_id`;
  - UUID/idempotency/reversal pada wallet ledger;
  - index limit lookup;
  - unique journal source + `journal_type`.
- `2026_07_20_000002_assign_cashier_location.php`
  - `users.location_id` dan index role/location.
- `2026_07_20_000003_add_photo_path_to_santris.php`
  - `santris.photo_path` nullable.

Kolom historis nullable pada migration hardening mempertahankan compatibility data lama; preflight mendeteksi row lama yang belum dibackfill.

## UI dan design compliance

- POS mobile-first dengan layout kasir tablet/desktop.
- State initial, loading, empty, validation, saldo/limit/kategori/stok, duplicate, server error, offline, dan success.
- Konfirmasi memuat foto, identitas santri, item, total, metode, saldo, dan lokasi.
- Upload santri menjadi 360×480 maksimal 200 KB; produk 640×640 maksimal 300 KB.
- Riwayat memiliki loading/empty/error/offline, detail read-only, focus return, dan print action.
- Pembayaran dinonaktifkan ketika offline; tidak ada success palsu atau dummy data.

## Verification

Command yang telah dijalankan pada perubahan terbaru:

```bash
php artisan test tests/Feature/Pos/PosAccessTest.php tests/Feature/Pos/CreatePosTransactionTest.php
npm run build
node --check resources/js/pos/index.js
./vendor/bin/pint --test
php artisan smart:audit-money-columns
php artisan smart:reconcile-wallets
php artisan smart:pos-wallet-preflight
```

Hasil targeted POS/riwayat: 19 test lulus (140 assertions). Build Vite berhasil dengan warning bundle utama >500 KB. Concurrency test skip pada SQLite dan harus dijalankan di MySQL/MariaDB test terisolasi.

Full suite terakhir: 63 test lulus, 2 skip, 4 gagal pada assertion UI/redirect lama (`ProductManagementTest`, `SantriPortalTest`, dan dua assertion `WaliPortalTest`). Jangan menyatakan full suite hijau sebelum command exit 0.

## Local preflight evidence terbaru

Pemeriksaan read-only database lokal pada 20 Juli 2026:

- Migration hardening POS/wallet, assignment lokasi, dan foto santri sudah `Ran`.
- Migration `2026_01_18_094000_add_timezone_to_app_settings` masih `Pending`.
- Audit nominal lulus: seluruh nilai yang diperiksa merupakan rupiah bulat valid.
- Wallet reconciliation: 50/50 santri ditandai inconsistent; sebagian cached balance cocok tetapi chain ledger lama tidak valid, sebagian memiliki selisih saldo.
- Production preflight gagal dengan 74 critical findings:
  - 12 completed transaction tanpa client UUID;
  - 5 debit POS lama tanpa UUID/idempotency key;
  - 50 cached wallet/ledger mismatch atau invalid chain;
  - 1 kasir belum memiliki assignment lokasi;
  - 6 journal entry tidak seimbang.

Tidak ada data yang diubah oleh command audit. Temuan ini adalah deployment blocker dan memerlukan rekonsiliasi ber-audit-trail, bukan update massal langsung.

## Remaining risks

- Token POS masih tersimpan di `localStorage` dan belum memiliki expiry eksplisit; risiko XSS terhadap bearer token tetap High.
- Data historis belum direkonsiliasi/backfill.
- Skema fisik uang masih decimal untuk compatibility.
- Concurrency belum tervalidasi pada database lokal yang mendukung row-level locking.
- Dokumen arsitektur lama di `docs/architecture.md`, `docs/blueprint.md`, dan `docs/debugging.md` masih memiliki beberapa drift; dokumen ini dan README menjadi rujukan POS/wallet terbaru.

## Deployment gate

Sebelum membuka maintenance mode kembali:

1. Backup database dan storage terverifikasi.
2. Terapkan migration dengan `php artisan migrate --force`.
3. Pastikan assignment lokasi kasir lengkap.
4. Rekonsiliasi transaksi/ledger/jurnal historis dengan audit trail.
5. Jalankan audit nominal, rekonsiliasi wallet, dan preflight.
6. Jalankan targeted test serta build.
7. Hanya lanjutkan bila preflight dan test yang diwajibkan exit 0.
