# Implementasi Gate User Synchronization pada SMART

Implementasikan fitur **Pull-Based User Synchronization & Application Access Management** dari Sabira Connect atau Gate SSO ke aplikasi SMART.

Repository SMART telah dianalisis menggunakan:

```bash
graphify extract . --code-only
```

Gunakan hasil Graphify untuk memahami hubungan antarmodel, route, controller, service, migration, policy, middleware, dan frontend sebelum mengubah kode.

SMART sudah memiliki fitur penting berupa:

* POS.
* Saldo atau wallet santri.
* Wallet ledger.
* Transaksi pembelian.
* Limit belanja.
* Inventori.
* Akuntansi.
* Portal santri dan wali.
* Role super admin, admin, bendahara, kasir, santri, dan wali.

Sinkronisasi identitas Gate tidak boleh merusak atau mengubah data domain tersebut.

---

# 1. Sumber kebenaran

Gunakan urutan berikut:

1. Implementasi repository aktual.
2. Knowledge graph Graphify.
3. Dokumen ketentuan integrasi Gate.
4. `DESIGN.md`.
5. Automated tests.
6. Dokumentasi arsitektur lama.

Dokumentasi lama mungkin tidak lagi sesuai dengan kode aktual. Jangan mengikuti nama class, tabel, kolom, atau framework frontend dari dokumentasi tanpa memeriksa implementasinya.

Gunakan Graphify untuk menemukan execution path dan dependensi sebelum melakukan perubahan besar.

---

# 2. Pemeriksaan awal repository

Sebelum mengubah kode, jalankan:

```bash
pwd
git branch --show-current
git status
git diff --stat
git log -1 --oneline
git worktree list
```

Jangan membuang atau menimpa perubahan yang sudah ada.

Dilarang menggunakan:

```bash
git reset --hard
git clean -fd
git checkout -- .
```

Periksa hasil Graphify:

```bash
ls -lah graphify-out
test -f graphify-out/graph.json && echo "GRAPH JSON FOUND"
```

Baca bila tersedia:

```text
graphify-out/GRAPH_REPORT.md
graphify-out/graph.json
```

---

# 3. Query Graphify wajib

Sebelum membuat implementasi, jalankan query berikut atau query setara sesuai CLI Graphify yang tersedia.

## 3.1 Arsitektur identitas SMART

```bash
graphify query \
"Map the complete SMART identity architecture including users, santris, walis, roles, account status, authentication, authorization, profile photos, QR codes, wallet relationships, and transaction relationships. Include file and symbol references." \
--budget 6000
```

## 3.2 Risiko terhadap data finansial

```bash
graphify query \
"Find every relationship and code path where creating, updating, suspending, or deleting a user could affect wallet balance, wallet transactions, POS transactions, daily limits, inventory, journal entries, santri profiles, wali profiles, or historical data." \
--budget 6000
```

## 3.3 Titik integrasi Gate

```bash
graphify query \
"Identify the safest integration points for GateProvisioningClient, GateUserReconciliationService, GateUserSyncService, Superadmin routes, dry-run preview, transactional apply, sync result reporting, photo synchronization, QR synchronization, and account suspension middleware." \
--budget 6000
```

## 3.4 UI admin aktual

```bash
graphify query \
"Trace the actual SMART admin frontend architecture, layout, navigation, reusable components, CSS or Tailwind entry files, typography, tables, cards, modals, alerts, and authorization used to build a new Superadmin User Synchronization module." \
--budget 5000
```

## 3.5 User creation flow

```bash
graphify query \
"Trace every existing code path used to create users, santri profiles, wali profiles, assign roles, set passwords, set wallet balance, and establish user-profile relationships. Identify which flow should be reused for Gate provisioning." \
--budget 6000
```

Simpan hasil penting ke:

```text
docs/graphify-analysis/GATE_USER_IDENTITY_MAP.md
docs/graphify-analysis/GATE_SYNC_RISK_MAP.md
docs/graphify-analysis/GATE_SYNC_INTEGRATION_POINTS.md
```

Jika Graphify tidak dapat menulis langsung, ringkas hasilnya secara manual berdasarkan output aktual.

---

# 4. Laporan audit awal

Sebelum implementasi, buat ringkasan:

```text
Current user model
Current santri and wali relationships
Current role system
Current account status system
Current authentication mechanism
Existing Gate or OAuth integration
Existing activity log
Existing admin UI architecture
Fields already available
Required migrations
Risks to wallet and transaction history
Architecture drift
Implementation order
```

Setelah audit, lanjutkan implementasi tanpa menunggu persetujuan selama perubahan masih berada dalam lingkup Gate User Synchronization.

---

# 5. Prinsip integrasi wajib

## 5.1 Gate sebagai identity source of truth

Gate adalah sumber canonical untuk data identitas dasar.

Gunakan:

```text
gate_user_uuid
```

sebagai identifier permanen lintas aplikasi.

`gate_user_uuid` adalah UUID v4 dari Gate.

Dilarang menggunakan secara permanen:

```text
name
email
username
NIS
NIP
photo
QR code
```

sebagai relasi lintas aplikasi.

## 5.2 Dilarang auto-merge

Email, username, NIS, atau NIP hanya boleh digunakan untuk mendeteksi kemungkinan konflik.

Jika identifier tersebut cocok tetapi `gate_user_uuid` belum cocok atau belum terpasang:

```text
category = conflict
action = manual_review
```

Jangan secara otomatis memasangkan akun.

Dilarang menggunakan:

* Fuzzy matching.
* Similarity matching.
* Levenshtein matching.
* Auto-link berdasarkan nama.
* Auto-link berdasarkan email.
* Auto-link berdasarkan username.
* Auto-link berdasarkan NIS atau NIP.
* Auto-link berdasarkan foto.
* Auto-link berdasarkan QR.

## 5.3 Pull-based

Sinkronisasi hanya dimulai dari SMART oleh Superadmin melalui:

```text
Sync Users from Gate
```

Gate tidak boleh langsung mengubah database SMART.

## 5.4 Password isolation

Password tidak pernah:

* Diambil dari Provisioning API.
* Dikirim ke Gate.
* Diubah saat sinkronisasi.
* Ditampilkan pada preview.
* Dicatat di log.
* Disimpan dalam sync batch.

Autentikasi pengguna tetap melalui OAuth2/OIDC Gate atau mekanisme resmi yang sudah digunakan SMART.

## 5.5 Non-destructive revocation

Jika akses SMART dicabut di Gate:

```text
local user status = suspended
```

Jangan:

* Menghapus user.
* Menghapus santri.
* Menghapus wali.
* Menghapus saldo.
* Menghapus wallet ledger.
* Menghapus transaksi.
* Menghapus histori pembelian.
* Menghapus limit.
* Menghapus inventori.
* Menghapus jurnal.
* Memutus historical foreign key.

---

# 6. Konfigurasi Gate

Gunakan konfigurasi setara dengan:

```env
GATE_URL=https://gate.sabira-iibs.id
GATE_PROVISIONING_CLIENT_ID=
GATE_PROVISIONING_CLIENT_SECRET=
GATE_SYNC_PHOTO=false
GATE_SYNC_QR=false
```

Masukkan konfigurasi melalui:

```text
config/services.php
```

atau file konfigurasi khusus yang konsisten dengan repository.

Gunakan:

```php
config('services.gate.url')
```

Jangan memanggil `env()` dari controller atau service.

Semua request harus menggunakan HTTPS dengan header:

```http
X-Client-Id: <SMART_CLIENT_ID>
X-Client-Secret: <SMART_CLIENT_SECRET>
Accept: application/json
```

Secret tidak boleh muncul dalam:

* JavaScript.
* HTML.
* API response.
* Laravel log.
* Activity log.
* Exception.
* Query string.
* Git repository.

Gunakan timeout dan retry terbatas.

Contoh:

```php
Http::acceptJson()
    ->connectTimeout(5)
    ->timeout(20)
    ->retry(2, 500, throw: false);
```

Jangan retry operation yang dapat menghasilkan mutasi lokal ganda.

---

# 7. Database migration

Periksa skema aktual terlebih dahulu. Gunakan migration baru dan jangan mengubah migration lama.

Pastikan tabel user memiliki field setara dengan:

```text
gate_user_uuid
status
last_gate_synced_at
gate_photo_checksum nullable
qr_code nullable jika capability diperlukan
```

Ketentuan:

```text
gate_user_uuid:
- UUID atau VARCHAR(36)
- nullable
- unique

status:
- active atau suspended
- default active
- not null

last_gate_synced_at:
- nullable timestamp
```

Jangan membuat kolom role kedua apabila SMART sudah memiliki kolom `role` canonical.

Gunakan mapping terhadap role yang sudah ada.

Tambahkan tabel sync batch agar preview, apply, dan report dapat diaudit.

## 7.1 gate_sync_batches

Struktur minimal:

```text
id
uuid
initiated_by
status
gate_response_checksum
total_items
expires_at
applied_at nullable
report_status
report_attempts
last_report_error nullable
reported_at nullable
created_at
updated_at
```

Status yang disarankan:

```text
preview
ready
applying
applied
report_pending
completed
failed
expired
```

## 7.2 gate_sync_items

Struktur minimal:

```text
id
batch_id
gate_user_uuid nullable
local_user_id nullable
category
recommended_action
selected_action nullable
gate_payload
local_payload nullable
differences nullable
result_status nullable
external_user_id nullable
error_code nullable
error_message nullable
created_at
updated_at
```

Tambahkan index pada:

```text
batch_id
gate_user_uuid
local_user_id
category
result_status
```

Preview harus:

* Memiliki expiry.
* Hanya dapat diterapkan satu kali.
* Idempotent terhadap double submit.
* Tidak menyimpan password.
* Tidak menyimpan client secret.
* Tidak menyimpan temporary signed photo URL lebih lama dari yang diperlukan.

Sebelum menambahkan unique constraint, audit data lama.

Dilarang menjalankan:

```bash
php artisan migrate:fresh
php artisan migrate:reset
php artisan db:wipe
```

---

# 8. Service architecture

Jangan meletakkan seluruh implementasi di controller.

Gunakan atau buat struktur setara dengan:

```text
app/Services/Gate/GateProvisioningClient.php
app/Services/Gate/GateUserReconciliationService.php
app/Services/Gate/GateUserSyncService.php
app/Services/Gate/GatePhotoSyncService.php
app/Http/Controllers/Admin/GateUserSyncController.php
app/Http/Requests/Admin/ApplyGateUserSyncRequest.php
app/Policies/GateSyncBatchPolicy.php
```

## 8.1 GateProvisioningClient

Menangani:

```text
GET /api/provisioning/users
POST /api/provisioning/sync-results
download photo temporary signed URL
```

Tanggung jawab:

* Authentication header.
* Timeout.
* Safe retry.
* Response validation.
* Error normalization.
* Tidak mencatat secret.

## 8.2 GateUserReconciliationService

Menangani:

* Normalisasi payload Gate.
* Normalisasi user lokal.
* Pencocokan berdasarkan UUID.
* Deteksi konflik email atau username.
* Field comparison.
* Pembentukan delapan kategori.
* Statistik preview.
* Recommended action.

## 8.3 GateUserSyncService

Menangani:

* Validasi batch.
* Lock batch.
* Pencegahan double apply.
* Apply action.
* Database transaction.
* Activity log.
* Pembentukan result payload.
* Penyimpanan status reporting.

## 8.4 GatePhotoSyncService

Menangani foto hanya ketika capability diaktifkan:

```text
sync_photo = true
```

---

# 9. Delapan kategori reconciliation

Implementasikan tepat delapan kategori berikut.

## 9.1 matched

Kondisi:

```text
gate_user_uuid cocok
seluruh field identitas dasar sama
Gate identity aktif
akses SMART aktif
status lokal sesuai
```

Action:

```text
no_change
```

## 9.2 needs_update

Kondisi:

```text
gate_user_uuid cocok
terdapat perbedaan identitas dasar
```

Field yang boleh diperbarui:

```text
name
email
username
type jika ada
role atau application_role
status
photo
photo checksum
qr_code jika capability aktif
last_gate_synced_at
```

Jangan menyentuh data domain SMART.

## 9.3 missing_in_application

Kondisi:

```text
Gate identity aktif
akses SMART aktif
gate_user_uuid belum ada di SMART
tidak ditemukan konflik identifier
```

Action:

```text
create_local_user
```

## 9.4 access_revoked

Kondisi:

```text
user lokal terhubung melalui gate_user_uuid
akses SMART di Gate revoked atau inactive
```

Action:

```text
suspend_local_user
```

## 9.5 inactive_in_gate

Kondisi:

```text
identitas utama Gate inactive atau suspended
```

Action:

```text
suspend_local_user
```

## 9.6 reactivation_required

Kondisi:

```text
Gate identity aktif
akses SMART aktif
user lokal suspended
```

Action:

```text
reactivate_local_user
```

## 9.7 local_only

Kondisi:

```text
user lokal tidak mempunyai pasangan gate_user_uuid
dan tidak ditemukan pasangan Gate yang aman
```

Action:

```text
manual_review
```

Jangan suspend, update, link, atau hapus otomatis.

## 9.8 conflict

Kondisi:

```text
email atau username cocok
tetapi gate_user_uuid kosong, berbeda, atau ambigu
```

Action:

```text
manual_review
```

Kode konflik minimal:

```text
unlinked_matching_email
unlinked_matching_username
uuid_mismatch
multiple_local_candidates
multiple_gate_candidates
duplicate_gate_uuid
```

Conflict tidak boleh dimutasi oleh apply otomatis.

---

# 10. Algoritma reconciliation

Gunakan algoritma deterministik.

## 10.1 Index lokal

Buat index data lokal berdasarkan:

```text
gate_user_uuid
normalized_email
normalized_username
```

Normalisasi email dan username hanya untuk deteksi conflict, bukan untuk linking.

## 10.2 Prioritas pencocokan

```text
1. Exact gate_user_uuid
2. Email/username hanya untuk conflict detection
3. Tidak ada pasangan → missing_in_application
4. User lokal yang tidak dipasangkan → local_only
```

Jangan melakukan query per user yang menghasilkan N+1 query besar. Gunakan eager loading dan in-memory index yang terkontrol.

## 10.3 Field diff

Simpan perbedaan dalam format terstruktur:

```json
{
  "name": {
    "local": "Nama Lama",
    "gate": "Nama Canonical"
  },
  "role": {
    "local": "santri",
    "gate": "santri"
  }
}
```

Jangan menyimpan password, token, client secret, atau temporary signed URL dalam diff.

---

# 11. Alur dry-run preview

Tambahkan menu Superadmin:

```text
Users
└── User Synchronization
```

Ketika tombol:

```text
Sync Users from Gate
```

ditekan:

1. Authorize Superadmin.
2. Ambil user dari Gate.
3. Validasi payload.
4. Buat sync batch.
5. Jalankan reconciliation.
6. Simpan delapan kategori.
7. Tampilkan preview.
8. Jangan mengubah user lokal.
9. Jangan mengubah profile.
10. Jangan mengubah wallet atau transaksi.
11. Jangan melaporkan hasil final ke Gate.

Dry-run tidak boleh:

* Membuat user.
* Mengubah user.
* Menangguhkan user.
* Mengaktifkan user.
* Menambah saldo.
* Membuat wallet transaction.
* Mengubah limit.
* Mengubah stok.
* Membuat jurnal.
* Mengunduh foto secara permanen.

---

# 12. Pemilihan tindakan

Preview harus menampilkan recommended action, tetapi Superadmin tetap meninjau pilihan.

Action yang diperbolehkan:

```text
no_change
update_identity
create_local_user
suspend_local_user
reactivate_local_user
skip
manual_review
```

Aturan:

* `matched` → `no_change`.
* `needs_update` → `update_identity` atau `skip`.
* `missing_in_application` → `create_local_user` atau `skip`.
* `access_revoked` → `suspend_local_user`.
* `inactive_in_gate` → `suspend_local_user`.
* `reactivation_required` → `reactivate_local_user` atau `skip`.
* `local_only` → `manual_review`.
* `conflict` → `manual_review`.

Client tidak boleh mengubah category melalui request.

Saat apply, baca category dan payload dari batch server, bukan dari payload frontend.

Frontend hanya mengirim item ID dan selected action yang diizinkan.

---

# 13. Apply synchronization

Apply wajib menggunakan:

```text
POST
CSRF protection
Superadmin authorization
```

Gunakan database transaction lokal:

```php
DB::transaction(function () {
    // lock batch
    // validate state
    // apply selected actions
    // update sync item result
    // mark batch applied
});
```

Gunakan `lockForUpdate()` pada batch untuk mencegah double submit.

Aturan:

* Batch hanya dapat diterapkan satu kali.
* Preview expired tidak boleh diterapkan.
* Conflict tidak boleh dimutasi.
* Local-only tidak boleh dimutasi.
* Item ID dari batch lain harus ditolak.
* Action yang tidak sesuai category harus ditolak.
* Jika terjadi kegagalan critical, rollback seluruh batch.
* Jangan melaporkan sukses ke Gate sebelum local commit berhasil.

---

# 14. Membuat user SMART

Gunakan flow resmi repository, bukan langsung `User::create()` apabila terdapat service atau action canonical.

Periksa kebutuhan:

```text
users
santris
walis
roles
profile fields
required foreign keys
```

## 14.1 User santri

Jika Gate user memiliki role santri:

* Buat user lokal.
* Buat profile santri minimum hanya bila field wajib tersedia.
* Jangan membuat data palsu.
* Jangan membuat saldo berdasarkan Gate.
* Saldo awal harus mengikuti default resmi SMART, biasanya `0`.
* Jangan membuat wallet ledger palsu.
* Jangan menimpa santri lokal lain.

Jika profile tidak dapat dibuat karena field wajib tidak tersedia:

```text
result_status = failed atau manual_review
error_code = SANTRI_PROFILE_DATA_INCOMPLETE
```

Jangan membuat user setengah jadi di luar transaction.

## 14.2 User wali

* Jangan otomatis menghubungkan wali dengan santri berdasarkan nama, email, NIS, atau NIP.
* Relasi wali-santri hanya dibuat bila Gate memberikan identifier canonical yang eksplisit.
* Jika identifier relasi tidak tersedia, buat user/profile sesuai kemampuan repository dan biarkan relasi domain untuk manual review.

## 14.3 Role administratif

Gunakan mapping konfigurasi:

```php
'role_mapping' => [
    'super_admin' => 'super_admin',
    'admin' => 'admin',
    'bendahara' => 'bendahara',
    'kasir' => 'kasir',
    'santri' => 'santri',
    'wali' => 'wali',
],
```

Unknown role:

```text
manual_review
SYNC_ROLE_MAPPING_FAILED
```

Jangan pernah memetakan unknown role menjadi admin atau super_admin.

---

# 15. Update identitas

Untuk `needs_update`, hanya update field identitas yang diizinkan:

```text
name
email
username
role
type
status
avatar/photo
qr_code
last_gate_synced_at
gate_user_uuid
```

Jangan update:

```text
wallet_balance
daily_limit
monthly_limit
category restrictions
transactions
wallet_transactions
inventory
journal entries
daily closings
POS history
top-up history
```

Jangan mengubah password lokal.

---

# 16. Suspend dan reactivate

Akun lokal aktif hanya jika:

```text
Gate identity active
AND
Gate SMART application access active
```

Jika salah satu tidak aktif:

```text
status = suspended
```

Tambahkan atau periksa middleware agar user suspended tidak dapat:

* Login.
* Mengakses API.
* Menggunakan POS.
* Mengakses portal santri.
* Mengakses portal wali.
* Mengakses halaman admin.

User suspended tetap dapat ditampilkan dalam laporan administratif sesuai authorization.

Reactivation hanya mengubah status akun dan field identitas yang diizinkan.

Reactivation tidak boleh:

* Mengembalikan saldo lama dari Gate.
* Membuat saldo baru.
* Menghapus histori.
* Mengubah limit.
* Membuat transaksi.

---

# 17. Reporting kembali ke Gate

Setelah local transaction commit berhasil, kirim:

```text
POST /api/provisioning/sync-results
```

Contoh:

```json
{
  "items": [
    {
      "gate_user_uuid": "uuid",
      "status": "matched",
      "external_user_id": "101"
    }
  ]
}
```

Untuk conflict:

```json
{
  "gate_user_uuid": "uuid",
  "status": "conflict",
  "external_user_id": null,
  "error_code": "unlinked_matching_identifier",
  "error_message": "Identifier cocok tetapi gate_user_uuid belum terhubung."
}
```

Aturan:

* `external_user_id` adalah ID lokal SMART dalam bentuk string.
* Hanya item dari batch tersebut yang dikirim.
* Jangan mengirim password.
* Jangan mengirim saldo.
* Jangan mengirim transaksi.
* Jangan mengirim limit.
* Jangan mengirim secret.
* Retry reporting tidak boleh menjalankan ulang local apply.

Jika report ke Gate gagal setelah local commit:

```text
batch.status = report_pending
batch.report_status = pending
```

Jangan rollback perubahan lokal yang sudah committed.

Sediakan action Superadmin:

```text
Retry Report to Gate
```

Retry harus idempotent.

---

# 18. Foto profil

Aktifkan hanya jika:

```text
GATE_SYNC_PHOTO=true
```

Jika Gate mengirim:

```json
{
  "photo": {
    "available": true,
    "url": "temporary signed URL",
    "checksum": "checksum"
  }
}
```

Aturan:

1. Bandingkan checksum.
2. Download hanya jika berubah.
3. URL harus HTTPS.
4. Validasi host bila kontrak Gate menetapkan host tertentu.
5. Batasi ukuran download.
6. Validasi MIME type.
7. Validasi bahwa isi benar-benar image.
8. Simpan ke storage SMART.
9. Jangan menyimpan signed URL sebagai avatar permanen.
10. Simpan checksum.
11. Ganti file lama hanya setelah file baru berhasil.
12. Photo failure menjadi warning per item.
13. Photo failure tidak boleh mengubah saldo atau histori.

Gunakan aturan resize, rasio, padding, dan kompresi foto yang sudah berlaku di SMART jika tersedia.

---

# 19. QR code

Aktifkan hanya jika:

```text
GATE_SYNC_QR=true
```

Aturan:

* QR dari Gate bukan primary key.
* Identifier lintas aplikasi tetap `gate_user_uuid`.
* Jangan menghasilkan QR acak bila Gate tidak mengirim.
* Periksa duplicate QR.
* Duplicate QR menghasilkan conflict.
* Jangan overwrite QR user lain.

---

# 20. UI berdasarkan DESIGN.md

Gunakan implementasi frontend aktual dan `DESIGN.md`.

Jangan membuat UI yang tidak terhubung ke backend.

## 20.1 Halaman index

Tampilkan:

```text
Gate connection configuration status
Last successful sync
Last failed sync
Pending reports
Total local users
Users linked to Gate
Users without gate_user_uuid
Sync history
Sync Users from Gate button
```

Jangan melakukan live connection check setiap halaman dibuka kecuali diminta eksplisit.

## 20.2 Halaman preview

Tampilkan delapan summary cards:

```text
matched
needs_update
missing_in_application
access_revoked
inactive_in_gate
reactivation_required
local_only
conflict
```

Tabel minimal:

```text
Gate UUID
Name
Username
Email
Gate role
Local role
Gate identity status
Gate application access
Local status
Category
Differences
Recommended action
Selected action
```

Sediakan:

* Filter category.
* Search.
* Pagination jika data besar.
* Expandable field differences.
* Warning untuk conflict.
* Empty state.
* Loading state.
* Error state.
* Expired preview state.

## 20.3 Konfirmasi apply

Tampilkan ringkasan:

```text
Create
Update
Suspend
Reactivate
No change
Skip
Manual review
```

Gunakan modal konfirmasi.

Jangan menggunakan GET untuk apply.

## 20.4 Result page

Tampilkan:

```text
Applied
Skipped
Conflict
Failed
Report sent
Report pending
```

Berikan per-item error yang aman.

Jangan menampilkan stack trace.

## 20.5 Perubahan visual wajib terlihat

Pastikan menu, halaman, table, cards, badge, button, modal, alert, dan typography benar-benar menggunakan asset entry yang dimuat aplikasi.

Jalankan build dan periksa manifest Vite agar perubahan tidak hanya berada di source tetapi tidak digunakan runtime.

---

# 21. Authorization

Semua route berikut hanya untuk:

```text
super_admin
```

Route:

```text
sync index
create preview
view preview
apply batch
view result
retry Gate report
download conflict report
```

Jangan hanya menyembunyikan menu.

Gunakan middleware, Policy, atau Gate.

Role lain harus menerima HTTP 403:

```text
admin
bendahara
kasir
santri
wali
```

Jika role `super_admin` belum canonical, audit dan perbaiki secara kompatibel menggunakan migration baru.

---

# 22. Activity log

Catat:

```text
GATE_SYNC_PREVIEW_CREATED
GATE_SYNC_APPLIED
GATE_USER_CREATED
GATE_USER_UPDATED
GATE_USER_SUSPENDED
GATE_USER_REACTIVATED
GATE_SYNC_CONFLICT
GATE_SYNC_REPORT_SENT
GATE_SYNC_REPORT_FAILED
GATE_PHOTO_SYNCED
GATE_PHOTO_FAILED
```

Metadata aman:

```text
batch_uuid
actor_id
gate_user_uuid
local_user_id
action
result
timestamp
```

Jangan log:

```text
client secret
Authorization header
password
session cookie
temporary signed URL lengkap
raw credentials
```

---

# 23. Error codes

Gunakan error code stabil:

```text
GATE_CONFIGURATION_MISSING
GATE_CONNECTION_FAILED
GATE_AUTHENTICATION_FAILED
GATE_ACCESS_DENIED
GATE_RATE_LIMITED
GATE_INVALID_RESPONSE
SYNC_PREVIEW_EXPIRED
SYNC_ALREADY_APPLIED
SYNC_ITEM_CONFLICT
SYNC_INVALID_ACTION
SYNC_ROLE_MAPPING_FAILED
SYNC_LOCAL_VALIDATION_FAILED
SYNC_LOCAL_TRANSACTION_FAILED
SYNC_REPORT_PENDING
PHOTO_DOWNLOAD_FAILED
PHOTO_INVALID_CONTENT
QR_CODE_CONFLICT
SANTRI_PROFILE_DATA_INCOMPLETE
```

---

# 24. Production preflight command

Buat command read-only:

```bash
php artisan smart:gate-sync-preflight
```

Periksa:

```text
Gate URL configured
Gate URL uses HTTPS
Client ID configured
Client secret configured without printing it
gate_user_uuid duplicates
email duplicates
username duplicates
QR duplicates
invalid local status
unknown role mappings
santri without user
wali without user
user santri without profile if required
users without gate_user_uuid
pending Gate reports
expired unapplied batches
photo storage writable
queue availability if retry uses queue
```

Option:

```bash
php artisan smart:gate-sync-preflight --check-connection
```

Hanya option tersebut yang boleh melakukan request aman ke Gate.

Command default tidak mengubah database.

Gunakan exit code non-zero untuk masalah critical.

---

# 25. Automated tests

Gunakan `Http::fake()` untuk Gate. Jangan menghubungi Gate production dari test.

## 25.1 Reconciliation tests

1. UUID cocok dan field sama → `matched`.
2. UUID cocok dan nama berbeda → `needs_update`.
3. Gate aktif dan belum ada lokal → `missing_in_application`.
4. Akses SMART revoked → `access_revoked`.
5. Gate identity inactive → `inactive_in_gate`.
6. Gate aktif dan lokal suspended → `reactivation_required`.
7. User hanya ada lokal → `local_only`.
8. Email sama tanpa UUID → `conflict`.
9. Username sama tanpa UUID → `conflict`.
10. UUID berbeda tetapi email sama → `conflict`.
11. Multiple local candidates → `conflict`.
12. Tidak pernah auto-merge.

## 25.2 Preview tests

1. Preview tidak mengubah user.
2. Preview tidak mengubah saldo.
3. Preview tidak mengubah wallet ledger.
4. Preview tidak mengubah transaksi.
5. Preview tidak mengubah stok.
6. Preview tidak mengubah jurnal.
7. Preview menyimpan delapan kategori.
8. Expired preview ditolak.

## 25.3 Apply tests

1. Create local user berhasil.
2. Update identitas berhasil.
3. Suspend tidak menghapus user.
4. Reactivate berhasil.
5. Conflict tidak dimutasi.
6. Local-only tidak dimutasi.
7. Password tidak berubah.
8. Apply berada dalam database transaction.
9. Apply kedua ditolak.
10. Action yang dimanipulasi ditolak.
11. Batch item dari batch lain ditolak.
12. Kegagalan critical me-rollback batch.

## 25.4 Domain isolation tests

Sebelum dan sesudah sync, buktikan bahwa nilai berikut tidak berubah:

```text
santris.wallet_balance
santris.daily_limit
santris.monthly_limit jika ada
wallet_transactions count and data
transactions count and data
transaction_items count and data
products stock
inventory_movements count and data
journal_entries count and data
journal_lines count and data
daily_closings
```

## 25.5 Reporting tests

1. Payload result sesuai kontrak.
2. External user ID dikirim sebagai string.
3. Conflict mengirim error code.
4. Report failure menghasilkan `report_pending`.
5. Retry report tidak mengulang local apply.
6. Retry report idempotent.

## 25.6 Authorization tests

1. Superadmin dapat mengakses.
2. Admin mendapat 403.
3. Bendahara mendapat 403.
4. Kasir mendapat 403.
5. Santri mendapat 403.
6. Wali mendapat 403.
7. Apply menggunakan POST.
8. CSRF digunakan untuk web flow.

## 25.7 Suspended user tests

1. Suspended user tidak dapat login.
2. Suspended user tidak dapat menggunakan API.
3. Suspended kasir tidak dapat memakai POS.
4. Suspended santri tidak dapat mengakses portal.
5. Histori user suspended tetap tersedia untuk admin.

## 25.8 Photo and QR tests

1. Foto hanya diunduh jika checksum berubah.
2. Non-image ditolak.
3. File terlalu besar ditolak.
4. Signed URL tidak disimpan sebagai avatar.
5. Duplicate QR menghasilkan conflict.
6. Photo failure tidak mengubah domain finansial.

---

# 26. Dokumentasi

Buat:

```text
docs/GATE_USER_SYNC.md
```

Isi:

```text
Architecture
Graphify findings
Configuration
Database schema
Eight reconciliation categories
Matching algorithm
Preview flow
Apply flow
Reporting flow
Santri and wali provisioning
Suspension behavior
Photo handling
QR handling
Authorization
Activity logs
Commands
Testing
Deployment prerequisites
Rollback
Troubleshooting
Known limitations
```

Perbarui dokumentasi lama yang menyatakan frontend atau struktur user berbeda dari implementasi aktual.

---

# 27. Validasi runtime dan build

Jalankan:

```bash
php artisan test --filter=Gate
php artisan test
npm run build
```

Jika tersedia:

```bash
./vendor/bin/pint --test
./vendor/bin/phpstan analyse
npm run lint
```

Periksa:

```text
public/build/manifest.json
```

Pastikan file frontend yang diubah benar-benar masuk ke bundle yang dimuat halaman admin.

Laporkan:

* Command aktual.
* Exit code.
* Test passed.
* Test failed.
* Test skipped.
* Build result.
* File bundle yang berubah.

Jangan menyatakan berhasil tanpa menjalankan command.

---

# 28. Perbarui Graphify setelah implementasi

Setelah kode selesai:

```bash
graphify extract . --code-only
```

Kemudian query ulang:

```bash
graphify query \
"Trace the complete Gate user synchronization flow in SMART from Superadmin preview through reconciliation, transactional apply, local user mutation, domain data protection, result reporting, and report retry. Include file and symbol references." \
--budget 7000
```

Simpan hasil ke:

```text
docs/graphify-analysis/GATE_SYNC_FINAL_FLOW.md
```

Gunakan hasil tersebut untuk memastikan tidak ada route, service, atau mutation path yang terlewat.

---

# 29. Batasan eksekusi

Jangan:

* Deploy ke production.
* Menjalankan migration production.
* Mengubah `.env` production.
* Menjalankan destructive database command.
* Menghapus user.
* Auto-merge user.
* Mengubah saldo.
* Mengubah transaksi.
* Mengubah stok.
* Mengubah limit.
* Mengubah jurnal.
* Membersihkan queue production.
* Melakukan git push.
* Membuat commit tanpa instruksi eksplisit.
* Menghapus perubahan worktree pengguna.

---

# 30. Output akhir

Berikan laporan berikut.

## Repository and Graphify audit

```text
Current user model
Current profile relationships
Current role and status
Current auth
Existing Gate integration
Graph paths used
Architecture drift
```

## Implementation summary

```text
Preview flow
Eight categories
Apply flow
Reporting flow
Report retry
Suspension enforcement
Photo and QR
UI implementation
```

## Changed files

Pisahkan:

```text
Created
Modified
Deleted
```

## Database changes

Jelaskan:

```text
Migrations
Tables
Columns
Constraints
Indexes
Backfill requirement
Risk to existing data
Rollback
```

## Reconciliation proof

Tampilkan test fixture untuk semua delapan kategori.

## Domain isolation proof

Buktikan secara faktual bahwa sync tidak mengubah:

```text
wallet balance
wallet ledger
transactions
limits
stock
inventory movements
journal entries
daily closings
```

## Runtime proof

Tampilkan:

```text
Route
Controller
Service
Policy
Preview batch
Apply result
Gate report payload
Pending report retry
```

## UI proof

Jelaskan:

```text
Navigation entry
Pages created
Design tokens used
Frontend entry loaded
Build manifest result
Visible states
```

## Test results

Tampilkan command dan hasil aktual.

## Graphify verification

Tampilkan hasil tracing flow setelah ekstraksi ulang.

## Remaining risks

Cantumkan seluruh bagian yang belum selesai secara jujur.

## Production prerequisites

Berikan checklist deployment, tetapi jangan melakukan deployment.

Prioritaskan keamanan identitas, larangan auto-merge, transactional apply, non-destructive suspension, idempotent reporting, dan isolasi penuh data finansial SMART.