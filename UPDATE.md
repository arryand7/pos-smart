Saya sedang mengembangkan aplikasi **SMART — Sabira Mart Integrated Cashless System** yang sudah berjalan di server production.

SMART dibangun menggunakan **Laravel 12**, Vite, PWA, API Laravel Sanctum, dan frontend POS berbasis JavaScript. Fokus utama aplikasi adalah transaksi pembelian santri menggunakan **saldo wallet santri**. Untuk tahap ini, prioritaskan stabilitas POS dan wallet. Jangan mengembangkan fitur payment gateway, pembayaran tunai, atau modul lain kecuali diperlukan untuk menjaga konsistensi transaksi saldo.

## Tujuan utama

Audit dan perbaiki implementasi POS dan saldo santri agar:

1. Transaksi pembelian hanya menggunakan saldo santri.
2. Saldo tidak dapat digunakan melebihi nilai yang tersedia.
3. Tidak terjadi double spending ketika dua kasir memproses santri yang sama.
4. Request yang terkirim dua kali tidak menghasilkan transaksi ganda.
5. Stok, transaksi POS, mutasi saldo, dan jurnal akuntansi selalu konsisten.
6. Semua perubahan saldo memiliki audit trail.
7. Transaksi yang gagal tidak meninggalkan data setengah jadi.
8. Implementasi aman digunakan pada production.

## Konteks aplikasi

Modul utama yang kemungkinan berkaitan:

```text
app/
  Http/
    Controllers/
      Api/
      Admin/
      Portal/
  Models/
  Services/
    POS/
    Wallets/
    Accounting/
resources/
  views/
  js/pos/
routes/
  web.php
  api.php
database/
  migrations/
  seeders/
tests/
```

Tabel yang kemungkinan digunakan:

```text
users
santris
products
product_categories
locations
transactions
transaction_items
wallet_transactions
inventory_movements
accounts
journal_entries
journal_lines
activity_logs
```

Jangan menganggap dokumentasi selalu sesuai dengan implementasi. Periksa kode aktual dan jadikan repository sebagai sumber utama. Catat setiap perbedaan antara dokumentasi dan kode.

---

# Tahap 1 — Audit implementasi aktual

Sebelum mengubah kode, telusuri dan jelaskan:

1. Route yang digunakan oleh POS.
2. Controller yang menerima transaksi POS.
3. Service yang menangani transaksi.
4. Model dan migration yang berkaitan.
5. Cara saldo santri disimpan.
6. Cara `wallet_transactions` dicatat.
7. Cara stok dikurangi.
8. Cara jurnal akuntansi dibuat.
9. Cara frontend mengirim transaksi.
10. Mekanisme offline queue dan retry.
11. Mekanisme autentikasi dan otorisasi kasir.
12. Test yang sudah tersedia.

Cari risiko berikut:

* Saldo dibaca dan diperbarui tanpa `lockForUpdate()`.
* Transaksi tidak dibungkus dalam `DB::transaction()`.
* Harga dan total dipercaya dari frontend.
* Saldo dapat diubah langsung tanpa ledger.
* Transaksi dapat dibuat dua kali ketika request diulang.
* Stok dikurangi sebelum transaksi dipastikan berhasil.
* Wallet berhasil didebit tetapi jurnal atau stok gagal.
* Event atau queued listener menyebabkan data finansial tertunda.
* Wallet dapat digunakan ketika POS offline.
* Saldo negatif.
* Mutasi saldo dapat diedit atau dihapus.
* Route POS dapat diakses role yang tidak berwenang.
* Kasir dapat memproses transaksi dari lokasi lain.
* Snapshot harga produk tidak disimpan di `transaction_items`.

Setelah audit, tampilkan ringkasan singkat:

```text
Current flow
Critical findings
Files involved
Database risks
Recommended implementation order
```

Setelah itu lanjutkan implementasi tanpa menunggu konfirmasi, selama perubahan masih berada dalam lingkup POS dan wallet.

---

# Tahap 2 — Implementasi transaksi POS berbasis wallet

Buat satu application service atau action utama sebagai pintu masuk transaksi, misalnya:

```text
CreateWalletPosTransaction
```

atau gunakan service yang sudah ada jika strukturnya sudah baik.

Semua proses berikut harus berjalan dalam satu database transaction:

```text
1. Validasi idempotency key.
2. Lock data santri atau wallet.
3. Validasi saldo.
4. Validasi limit belanja.
5. Validasi kategori yang diizinkan.
6. Lock stok produk.
7. Ambil harga aktual dari database.
8. Hitung ulang total di server.
9. Buat transaksi.
10. Buat transaction items.
11. Kurangi stok.
12. Buat inventory movements.
13. Debit wallet.
14. Buat wallet transaction.
15. Buat jurnal akuntansi.
16. Commit.
```

Gunakan:

```php
DB::transaction(function () {
    // seluruh transaksi finansial
}, attempts: 5);
```

Gunakan `lockForUpdate()` untuk saldo dan stok yang sedang dipakai.

Jangan menjalankan proses finansial utama melalui queued listener. Queue hanya boleh digunakan setelah commit untuk:

* Notifikasi.
* Analytics.
* Export.
* Email.
* Pekerjaan nonkritis.

---

# Aturan request POS

Frontend tidak boleh menentukan nilai finansial final.

Request ideal:

```json
{
  "client_transaction_id": "UUID",
  "santri_id": 123,
  "location_id": 2,
  "items": [
    {
      "product_id": 10,
      "quantity": 2
    },
    {
      "product_id": 18,
      "quantity": 1
    }
  ]
}
```

Server harus mengambil sendiri:

* Harga produk.
* Kategori produk.
* Stok.
* Lokasi produk.
* Limit santri.
* Saldo santri.
* Total transaksi.
* Akun jurnal.

Jangan menerima atau mempercayai:

```text
unit_price
subtotal
total
wallet_balance
balance_after
discount
```

dari frontend, kecuali nilai tersebut hanya digunakan sebagai informasi dan tetap dihitung ulang di server.

---

# Idempotency

Setiap transaksi POS harus memiliki:

```text
client_transaction_id UUID
```

Tambahkan unique constraint pada database.

Jika request dengan `client_transaction_id` yang sama dikirim ulang:

* Jangan membuat transaksi baru.
* Kembalikan transaksi yang sudah dibuat.
* Response harus tetap sukses dan konsisten.
* Jangan kembali mengurangi saldo atau stok.

Pastikan pengecekan idempotency juga aman terhadap dua request yang masuk hampir bersamaan.

---

# Wallet sebagai immutable ledger

Jadikan `wallet_transactions` sebagai ledger yang tidak boleh diedit atau dihapus melalui proses normal.

Minimal field yang diperlukan:

```text
id
uuid
santri_id atau wallet_id
type: credit/debit
amount
balance_before
balance_after
reference_type
reference_id
idempotency_key
description
actor_id
created_at
reversed_transaction_id nullable
```

Aturan wallet:

1. Nominal disimpan sebagai integer rupiah, bukan floating point.
2. Saldo tidak boleh negatif.
3. Perubahan saldo hanya melalui `WalletService`.
4. Manual adjustment harus menghasilkan ledger transaction.
5. Koreksi transaksi dilakukan melalui reversal, bukan update atau delete.
6. `balance_before` dan `balance_after` harus konsisten.
7. Bila `santris.wallet_balance` tetap digunakan, perlakukan sebagai cached balance.
8. Cached balance dan ledger harus diperbarui dalam database transaction yang sama.

Tambahkan command rekonsiliasi, misalnya:

```bash
php artisan smart:reconcile-wallets
```

Command tersebut harus:

* Membandingkan saldo tersimpan dengan ledger.
* Menampilkan santri yang tidak konsisten.
* Tidak otomatis mengubah saldo tanpa flag eksplisit.
* Mendukung mode laporan aman untuk production.

---

# Aturan limit dan kategori

Implementasikan atau audit aturan berikut:

```text
daily_limit
monthly_limit jika tersedia
category whitelist
category blacklist
account status
wallet active status
```

Validasi dilakukan di server pada saat transaksi diproses.

Urutan aturan kategori:

1. Produk harus aktif.
2. Produk harus tersedia di lokasi kasir.
3. Jika santri memiliki blacklist, kategori tersebut ditolak.
4. Jika whitelist aktif, hanya kategori yang tercantum yang diizinkan.
5. Pesan error harus menyebut item atau kategori yang ditolak.

Hitung penggunaan limit hanya dari transaksi yang valid atau completed. Transaksi cancelled, failed, void, dan reversed tidak boleh ikut dihitung.

---

# Inventory

Pastikan stok tidak menjadi negatif.

Jika aplikasi memiliki stok per lokasi, gunakan data stok berdasarkan:

```text
product_id + location_id
```

Bila stok masih berada langsung pada tabel `products`, jangan melakukan refactor besar tanpa kebutuhan. Namun, dokumentasikan keterbatasannya dan buat implementasi transaksi tetap aman.

Setiap penjualan harus menghasilkan `inventory_movements` bertipe `sale`.

Simpan snapshot pada `transaction_items`:

```text
product_id
product_name
sku
quantity
unit_price
unit_cost jika tersedia
subtotal
```

Perubahan harga produk setelah transaksi tidak boleh mengubah histori transaksi lama.

---

# Accounting

Transaksi wallet harus menghasilkan jurnal berikut:

```text
Debit  Liabilitas Wallet Santri
Kredit Pendapatan Penjualan
```

Jika persediaan dan HPP sudah diterapkan:

```text
Debit  Harga Pokok Penjualan
Kredit Persediaan
```

Pastikan:

```text
total debit = total kredit
```

Tambahkan unique constraint atau idempotency untuk journal source:

```text
source_type
source_id
journal_type
```

Satu transaksi POS tidak boleh menghasilkan jurnal yang sama dua kali.

Jurnal transaksi utama harus dibuat secara sinkron dalam database transaction yang sama. Analytics dan notifikasi boleh diproses setelah commit.

---

# Status transaksi

Gunakan status yang jelas, misalnya:

```text
pending
completed
failed
cancelled
voided
reversed
```

Untuk transaksi wallet biasa, usahakan transaksi langsung menjadi `completed` ketika seluruh proses database berhasil.

Jangan menyimpan transaksi `completed` jika:

* Saldo gagal didebit.
* Stok gagal dikurangi.
* Wallet transaction gagal dibuat.
* Journal entry gagal dibuat.

Jika salah satu gagal, seluruh perubahan harus rollback.

---

# Offline POS

Karena transaksi menggunakan saldo santri, jangan izinkan transaksi wallet ketika client offline.

Ketika offline:

* POS boleh menampilkan katalog cache.
* Keranjang boleh tetap digunakan.
* Tombol pembayaran wallet harus dinonaktifkan.
* Tampilkan informasi bahwa transaksi saldo membutuhkan koneksi server.
* Jangan mengantrekan debit wallet di localStorage atau IndexedDB.

Alasannya adalah mencegah double spending dari beberapa perangkat kasir.

Jika kode lama memiliki offline sync untuk wallet:

* Nonaktifkan dengan aman.
* Jangan langsung menghapus modul bila masih digunakan untuk fitur lain.
* Tambahkan feature flag seperti:

```php
'wallet_offline_enabled' => false,
```

---

# Authentication dan authorization

Pastikan hanya role berikut yang dapat membuat transaksi POS:

```text
super_admin
admin
kasir
```

Jika `super_admin` belum ada pada enum atau migration, catat sebagai architecture drift dan perbaiki secara aman bila memang digunakan oleh aplikasi.

Tambahkan Policy atau Gate, jangan hanya mengandalkan tampilan frontend.

Kasir hanya boleh menggunakan lokasi yang diberikan kepadanya, kecuali admin atau super admin memiliki izin lintas lokasi.

Jangan memindahkan bearer token ke URL, log, atau response error.

---

# Database constraints

Audit dan tambahkan constraint yang aman bila belum tersedia:

```text
transactions.client_transaction_id UNIQUE
transactions.transaction_number UNIQUE
wallet_transactions.idempotency_key UNIQUE
journal_entries(source_type, source_id, journal_type) UNIQUE
```

Tambahkan foreign key dan index yang relevan untuk:

```text
santri_id
cashier_id
location_id
transaction_id
product_id
created_at
status
```

Gunakan migration baru. Jangan mengubah migration lama yang sudah pernah dijalankan di production.

Jangan menjalankan:

```bash
php artisan migrate:fresh
php artisan migrate:reset
php artisan db:wipe
```

pada environment production.

---

# API response

Gunakan response terstruktur.

Contoh sukses:

```json
{
  "success": true,
  "message": "Transaksi berhasil.",
  "data": {
    "transaction_id": 1001,
    "transaction_number": "TRX-20260720-0001",
    "client_transaction_id": "UUID",
    "total": 25000,
    "wallet_balance_before": 100000,
    "wallet_balance_after": 75000,
    "status": "completed"
  }
}
```

Contoh saldo tidak cukup:

```json
{
  "success": false,
  "code": "INSUFFICIENT_WALLET_BALANCE",
  "message": "Saldo santri tidak mencukupi.",
  "errors": {
    "available_balance": 10000,
    "required_balance": 25000
  }
}
```

Gunakan error code stabil untuk frontend:

```text
INSUFFICIENT_WALLET_BALANCE
DAILY_LIMIT_EXCEEDED
MONTHLY_LIMIT_EXCEEDED
CATEGORY_NOT_ALLOWED
PRODUCT_OUT_OF_STOCK
PRODUCT_NOT_AVAILABLE_AT_LOCATION
DUPLICATE_TRANSACTION
WALLET_INACTIVE
SANTRI_INACTIVE
OFFLINE_WALLET_NOT_ALLOWED
```

Jangan mengirim stack trace ke client production.

---

# Testing wajib

Buat atau perbaiki automated test untuk skenario berikut:

## Feature tests

1. Kasir dapat membuat transaksi wallet.
2. Saldo berkurang sesuai total.
3. Wallet transaction tercatat.
4. Stok berkurang.
5. Inventory movement dibuat.
6. Journal entry dibuat dan seimbang.
7. Harga dimanipulasi frontend tetapi server menggunakan harga database.
8. Saldo tidak cukup.
9. Daily limit terlampaui.
10. Kategori diblokir.
11. Produk tidak tersedia di lokasi.
12. Stok tidak cukup.
13. Request dengan UUID sama tidak menghasilkan transaksi ganda.
14. User tanpa role POS mendapatkan 403.
15. Transaksi gagal menyebabkan seluruh perubahan rollback.
16. Transaksi reversed tidak dihitung dalam penggunaan limit.

## Concurrency tests

Buat test atau script untuk mensimulasikan:

1. Dua kasir mendebit wallet santri yang sama.
2. Dua kasir membeli stok produk terakhir.
3. Dua request menggunakan `client_transaction_id` yang sama.

Hasil yang diharapkan:

* Saldo tidak negatif.
* Stok tidak negatif.
* Hanya satu transaksi idempotent tercipta.
* Tidak ada jurnal atau wallet transaction ganda.

Gunakan database yang mendukung row-level locking untuk concurrency test. Jangan mengandalkan SQLite untuk pengujian locking.

---

# Logging dan audit

Catat event sensitif:

```text
POS_TRANSACTION_COMPLETED
POS_TRANSACTION_FAILED
WALLET_DEBIT
WALLET_CREDIT
WALLET_ADJUSTMENT
WALLET_REVERSAL
LIMIT_REJECTED
CATEGORY_REJECTED
TRANSACTION_DUPLICATE_REQUEST
```

Jangan menyimpan:

```text
password
session cookie
Sanctum token
API key
full authorization header
```

Setiap log transaksi harus memiliki correlation identifier:

```text
client_transaction_id
transaction_number
santri_id
cashier_id
location_id
```

---

# Batasan pekerjaan

1. Jangan melakukan rewrite seluruh aplikasi.
2. Jangan mengganti framework.
3. Jangan memperkenalkan microservices.
4. Jangan mengubah modul yang tidak berkaitan tanpa alasan kuat.
5. Pertahankan compatibility dengan fitur yang sudah berjalan.
6. Gunakan migration baru untuk perubahan database.
7. Hindari breaking change pada API tanpa memperbarui frontend terkait.
8. Jangan menyimpan uang dengan float.
9. Jangan mempercayai nominal dari frontend.
10. Jangan menggunakan queue untuk debit saldo atau jurnal utama.
11. Jangan mengaktifkan transaksi wallet offline.
12. Jangan menjalankan destructive database command.

---

# Hasil akhir yang harus diberikan

Setelah selesai, tampilkan:

## 1. Audit summary

```text
Masalah yang ditemukan
Risiko
Severity
File terkait
Perbaikan yang diterapkan
```

## 2. Daftar file

Pisahkan menjadi:

```text
Created
Modified
Deleted
```

Jangan menghapus file tanpa alasan yang jelas.

## 3. Database changes

Jelaskan:

```text
Migration baru
Constraint baru
Index baru
Dampak pada data lama
Cara rollback
```

## 4. Transaction flow final

Tuliskan alur final dari request POS sampai commit.

## 5. Test results

Jalankan minimal:

```bash
php artisan test
npm run build
```

Jika project terlalu besar, jalankan test yang relevan terlebih dahulu, kemudian jelaskan test mana yang belum dijalankan.

## 6. Deployment commands

Berikan command deployment yang aman untuk production, misalnya:

```bash
php artisan down
git pull
composer install --no-dev --prefer-dist --optimize-autoloader
php artisan migrate --force
npm ci
npm run build
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart
php artisan up
```

Sesuaikan dengan struktur repository aktual.

## 7. Verification checklist

Berikan checklist manual untuk memverifikasi:

```text
Login kasir
Pilih santri
Tambah produk
Bayar dengan saldo
Cek saldo
Cek mutasi wallet
Cek stok
Cek inventory movement
Cek journal entry
Coba submit ulang UUID yang sama
Coba saldo tidak cukup
Coba dua transaksi bersamaan
```

Mulai dengan membaca repository secara menyeluruh. Jangan membuat asumsi tentang nama class atau tabel sebelum memeriksa kode aktual. Prioritaskan correctness, konsistensi finansial, keamanan data, dan kemudahan audit daripada kecepatan implementasi.
