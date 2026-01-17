# SMART Debugging Playbook

Panduan ini merangkum cara menelusuri error di proyek SMART (Laravel + Vite PWA + modul pembayaran). Simpan sebagai referensi singkat ketika ada isu di VPS/dev machine.

## 1. Referensi Dokumen

| Modul | Dokumen | Lokasi |
| --- | --- | --- |
| Ringkasan fitur & role | PRD ringkas | `app-summary.md` |
| Detail arsitektur & modul | Detail implementasi | `app-detail.md` |
| Struktur service & tabel | Arsitektur tinggi | `docs/architecture.md` |

> Mulai dari dokumen di atas untuk memahami perilaku yang diharapkan sebelum memburu bug.

## 2. Backend Laravel

- **Log utama**: `storage/logs/laravel.log`. Gunakan `tail -f storage/logs/laravel.log` saat merepro bug.
- **Aktifkan debug**: set `APP_DEBUG=true`, `LOG_LEVEL=debug` sementara. Kembalikan ke `false/info` di produksi.
- **Bersihkan cache** ketika konfigurasi atau view berubah:
  ```bash
  php artisan optimize:clear
  ```
- **Route & middleware check**:
  ```bash
  php artisan route:list --path=pos
  php artisan about
  ```
- **Queue / webhook**: jalankan worker dengan verbose untuk melihat error signature validation.
  ```bash
  php artisan queue:work --tries=1 --backoff=3 -vvv
  ```
- **Testing**: gunakan suite feature/unit untuk merepro.
  ```bash
  php artisan test --testsuite=Feature
  ```
- **Dump request cepat**: pakai `ray()` atau `logger()` daripada `dd()` di code produksi.

## 3. Frontend, Vite, dan PWA

- **Vite dev server**: `npm run dev`. Error overlay bisa dimatikan lewat `server.hmr.overlay = false` pada `vite.config.js` bila perlu.
- **Build production**: `npm run build` untuk memastikan bundling tidak gagal (misal path asset salah).
- **POS asset**: entry utama ada di `resources/js/pos/main.js` yang saat ini memuat implementasi lama (`index.js`). Tambah modul baru di sini agar mudah dipantau oleh Vite.
- **Service worker**: file di `public/service-worker.js`. Setelah mengubahnya jalankan `php artisan view:clear` dan refresh dengan `navigator.serviceWorker.getRegistrations()` pada devtools.
- **Token bootstrap**: cek objek `window.__SMART_BOOTSTRAP__` di halaman `/pos` untuk memastikan Sanctum token tersuntik. Jika kosong, lihat `App\Http\Controllers\Api\Auth\AuthBridgeController`.

## 4. Database & Seed Data

- **Migrate + seed penuh**:
  ```bash
  php artisan migrate:fresh --seed
  ```
- **Seeder demo**: `php artisan db:seed --class=SmartDemoSeeder`. Menghasilkan ≥50 produk, santri, wali, plus user admin/bendahara/kasir.
- **Cek isi tabel** cepat:
  ```bash
  export XDG_CONFIG_HOME=$(mktemp -d)
  php artisan tinker --execute "echo App\\Models\\Product::count();"
  ```
- **Konsistensi limit & saldo**: gunakan `App\Models\Santri::with('walletTransactions')->find($id)` di Tinker untuk memastikan saldo akhir sesuai mutasi.

## 5. Autentikasi & Sanctum

- **Token error 401**: pastikan header `Authorization: Bearer <token>` terset (lihat `resources/js/services/authToken.js`). Event `auth:unauthorized` otomatis logout.
- **Regenerasi token**: hapus sesi lokal dengan `localStorage.removeItem('smart.auth.token')` lalu login ulang.
- **Scope role**: middleware `App\Http\Middleware\EnsureRole` digunakan di API (`routes/api.php`). Jika role tidak sesuai, cek kolom `users.role` dan abilities pada token.

## 6. Pembayaran & Webhook

- **Konfigurasi provider**: `config/payment.php` (belum di-commit? pastikan isi API key iPaymu/Midtrans/DOKU).
- **Webhook log**: simpan payload mentah ke tabel `payment_webhook_logs`. Gunakan `PaymentWebhookController` untuk replay.
- **Debug signature**:
  - Aktifkan log channel khusus dengan `LOG_CHANNEL=stack`.
  - Tambahkan `logger()->debug('ipaymu:webhook', request()->all());` di branch yang bermasalah.
- **Uji manual**:
  ```bash
  php artisan http:post https://smart.test/api/payments/webhook/ipaymu payload.json
  ```

## 7. POS Offline Queue

- **Storage key**: `smart.pos.offline.queue` di `localStorage`. Kosongkan saat ada data corrupt melalui devtools (`localStorage.clear()`).
- **Sinkronisasi**: modul `resources/js/services/offlineQueue.js` menangani enqueue/dequeue. Tambahkan log `console.table` untuk tiap flush jika transaksi hilang.
- **API health**: endpoint `/api/pos/transactions` memerlukan token role kasir/admin. Gunakan `php artisan route:list --path="pos/transactions"` bila 403 muncul.

## 8. Infrastruktur & VPS

- **Nginx/Supervisor**: contoh konfigurasi berada di `infra/` (nginx, supervisor, cron). Bandingkan dengan server aktual saat respon 502.
- **Cache & Horizon**: jika menggunakan Redis, flush queue dengan `php artisan horizon:purge`.
- **Backup**: script `infra/scripts/backup.sh` (jika belum dibuat, buatlah) untuk dump DB sebelum debugging tingkat lanjut.

## 9. Checklist Saat Ada Error

1. Identifikasi modul (POS, portal, payment, admin) dan buka dokumen terkait (`app-summary.md`, `app-detail.md`).
2. Repro di lokal menggunakan `sail up`/valet + `npm run dev`.
3. Periksa log Laravel + console browser.
4. Jalankan test terkait (`php artisan test --testsuite=Feature --filter=Pos`).
5. Jika menyentuh data, jalankan `php artisan migrate:fresh --seed` di lingkungan terisolasi untuk kondisi awal.
6. Catat langkah dan link dokumentasi ketika melaporkan/menutup isu di tracker internal.

Dengan playbook ini setiap error memiliki jalur investigasi yang jelas plus referensi dokumentasi supaya tim bisa menindaklanjuti lebih cepat.
