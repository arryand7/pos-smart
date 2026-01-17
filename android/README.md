# SMART Android Wrapper

Minimal WebView wrapper untuk menjalankan SMART POS sebagai aplikasi Android.

## Cara pakai

1. Buka folder `android/` dengan Android Studio.
2. Ubah URL backend di `android/app/src/main/res/values/strings.xml` pada `web_app_url`.
3. Jalankan konfigurasi `app` (debug) untuk instalasi di emulator/perangkat.

## Catatan

- `usesCleartextTraffic="true"` di `AndroidManifest.xml` memudahkan koneksi HTTP saat dev lokal.
- `SmartAndroid` bridge tersedia untuk fitur native:
  - `printReceipt(html)` untuk cetak struk menggunakan Android print manager.
  - `startScan(mode)` untuk scan barcode/QR (menggunakan ZXing embedded).
- Modul Bluetooth printer masih bisa ditambahkan setelah alur ini stabil.
