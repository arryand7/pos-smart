<h1 align="center">SMART &mdash; Sabira Mart Integrated Cashless System</h1>

SMART adalah platform POS dan keuangan terintegrasi untuk Pondok Pesantren. Repositori ini berisi monolit Laravel 12 yang menjadi fondasi modul POS, dompet santri, gateway pembayaran, serta akuntansi double-entry.

## ✨ Fitur Utama (v1 Scaffold)

- **POS Mobile-first** &mdash; transaksi tunai, saldo santri, atau gateway dalam satu layar dengan dukungan sinkronisasi offline.
- **Dompet Santri** &mdash; kontrol saldo, riwayat mutasi, batas harian, dan whitelist/blokir kategori.
- **Gateway Modular** &mdash; antarmuka `PaymentProvider` dengan implementasi stub iPaymu (default), Midtrans, dan DOKU.
- **Akuntansi Otomatis** &mdash; pencatatan jurnal double-entry untuk penjualan POS dan top-up saldo.
- **Laporan Kasir** &mdash; dukungan closing harian dan persiapan dashboard KPI.

## 🧱 Struktur Domain

| Modul | Ringkasan |
| --- | --- |
| Identity & Role | `users` diberi enum `role` (`admin`, `bendahara`, `kasir`, `santri`, `wali`). Profil `Santri` dan `Wali` disimpan terpisah. |
| Inventori & POS | `products`, `inventory_movements`, `transactions`, `transaction_items` merekam penjualan dan pergerakan stok. |
| Dompet Santri | `wallet_transactions` dengan snapshot saldo sebelum/sesudah dan referensi morphic. |
| Pembayaran | `payments`, `payment_provider_configs`, `payment_webhook_logs` mendukung multi-gateway. |
| Akuntansi | `accounts`, `journal_entries`, `journal_lines` untuk double-entry. |
| Operasional | `daily_closings` mencatat setoran kas kasir per lokasi. |

Rincian arsitektur tersedia pada `docs/architecture.md`.
Blueprint alur aplikasi dan peta kode tersedia pada `docs/blueprint.md`.

## 🚀 Persiapan Lingkungan

Prasyarat:

- PHP 8.2+
- Composer 2+
- Node.js 18+ & npm / pnpm
- MySQL / MariaDB

Langkah instalasi lokal:

```bash
cp .env.example .env               # sesuaikan kredensial DB, driver queue, dll.
# isi parameter gateway (IPAYMU_*, MIDTRANS_*, DOKU_*) jika akan menguji pembayaran
composer install
npm install
php artisan key:generate
php artisan migrate
php artisan serve                   # API + Inertia stack (akan dilanjutkan)
```

Tambahkan `SMART_DEFAULT_PAYMENT_PROVIDER` atau variabel terkait apabila ingin mengatur gateway default dari `.env`.

### Demo Data

Tersedia seeder contoh untuk mengisi pengguna (admin, bendahara, kasir, wali, santri) beserta produk lintas lokasi dan saldo dompet:

```bash
php artisan db:seed --class=SmartDemoSeeder
```

Password default seluruh akun demo: `password`.

## 🔌 Endpoint API (sementara)

| Metode | Endpoint | Deskripsi |
| --- | --- | --- |
| `POST` | `/api/auth/login` | Login email + password untuk memperoleh Bearer token Sanctum. |
| `POST` | `/api/auth/logout` | Cabut token aktif (guard `auth:sanctum`). |
| `GET` | `/api/pos/transactions` | Daftar transaksi POS (role: `kasir`, `admin`). |
| `POST` | `/api/pos/transactions` | Buat transaksi POS baru (role: `kasir`, `admin`). |
| `POST` | `/api/pos/transactions/offline-sync` | Sinkronisasi batch transaksi offline (role: `kasir`, `admin`). |
| `POST` | `/api/wallets/{santri}/top-up` | Top-up saldo (role: `kasir`, `admin`). |
| `GET` | `/api/wallets/{santri}/transactions` | Riwayat mutasi dompet; terbatas ke santri terkait, wali, kasir/bendahara/admin. |
| `POST` | `/api/payments/webhook/{provider}` | Endpoint webhook iPaymu/Midtrans/DOKU. |

Endpoint di atas masih memerlukan guard otentikasi dan validasi lanjutan (TBD).

## 🔐 Autentikasi & Otorisasi

- Laravel Sanctum digunakan sebagai guard API. Jalankan migrasi `personal_access_tokens` baru sebelum menggunakan token.
- `POST /api/auth/login` menerima `email`, `password`, dan opsional `device_name`. Respons mengandung token Bearer dan daftar abilities (per role).
- Token abilities default per role:
  - `admin`: `*`
  - `kasir`: `pos:manage`, `wallet:topup`, `wallet:view`
  - `bendahara`: `reports:view`, `wallet:view`, `journal:view`
  - `wali`: `wallet:view`, `wallet:topup`
  - `santri`: `wallet:view-self`
- Middleware `auth:sanctum` + `role:*` sudah diterapkan di `routes/api.php`. `EnsureRole` otomatis mem-parsing beberapa peran yang dipisah koma.
- `WalletController` membatasi akses santri/wali agar hanya bisa melihat dompet yang relevan (wali harus menjadi wali dari santri tersebut).
- Modul front-end `resources/js/services/authToken.js` menyimpan token di `localStorage`, mengatur header `Authorization`, serta menghapus token saat menerima HTTP 401 untuk memudahkan integrasi PWA.
- Integrasi gateway kini mencakup iPaymu (signature HMAC + request API), Midtrans (Snap + webhook signature), dan DOKU (HMAC SHA256). Siapkan kredensial pada `.env` sebagaimana dicontohkan di `.env.example`.
- Halaman login kasir tersedia di `/login`. Kredensial yang valid akan menghasilkan token Sanctum, disimpan ke localStorage, dan otomatis mengarahkan ke `/pos`. Rute `/logout` mencabut token dan membersihkan sesi.

## 📦 Komponen Service

- `PaymentManager` dan `PaymentService` mengorkestrasi gateway pembayaran.
- `WalletService` menangani debit/kredit saldo beserta batas harian.
- `PosService` menulis transaksi, item, mutasi dompet, serta memicu jurnal otomatis.
- `AccountingService` membuat entri jurnal untuk POS dan top-up.

## ✅ Testing

```bash
php artisan test --testsuite=Feature
```

- Meliputi login/token Sanctum, proteksi POS, visibilitas dompet santri/wali, serta skenario penjualan yang memadukan pembayaran tunai + saldo dan memastikan stok turun melalui `inventory_movements`.
- Tes pembayaran memverifikasi signature iPaymu, Midtrans, dan DOKU beserta pencatatan webhook/log error, sehingga siap untuk integrasi produksi.

## 🛒 POS Frontend (PWA)

- Endpoint PWA: `/pos` (Vite + vanilla JS) dengan antrean offline menggunakan `localStorage`.
- Skrip token di `services/authToken.js` otomatis mengisi header Authorization di axios dan menangani auto-logout saat token invalid (redirect ke `/login`).
- Service worker `public/service-worker.js` melakukan precache shell dan fallback offline; register dilakukan di `resources/js/pwa/registerServiceWorker.js`.
- Logic kasir berada di `resources/js/pos/index.js` menggunakan DOM API, mencakup keranjang, metode bayar, sinkronisasi offline, dan tombol logout.
- Manifest PWA tersedia di `public/manifest.webmanifest` sehingga layar dapat di-install sebagai aplikasi.

## 🗺️ Roadmap Berikutnya

- Integrasi Inertia/Vue untuk kasir dan dashboard bendahara.
- Implementasi penuh API iPaymu/Midtrans/DOKU (signature, webhook validation, retry).
- Penanganan limit harian berbasis akumulasi mutasi + whitelist/blokir kategori produk.
- Laporan finansial (Laba Rugi, Neraca, Arus Kas) dan analitik konsumsi santri.
- Service worker PWA + queue sinkronisasi offline.
- Test otomatis (feature & unit) untuk service inti.

Kontribusi lanjutan bisa dimulai dari modul di atas dengan mengacu pada PRD.
- **Panel Admin:** akses `/admin/dashboard` (role admin) untuk ringkas stok & navigasi. Modul produk/lokasi/kategori memiliki fitur CRUD lengkap dengan filter & pagination.

## © Copyright & Kredit

SMART dibuat dan dikembangkan oleh **Ryand Arifriantoni** (arryand7@gmail.com).
© 2026 Ryand Arifriantoni. Semua hak cipta dilindungi.
