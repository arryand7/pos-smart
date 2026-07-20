<h1 align="center">SMART — Sabira Mart Integrated Cashless System</h1>

SMART adalah aplikasi monolit Laravel untuk operasional kantin/pesantren: POS kasir, saldo santri, inventori, payment gateway, akuntansi double-entry, portal wali/santri, dan laporan. Frontend POS menggunakan Blade + vanilla JavaScript yang dibangun dengan Vite dan dapat dipasang sebagai PWA.

Dokumentasi ini menggambarkan implementasi repository aktual per 20 Juli 2026. Detail audit keamanan POS/wallet tersedia di [docs/pos-wallet-audit.md](docs/pos-wallet-audit.md), sedangkan aturan visual berada di [DESIGN.md](DESIGN.md).

## Status implementasi

Fitur yang sudah tersedia:

- POS mobile-first untuk pembayaran saldo santri, tunai, dan gateway.
- Pemilihan lokasi, santri, kategori, produk, barcode/QR, dan keranjang.
- Foto santri pada konfirmasi pembelian untuk mencegah penyalahgunaan kartu.
- Foto produk dan santri dioptimalkan otomatis tanpa merusak proporsi.
- Validasi saldo, limit harian/mingguan/bulanan, kategori, lokasi, dan stok.
- Checkout atomik, server-side pricing, row locking, dan idempotency UUID.
- Immutable wallet ledger, inventory movement, dan jurnal double-entry.
- Pembatalan menggunakan reversal ledger, restock, refund gateway bila tersedia, dan jurnal reversal.
- Riwayat 20 transaksi terbaru pada POS dengan detail read-only dan cetak ulang nota.
- Portal wali/santri, administrasi produk/santri/wallet, laporan, PDF/Excel, SSO, dan activity log.
- Audit read-only untuk nominal, rekonsiliasi wallet, serta production preflight.

> **Status deployment:** kode hardening sudah tersedia, tetapi database tujuan wajib lulus `smart:pos-wallet-preflight`. Database lokal saat dokumentasi ini diperbarui masih memiliki temuan data historis dan belum boleh dijadikan bukti bahwa production siap deploy.

## Stack aktual

| Area | Implementasi |
| --- | --- |
| Backend | PHP 8.2+, Laravel 12 |
| Database production | MySQL/MariaDB dengan row-level locking |
| API authentication | Laravel Sanctum bearer token + abilities |
| Web authentication | Session role bridge; tersedia juga SSO OIDC bridge |
| POS frontend | Blade, vanilla JavaScript, Axios, Vite 7 |
| UI | Tailwind CSS 4 dan CSS komponen pada Blade |
| PWA | Web manifest, service worker `public/sw.js`, cache katalog/shell |
| Queue/cache/session | Driver database secara default; Redis dapat dikonfigurasi |
| Payment | iPaymu, Midtrans, DOKU sesuai capability dan konfigurasi provider |
| Export | Dompdf, Laravel Excel, QR/barcode generator |
| Testing | PHPUnit/Pest melalui `php artisan test` |

## Peran dan akses

| Role | Akses utama |
| --- | --- |
| `super_admin` | Seluruh pengaturan, POS, laporan, pembatalan, payment, SSO, dan audit log |
| `admin` | Master data, produk, santri, wallet, laporan, dan API POS |
| `bendahara` | Dashboard/laporan keuangan dan histori wallet sesuai route |
| `kasir` | Halaman POS dan transaksi pada `users.location_id` yang ditugaskan |
| `wali` | Portal wali, limit/kategori anak, histori dan top-up wallet |
| `santri` | Portal santri dan histori wallet milik sendiri |

API POS membutuhkan ketiganya sekaligus: token Sanctum valid, ability `pos:manage`, dan role `kasir`, `admin`, atau `super_admin`. Kasir biasa hanya dapat bertransaksi dan melihat riwayat pada lokasi tugasnya.

## Alur transaksi POS

Request checkout hanya mengirim identitas transaksi, lokasi, santri, metode pembayaran, dan produk/quantity. Harga serta total dari browser tidak dipercaya.

```json
{
  "client_transaction_id": "7ba3babe-f6b8-44cf-9530-4836d3e60cbd",
  "location_id": 1,
  "santri_id": 10,
  "payment_method": "wallet",
  "items": [
    { "product_id": 4, "quantity": 2 }
  ]
}
```

`PosService::createTransaction()` menjalankan boundary berikut dalam satu `DB::transaction(..., attempts: 5)`:

1. Membuat fingerprint payload dan memeriksa idempotency UUID.
2. Memastikan kasir boleh memakai lokasi.
3. Mengunci santri dan memvalidasi status/wallet.
4. Mengunci produk dengan urutan ID deterministik.
5. Memvalidasi lokasi, kategori, status produk, dan stok.
6. Membaca harga aktual dan menghitung total di server sebagai integer rupiah.
7. Memvalidasi saldo serta limit wallet atau uang tunai yang diterima.
8. Membuat transaksi dan snapshot `transaction_items`.
9. Mengurangi stok dan membuat `inventory_movements`.
10. Mendebit wallet dan membuat ledger bila metode saldo digunakan.
11. Membuat jurnal utama secara sinkron untuk transaksi completed.
12. Commit; pekerjaan nonkritis dapat dilakukan setelah commit.

Kegagalan stok, saldo, limit, ledger, atau jurnal menyebabkan seluruh perubahan utama rollback.

### Metode pembayaran

- **Saldo santri:** santri wajib dipilih; saldo, limit, lock wallet, dan kategori diperiksa di server. Debit tidak pernah diantrikan ketika offline.
- **Tunai:** `cash_received` harus rupiah bulat dan minimal sebesar total; server menghitung pembayaran serta kembalian.
- **Gateway:** transaksi dibuat `pending`, lalu provider dengan capability `pos_checkout` diinisialisasi. Status final disinkronkan melalui webhook/refresh provider.

Semua checkout saat ini memerlukan koneksi server. Ketika offline, katalog cache dan keranjang tetap dapat digunakan, tetapi tombol bayar dinonaktifkan dan tidak ada debit wallet yang disimpan untuk sinkronisasi.

## Wallet dan nominal rupiah

- `santris.wallet_balance` adalah cached balance; sumber audit mutasi berada pada `wallet_transactions`.
- Debit/kredit mengunci baris santri dengan `lockForUpdate()`.
- Ledger menyimpan UUID, idempotency key, saldo sebelum/sesudah, actor, reference, dan reversal reference.
- Model ledger menolak update dan delete; koreksi dilakukan melalui entry baru/reversal.
- Limit menggunakan kalender `SMART_WALLET_LIMIT_TIMEZONE`, default `Asia/Jakarta`.
- Nilai limit `0` memakai kebijakan default sistem. Default mingguan adalah Rp200.000; default harian dan bulanan adalah tanpa batas (`0`).
- Input harga, saldo, limit, top-up, adjustment, payment, refund, dan transaksi harus rupiah bulat. Float/pecahan ditolak pada boundary write.
- Skema historis masih memakai `decimal(12,2)` untuk kompatibilitas, tetapi aplikasi hanya menerima nilai dengan pecahan `.00`.

## Foto santri dan produk

Upload diproses oleh `App\Support\ImageOptimizer` dan membutuhkan ekstensi PHP GD.

| Jenis | Hasil | Batas hasil | Perilaku |
| --- | --- | --- | --- |
| Santri | JPEG 360×480 | 200 KB | Orientasi EXIF diperbaiki, gambar di-contain pada kanvas tanpa distorsi |
| Produk | JPEG 640×640 | 300 KB | Gambar di-contain pada kanvas persegi tanpa distorsi |

Foto santri tampil pada konfirmasi POS bersama nama dan NIS. File lama baru dihapus setelah update database berhasil agar kegagalan penyimpanan tidak menghilangkan foto sebelumnya.

## Riwayat dan nota POS

Tautan **Lihat riwayat transaksi** berada di bawah keranjang, di antara ringkasan transaksi terbaru dan tombol cetak. Modal mengambil 20 transaksi terbaru dari backend aktual dan hanya menyediakan:

- lihat reference, waktu, santri, kasir, metode, status, item, dan total;
- cetak ulang nota transaksi sebelumnya;
- state loading, kosong, error, dan offline.

Tidak ada aksi edit atau hapus pada UI ini. Riwayat kasir dibatasi ke lokasi tugas oleh server, bukan hanya oleh filter frontend.

## Struktur domain penting

| Domain | File/tabel utama |
| --- | --- |
| POS | `PosService`, `TransactionController`, `transactions`, `transaction_items` |
| Wallet | `WalletService`, `santris.wallet_balance`, `wallet_transactions` |
| Inventori | `products.stock`, `inventory_movements` |
| Akuntansi | `AccountingService`, `accounts`, `journal_entries`, `journal_lines` |
| Payment | `PaymentManager`, `PaymentService`, `payments`, `payment_webhook_logs` |
| POS UI | `resources/views/pos.blade.php`, `resources/js/pos/index.js` |
| PWA | `public/sw.js`, `resources/js/pwa/registerServiceWorker.js` |
| Audit | `MoneyColumnAuditor`, `WalletLedgerReconciler`, command `smart:*` |

## Instalasi lokal

Prasyarat:

- PHP 8.2+ dengan ekstensi umum Laravel dan GD;
- Composer 2;
- Node.js 18+ dan npm;
- MySQL/MariaDB untuk perilaku locking yang sama dengan production.

```bash
cp .env.example .env
composer install
npm install
php artisan key:generate
php artisan migrate
npm run build
php artisan serve
```

Atur database kosong/non-production pada `.env` sebelum menjalankan migration. Jangan menjalankan `migrate:fresh`, `migrate:reset`, atau `db:wipe` pada database berisi data.

Seeder demo opsional:

```bash
php artisan db:seed --class=SmartDemoSeeder
```

Seeder hanya untuk development/test. Periksa implementasi seeder sebelum memakai kredensial demo dan jangan menjalankannya pada production.

Untuk development dengan server, queue listener, log, dan Vite sekaligus:

```bash
composer run dev
```

## Konfigurasi environment

Variabel penting tersedia pada `.env.example`:

```dotenv
APP_NAME=SMART
APP_ENV=production
APP_DEBUG=false
APP_URL=https://smart.example.sch.id

DB_CONNECTION=mysql
SESSION_DRIVER=database
QUEUE_CONNECTION=database
CACHE_STORE=database

SMART_WALLET_OFFLINE_ENABLED=false
SMART_WALLET_LIMIT_TIMEZONE=Asia/Jakarta
SMART_WALLET_DEFAULT_DAILY_LIMIT=0
SMART_WALLET_DEFAULT_WEEKLY_LIMIT=200000
SMART_WALLET_DEFAULT_MONTHLY_LIMIT=0

SMART_ACCOUNT_CASH=101
SMART_ACCOUNT_WALLET_LIABILITY=202
SMART_ACCOUNT_REVENUE=401
SMART_ACCOUNT_INVENTORY=103
SMART_ACCOUNT_COGS=501
```

Kredensial `IPAYMU_*`, `MIDTRANS_*`, `DOKU_*`, mail, database, dan SSO tidak boleh dicatat di log atau di-commit. Provider juga dapat diaktifkan/diprioritaskan melalui pengaturan super admin.

## Route utama

### Web

| Path | Kegunaan |
| --- | --- |
| `/login`, `/logout` | Session/token bridge kasir |
| `/pos` | POS untuk kasir/super admin |
| `/portal/wali` | Portal wali |
| `/portal/santri` | Portal santri |
| `/admin/dashboard` | Dashboard admin/super admin |
| `/admin/wallets` | Administrasi dan histori wallet |
| `/admin/reports` | Laporan transaksi, receipt, invoice, PDF |
| `/dashboard/bendahara` | Dashboard bendahara |

### API

| Metode | Endpoint | Kegunaan |
| --- | --- | --- |
| `POST` | `/api/auth/login` | Login dan penerbitan token Sanctum |
| `POST` | `/api/auth/logout` | Mencabut current access token |
| `GET` | `/api/pos/locations` | Lokasi yang boleh digunakan |
| `GET` | `/api/pos/categories` | Kategori produk |
| `GET` | `/api/pos/products` | Produk aktual per lokasi |
| `GET` | `/api/pos/santris` | Pencarian santri/QR dan foto verifikasi |
| `GET` | `/api/pos/transactions` | Riwayat read-only transaksi POS |
| `POST` | `/api/pos/transactions` | Checkout POS atomik |
| `POST` | `/api/pos/transactions/offline-sync` | Endpoint compatibility; menolak sinkronisasi debit wallet |
| `POST` | `/api/wallets/{santri}/top-up` | Top-up wallet tunai/gateway sesuai payload |
| `GET` | `/api/wallets/{santri}/transactions` | Histori wallet terotorisasi |
| `POST` | `/api/payments/webhook/{provider}` | Webhook provider pembayaran |

## Audit operasional

Semua command berikut read-only dan aman dijalankan untuk pemeriksaan:

```bash
php artisan smart:audit-money-columns
php artisan smart:reconcile-wallets
php artisan smart:reconcile-wallets --santri=10
php artisan smart:pos-wallet-preflight
```

- `smart:audit-money-columns`: mendeteksi nominal negatif yang tidak diizinkan, pecahan, atau format invalid pada seluruh tabel uang. `daily_closings.cash_variance` boleh negatif tetapi tetap harus rupiah bulat.
- `smart:reconcile-wallets`: membandingkan cached balance dengan rantai ledger tanpa mengubah data.
- `smart:pos-wallet-preflight`: memeriksa migration hardening, idempotency, ledger, stok, jurnal, assignment lokasi, dan nominal.

Exit code non-zero adalah blocker. Jangan memperbaiki mismatch wallet atau jurnal dengan update massal tanpa audit trail dan persetujuan pemilik data.

## Testing

```bash
php artisan test
npm run build
./vendor/bin/pint --test
```

`package.json` saat ini hanya menyediakan script `dev` dan `build`; belum ada `npm run lint`.

Coverage penting mencakup:

- checkout wallet/tunai/gateway;
- saldo, immutable ledger, stok, movement, jurnal, rollback, dan idempotency;
- saldo/limit/kategori/stok tidak cukup;
- server-side pricing dan penolakan nominal pecahan;
- authorization role/ability/lokasi;
- foto santri/produk dan optimasi dimensi;
- riwayat POS read-only dan pembatasan lokasi;
- offline wallet ditolak;
- concurrency wallet/stok terakhir.

Concurrency test sengaja skip pada SQLite. Jalankan pada database MySQL/MariaDB test yang benar-benar terisolasi agar `lockForUpdate()` diuji. Jangan arahkan test concurrency ke production.

Validasi terakhir untuk perubahan POS/riwayat: **19 test lulus, 140 assertions**; build Vite berhasil. Full suite terakhir masih memiliki empat assertion lama pada teks UI/redirect portal yang perlu diselaraskan terpisah, sehingga jangan menyatakan seluruh suite hijau sampai `php artisan test` benar-benar exit 0.

## Deployment aman

Gunakan prosedur operasional production dan backup database/storage yang terverifikasi. Contoh urutan:

```bash
php artisan smart:pos-wallet-preflight
php artisan down --render="errors::503"

composer install --no-dev --prefer-dist --optimize-autoloader
npm ci
npm run build
php artisan migrate --force

php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

php artisan smart:audit-money-columns
php artisan smart:reconcile-wallets
php artisan smart:pos-wallet-preflight
php artisan queue:restart
php artisan up
```

Jangan menjalankan `php artisan up` bila audit/preflight setelah migration gagal. Financial writes utama tidak bergantung pada queue; queue hanya untuk pekerjaan nonkritis setelah commit.

Rollback deployment harus memakai backup dan prosedur perubahan aplikasi yang terkontrol. Migration `down()` tersedia, tetapi rollback skema hardening dapat menghilangkan constraint/kolom audit sehingga tidak boleh dilakukan sembarangan pada production.

## Risiko yang masih terbuka

- Bearer token POS masih disimpan di `localStorage`; XSS dapat mengekspos token long-lived. Target lanjutan adalah session Sanctum stateful HttpOnly/SameSite dengan CSRF dan expiry/rotation.
- Skema nominal fisik masih `decimal(12,2)` untuk kompatibilitas; integer rupiah ditegakkan pada application boundary dan audit.
- Data historis harus direkonsiliasi sebelum deployment hardening dinyatakan selesai.
- Bundle frontend utama melebihi rekomendasi Vite 500 KB; ini peringatan performa, bukan kegagalan build, dan dapat ditangani dengan code splitting terpisah.
- Beberapa dokumen lama (`docs/architecture.md`, `docs/blueprint.md`, `docs/debugging.md`) masih mengandung gambaran scaffold/queue offline lama. Untuk POS/wallet, gunakan README ini dan `docs/pos-wallet-audit.md` sebagai referensi operasional terbaru.

## Copyright

SMART dibuat dan dikembangkan oleh **Ryand Arifriantoni** (`arryand7@gmail.com`).

© 2026 Ryand Arifriantoni. Semua hak cipta dilindungi.
