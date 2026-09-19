# TU-DAILY-CLOSE-MAIN-UI-INTEGRATION

Daily Close SPP dipindahkan dari halaman Filament terpisah (`/admin/spp-daily-close`)
ke **aplikasi utama TU** — satu shell, satu sidebar, satu konteks autentikasi, satu
bahasa visual, satu sistem navigasi (sama seperti `/bills`). Tidak ada dependensi ke
`/admin` atau panel Filament. Backend tersertifikasi tidak diubah. Tanpa commit, tanpa
push. Pekerjaan hanya di `~/sistem-tu-latest`.

## 1. Arsitektur UI utama saat ini

- **Routing:** `routes/web.php`, grup `Route::middleware(['auth','verified'])`,
  otorisasi per-modul via middleware `role:` (contoh `role:admin,tu`).
- **Layout/shell:** `resources/views/layouts/app.blade.php` (komponen `<x-app-layout>`)
  — sidebar Alpine.js, palet warna dari `school_settings`, submenu expand/collapse.
- **Sidebar:** inline di `app.blade.php`; grup "Tagihan Sekolah" berisi "Monitoring
  Tagihan" (`bills.index`) + "Buat Tagihan Massal" (`bills.create`, admin/tu).
- **Halaman contoh:** `/bills` → `BillController@index` + `resources/views/bills/index.blade.php`.
- **Stack:** Blade + Tailwind + Alpine.js + SweetAlert2 (konfirmasi & toast flash
  `session('success'|'error'|'warning')`). TIDAK ada framework UI lain; TIDAK memakai
  Filament untuk fitur ini.
- **RBAC:** role-based (`users.role`: `admin|tu|staf|kepala_sekolah`) via
  `App\Http\Middleware\EnsureRole` — SAMA seperti modul `/bills`. Tidak ada Spatie.

## 2. Implementasi Filament sebelumnya

Task sebelumnya membuat entry point terpisah:
- `app/Filament/Pages/SppDailyClose.php`
- `resources/views/filament/pages/spp-daily-close.blade.php`
- `tests/Feature/SppDailyClosePageTest.php`

Ini mengharuskan operator masuk panel `/admin` (shell/sidebar terpisah) — bukan UX yang
diinginkan. Selain itu panel Filament di-protect gate env vendor (403 untuk semua role
saat `app.env !== 'local'`) dan rutenya sempat melempar error konfigurasi nav-group —
keduanya alasan kuat untuk TIDAK menjadikan Filament sebagai titik operasional.

## 3. Apa yang dihapus / diganti

- **Dihapus (duplicate UI entry point):** `app/Filament/Pages/SppDailyClose.php`,
  `resources/views/filament/pages/spp-daily-close.blade.php`,
  `tests/Feature/SppDailyClosePageTest.php`. Direktori `app/Filament/Pages` dan
  `resources/views/filament` ikut dihapus karena kosong.
- **Tidak disentuh:** service tersertifikasi (`SppSettlementBatchService`, model batch,
  lifecycle, outbox, HMAC, kontrak v1.0, rekonsiliasi, Finance, command CLI). Penghapusan
  hanya pada lapisan UI Filament + tes/dokumentasi terkaitnya.
- **Diganti oleh:** controller + Blade + route aplikasi utama (lihat §4–§6).

Verifikasi: `php artisan route:list` tidak lagi memuat `admin/spp-daily-close`; tidak ada
referensi tersisa ke halaman Filament Daily Close.

## 4. Lokasi sidebar baru

`Tagihan Sekolah` (grup yang sudah ada) kini berisi:
```
Tagihan Sekolah
├─ Monitoring Tagihan      (bills.index)
├─ Buat Tagihan Massal     (bills.create — admin/tu)
└─ Daily Close SPP         (spp-daily-close.index)
```
Mengikuti persis tipografi/spasi/ikon/active/hover/expand-collapse pola submenu yang ada.
Link "Daily Close SPP" tampil untuk `admin,tu,staf,kepala_sekolah` (cakupan monitoring,
sama seperti `/bills`). Active state & auto-expand grup diperluas ke
`spp-daily-close.*`.

## 5. Route baru

Di dalam shell yang sama (`auth`,`verified`):
- `GET  /spp-daily-close` → `spp-daily-close.index`  (`role:admin,tu,staf,kepala_sekolah`)
- `POST /spp-daily-close` → `spp-daily-close.close`  (`role:admin,tu`)

Route dedicated BUKAN aplikasi terpisah — merender di dalam `<x-app-layout>` + sidebar
yang sama; tidak ada dependensi `/admin`.

## 6. Perilaku otorisasi

RBAC role bawaan aplikasi (middleware `role:`), dipetakan ke desain tersertifikasi:
- **admin** → dapat melihat + close.
- **tu** → dapat melihat + close.
- **staf** → dapat melihat, TIDAK dapat close (POST 403; UI menampilkan badge read-only).
- **kepala_sekolah** → melihat saja (konsisten dengan monitoring `/bills`).
- **role lain / guest** → tidak dapat mengakses (guest → redirect login).

Defense-in-depth: `SppDailyCloseController::close()` juga `abort_unless(canClose(), 403)`
di atas middleware. Mekanisme persis `EnsureRole` yang dipakai `/bills` — bukan sistem
RBAC kedua.

## 7. Interaksi UI ↔ service

Controller `SppDailyCloseController` adalah lapisan tipis:
- `index()` → `eligibleBatch()` memuat batch open (DRAFT/READY_TO_CLOSE), `recompute()`
  agar angka mutakhir, lalu menampilkan field authoritative: period start/end, batch
  reference, total, payment count, bill count, status, breakdown. Tanpa kalkulasi bisnis
  di frontend.
- `close()` → **persis jalur CLI**: `SppSettlementBatchService::close()` lalu
  `buildOutboxEvent()` saat hasilnya `CLOSED`. Tidak memutasi model langsung, tidak
  menduplikasi lifecycle, tidak ada algoritma close kedua.

## 8. Alur konfirmasi

Sebelum close, modal konfirmasi **SweetAlert2** (pola yang sama dengan bayar/hapus di
`/bills`) menampilkan periode, referensi batch, total, dan payment/bill count. Hanya
"Ya, Tutup Batch" yang submit form POST. Setelah sukses: redirect ke halaman (data
ter-refresh), flash `success` → toast, status menjadi CLOSED, dan halaman kembali ke
empty state. Tidak pernah mengarang SENT/ACKNOWLEDGED.

## 9. Perilaku error / empty / loading

- **Loading:** SSR — state final dirender server (tidak ada spinner AJAX yang perlu
  dikelola); pola konsisten dengan `/bills`.
- **Empty:** tidak ada batch eligible → kartu "Tidak ada batch yang memenuhi syarat".
- **Batch kosong:** service membiarkan DRAFT → flash `warning` ("tetap DRAFT"), sesuai CLI.
- **Sudah tertutup / tidak ada:** `close()` re-query, redirect `warning` tanpa mutasi.
- **Service exception:** ditangkap, `Log::error` (konteks batch/user, **tanpa stack trace
  ke operator**), flash `error` generik.
- **Unauthorized:** middleware `role:` → 403; guest → redirect login.

## 10. Tests

`tests/Feature/SppDailyCloseMainUiTest.php` — **17 tests, 49 assertions**, semua lulus.
Menggunakan konvensi HTTP `actingAs` + `role:` yang sudah ada (TANPA framework tes
frontend baru):
- **Auth:** guest redirect; admin/tu/staf/kepala_sekolah dapat GET; admin/tu dapat close;
  staf/kepala_sekolah close → 403 + batch tak berubah.
- **Eligibility:** empty state; batch eligible tampil dengan angka live + status.
- **Close:** → CLOSED; outbox v1.0 punya `total_amount/payment_count/bill_count/breakdown`
  dan TANPA key fee; flash success.
- **Safety:** close berulang idempotent (1 batch, 1 event); settlement terlambat →
  overflow; batch tertutup immutable (`LogicException`).
- **Regresi/konvergensi:** command CLI tetap menutup; batch kosong tetap DRAFT.

Tes bisnis/service yang sudah melindungi close()/lifecycle/overflow/immutability/
idempotency/outbox (`SppSettlementBatchTest` dsb.) dipertahankan dan tetap hijau.

## 11. Hasil build / lint

- **Frontend build (Vite):** ✅ `npm run build` sukses (app.css/js ter-build, tidak ada
  perubahan asset yang diperlukan — view memakai utilitas Tailwind yang sudah ada).
- **Pint:** controller + tes auto-fixed (gaya minor) → PASS; view Blade PASS.

## 12. Hasil regresi

- Fokus batch/close: **76 passed** (252 assertions).
- **Full TU suite (`composer test`): 451 passed, 1 skipped** (1,408 assertions) — baseline
  434 → +17 tes baru, **zero regresi**. Skip adalah tes mock facade Midtrans yang sudah
  ada sebelumnya.

## 13. Sisa gap

1. **Panel Filament masih ter-gate env (pre-existing, LOW).** Gate vendor
   `Filament\Http\Middleware\Authenticate` tetap 403 untuk semua role saat
   `app.env !== 'local'` karena `User` tidak mengimplementasikan `FilamentUser`. Ini
   tidak lagi memengaruhi Daily Close (kini di aplikasi utama), dan hanya relevan bila
   panel `/admin` perlu di-host non-local — cakupan terpisah (`canAccessPanel()`), sengaja
   tidak disentuh.

## Status akhir: VERIFIED

Daily Close SPP kini muncul langsung di sidebar aplikasi utama, memakai shell yang sama
dengan `/bills`, tanpa `/admin`/Filament, memanggil service close tersertifikasi. Admin/TU
dapat close, staf view-only, proteksi close/lifecycle tetap utuh, CLI dan browser konvergen
pada logika yang sama, seluruh suite TU lulus, dan tidak ada perubahan Finance/kontrak/fee.
