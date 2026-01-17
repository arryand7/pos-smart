# SMART Blueprint

Dokumen ini merangkum alur aplikasi, struktur kode, dan blueprint teknis untuk SMART (Sabira Mart Integrated Cashless System).

## 1) Gambaran Umum
SMART adalah sistem POS + dompet santri + pembayaran + akuntansi untuk ekosistem pesantren. Aplikasi ini berjalan sebagai monolit Laravel 12 dengan UI Blade, POS PWA (Vite + JS), dan API (Sanctum).

**Tujuan utama:**
- Transaksi kasir cepat (tunai/saldo/gateway).
- Kontrol uang saku santri (limit/whitelist).
- Laporan keuangan dan jurnal otomatis.
- Portal wali & santri untuk monitoring.

## 2) Peran & Akses
- **super_admin**: akses penuh, termasuk pengaturan SSO, Payment Gateway, SMTP, dan Activity Log.
- **admin**: manajemen data, laporan, branding, akuntansi, POS.
- **bendahara**: laporan keuangan + akuntansi + inventaris (terbatas).
- **kasir**: POS & transaksi harian.
- **wali**: portal wali (monitor dan top-up).
- **santri**: portal santri (mutasi, histori).

## 3) Alur Aplikasi (User Flow)
### 3.1 Login & Portal
1. User login melalui `/login`.
2. Sistem menentukan peran dan menampilkan portal yang sesuai.
3. Super admin dapat membuka portal lain lewat **Portal Switcher**.

### 3.2 POS (Kasir)
1. Pilih lokasi → fetch kategori & produk.
2. Cari produk (nama/SKU/barcode) → klik kartu produk.
3. Tentukan santri (opsional) via pencarian.
4. Input pembayaran (tunai/saldo/gateway).
5. Submit transaksi → stok berkurang → wallet mutasi → jurnal otomatis.
6. Jika offline, transaksi disimpan di antrean lokal dan disinkronkan saat online.

### 3.3 Dompet & Topup
1. Wali/kasir memilih santri.
2. Masukkan nominal topup.
3. Sistem menambah saldo dan mencatat `wallet_transactions`.

### 3.4 Akuntansi Otomatis
- Penjualan POS menghasilkan jurnal double-entry:
  - Debit: Kas/Gateway/Wallet
  - Kredit: Pendapatan
- Topup menghasilkan jurnal:
  - Debit: Kas
  - Kredit: Liabilitas Wallet

### 3.5 Portal Santri / Wali
- Santri melihat saldo, limit harian/bulanan, dan histori transaksi.
- Wali bisa topup dan mengatur kategori belanja.

## 4) Struktur Kode (Blueprint)
```
app/
  Http/
    Controllers/
      Admin/           # CRUD admin & settings
      Api/             # API auth + POS
      Portal/          # Portal wali & santri
  Models/              # Eloquent models
  Services/
    POS/               # PosService, offline sync
    Accounting/        # AccountingService (jurnal)
    Payments/          # PaymentManager, Payment providers
    Wallets/           # WalletService
resources/
  views/               # Blade templates (admin, portal, pos)
  js/pos/              # POS frontend (Vanilla JS)
  css/                 # Tailwind + custom styles
routes/
  web.php              # Web UI
  api.php              # API + Sanctum
```

## 5) Modul Utama
### 5.1 Inventori & POS
- Tabel: `products`, `inventory_movements`, `transactions`, `transaction_items`.
- POS UI: `resources/views/pos.blade.php` + `resources/js/pos/index.js`.

### 5.2 Dompet Santri
- Tabel: `wallet_transactions`.
- Service: `WalletService` (limit, debit/kredit).

### 5.3 Pembayaran
- Tabel: `payments`, `payment_provider_configs`, `payment_webhook_logs`.
- Provider: iPaymu, Midtrans, DOKU (stub + webhook).

### 5.4 Akuntansi
- Tabel: `accounts`, `journal_entries`, `journal_lines`.
- Service: `AccountingService` (POS & topup).

### 5.5 Portal
- Portal Wali: `Portal/WaliPortalController` + `resources/views/portal/wali.blade.php`.
- Portal Santri: `Portal/SantriPortalController` + `resources/views/portal/santri.blade.php`.

## 6) API Overview
Endpoint utama (guard `auth:sanctum`):
- `POST /api/auth/login`
- `POST /api/auth/logout`
- `GET /api/pos/products`
- `GET /api/pos/categories`
- `GET /api/pos/santris`
- `POST /api/pos/transactions`
- `POST /api/pos/transactions/offline-sync`

## 7) PWA & Frontend
- POS dipaketkan oleh Vite dengan PWA manifest & service worker.
- Offline queue disimpan di localStorage.

## 8) Konfigurasi & ENV
Contoh variabel penting:
- `SMART_DEFAULT_PAYMENT_PROVIDER`
- `IPAYMU_*`, `MIDTRANS_*`, `DOKU_*`
- `APP_URL`, `SANCTUM_STATEFUL_DOMAINS`

## 9) Deployment Checklist
- `php artisan migrate --seed`
- `php artisan storage:link`
- `npm run build`
- Set `.env` payment & mail config.

## 10) Troubleshooting Singkat
- **Login error**: cek user role + `EnsureSessionRole`.
- **POS gagal load**: cek API `/api/pos/*` + token.
- **Gambar produk tidak tampil**: pastikan `storage:link` dan file tersimpan di `storage/app/public/products`.

## 11) Kredit
SMART dibuat dan dikembangkan oleh **Ryand Arifriantoni** (arryand7@gmail.com).

© 2026 Ryand Arifriantoni. Semua hak cipta dilindungi.
