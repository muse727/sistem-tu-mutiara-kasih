# Catatan Perbaikan Bug — Sistem POS
> File ini diupdate setiap ada progress perbaikan. Format status: `[ ]` Belum · `[~]` On Progress · `[x]` Selesai

---

## 🔴 P1 — Kritis (Harus Diperbaiki Segera)

| No | Status | Lokasi | Temuan |
|----|--------|--------|--------|
| 1 | [x] | `PosTransactionController::store()` | **`total_amount` tidak divalidasi ulang di server** — diperbaiki: `total_amount` dari frontend diabaikan, server rekalkulasi `$serverTotal` dari harga DB × qty cart. `payment_amount` di-cast ke float agar tidak misbehave. Validasi cart ditambah `min:1` |
| 2 | [x] | `PosBundleController::generateBills()` | **Stok tidak berkurang saat generate bills** — diperbaiki dengan Opsi A: stok dipotong saat generate (validasi dulu, lalu decrement per bundleItem). `BillingPaymentService::deductBundleStock()` di-skip untuk tipe PAKET agar tidak double-potong. `BillController::destroy()` mengembalikan stok saat tagihan PAKET UNPAID dihapus |
| 3 | [x] | Migration `pos_bundle_items` | **`onDelete('cascade')` ke `pos_items`** — diperbaiki via migration `2026_08_19_175005_fix_pos_bundle_items_pos_item_fk_to_restrict`. FK diganti ke `RESTRICT`: hapus barang yang masih dipakai di bundle sekarang ditolak DB, bukan diam-diam hapus komposisi bundle |
| 4 | [x] | `PosBundleController::generateBills()` | **Tidak ada cek duplikat tagihan** — diperbaiki: cek `StudentBill` dengan `whereHas('items')` per siswa per bundle sebelum buat tagihan baru. Kalau sudah ada UNPAID → skip + catat di `$errors` |
| 22 | [x] | `PosBundleController::destroy()` | **Hapus bundle crash dengan FK violation** — controller langsung `$bundle->delete()` tanpa cek apakah bundle sudah dipakai di `bill_items`. DB menolak dengan `SQLSTATE 23000 RESTRICT`. Diperbaiki: cek dulu jumlah bill_items, kalau ada → tolak dengan pesan informatif, sarankan nonaktifkan saja |
| 5 | [x] | `PosBundleController::store()` & `update()` | **Modal Edit bundle selalu kosong** — `fetchAndEditBundle()` baca `data.items` tapi controller kirim `data.bundle.items`. Items bundle tidak pernah muncul saat edit |
| 6 | [x] | `PosBundleController` validasi | **Server validasi `products.*` nullable tapi frontend required** — inkonsistensi, bisa bypass validasi server dengan kirim array berisi null |

---

## 🟠 P2 — Penting (Berdampak pada Fitur & Akurasi Data)

| No | Status | Lokasi | Temuan |
|----|--------|--------|--------|
| 7 | [x] | `PosItem` model + migration | **`cost_price` / `buy_price` tidak ada di DB** — diselesaikan dengan Opsi A: field harga manual dihapus sepenuhnya, harga bundle dihitung otomatis dari sum komposisi item |
| 8 | [x] | `PosReportController::repay()` | **Pelunasan tidak ada input nominal bayar** — diperbaiki: backend terima & validasi `payment_amount` dari request, hitung `change_amount` otomatis. Frontend: SweetAlert kini tampilkan input nominal dengan `preConfirm` validasi, kirim via hidden input |
| 9 | [x] | `pos/transaction/print.blade.php` | **Struk hardcode nama sekolah** — diperbaiki: `printStruk()` sekarang pass `$school` dari `DB::table('school_settings')->pluck('value', 'key')`. View ganti hardcode dengan `$school['school_name']`, `$school['school_address']`, `$school['school_phone']` dengan fallback aman |
| 10 | [x] | `PosBundleController::index()` | **N+1 query tersembunyi untuk `avgItems`** — diperbaiki: pakai `DB::table('pos_bundle_items')->selectRaw('AVG(cnt)')` dengan subquery agregat, tidak load semua bundle ke memori |
| 11 | [x] | `generate-bills.blade.php` vs `generateBills()` | **`$bundleTotal` di view vs `$bundle->price` di controller bisa berbeda** — diselesaikan: `bundle->price` dihapus, satu-satunya sumber kebenaran adalah sum komposisi item di kedua sisi |
| 12 | [x] | `pos/transaction/index.blade.php` | **`payAmount` string comparison** — diperbaiki: inisialisasi `payAmount: 0` (bukan string kosong), semua perbandingan pakai `parseFloat(this.payAmount) || 0` sebelum compare dan submit |

---

## 🟡 P3 — Minor (Inkonsistensi & UX)

| No | Status | Lokasi | Temuan |
|----|--------|--------|--------|
| 13 | [x] | `resources/views/pos/bundles/` | **`create.blade.php` dan `edit.blade.php` dead code** — field `price` dan `buy_price` dihapus dari kedua view, panel 'Estimasi Profit' diganti panel 'Total Harga Otomatis', validasi `price > 0` dihapus, `data-price` pakai `$p->price` (bukan `$p->buy_price`) |
| 14 | [x] | `PosBundle` model | **Dua relasi identik `items()` dan `bundleItems()`** — diperbaiki: alias `bundleItems()` di `PosBundle` dihapus. `PosItem::bundleItems()` tetap ada karena berbeda konteks (relasi dari sisi item ke bundle) |
| 15 | [x] | `PosBundle` model + migration | **Tipe `price` tidak konsisten** — diselesaikan: `price` dihapus dari `$fillable` dan `$casts` di model. Kolom di DB dibiarkan (tidak breaking), tidak lagi dipakai |
| 16 | [x] | Sistem keseluruhan | **Dua sistem hutang tidak tersinkron** — diselesaikan dengan Opsi C: `student_bills` jadi satu-satunya sumber kebenaran piutang siswa. Saat checkout hutang kasir, `StudentBill` type `KANTIN` dibuat otomatis. `repay()` sync keduanya. `ParentApi` ganti query dari `PosOrder` ke `StudentBill` type `KANTIN`. `getTotalDebtAttribute()` hapus `$posDebt`. Migration tambah `pos_order_id` ke `student_bills` |
| 17 | [x] | `pos/history/index.blade.php` | **Tidak ada detail item di tabel history** — diperbaiki: eager load `items.item` di controller, kolom Detail Transaksi sekarang tampilkan nama barang × qty (max 3, sisanya "+N lainnya") |
| 18 | [x] | `pos/history/index.blade.php` | **Tidak ada sorting piutang by tanggal terlama** — diperbaiki: tambah dropdown "Piutang Terlama" di form filter, controller handle `sort=oldest_unpaid` dengan `->oldest()` |
| 19 | [x] | `pos/bundles/index.blade.php` | **Logic disable tombol hapus row terbalik** — diperbaiki: hanya row pertama yang di-disable saat total = 1, row baru selalu bisa dihapus |
| 20 | [x] | `create.blade.php` & `index.blade.php` (modal) | **Dua jalur buat bundle punya field berbeda** — diselesaikan: field harga dihapus dari kedua jalur, modal create kini konsisten dengan field `description` dan `is_active` |
| 21 | [x] | Semua view POS | **SweetAlert2 & dependencies via CDN tanpa SRI hash** — diperbaiki: SRI hash ditambah di `layouts/app.blade.php` untuk SweetAlert2, duplikat `<script>` di 5 view dihapus. ApexCharts di `dashboard.blade.php` juga ditambah SRI hash |

---

## Catatan Progress

```
[Tanggal]      [No Bug]      [Keterangan]
 Hari ini       #5, #6        Fix modal Edit selalu kosong — controller kini kirim data.items dengan pos_item_id & quantity
 Hari ini       #6            Validasi server diperketat: products.* jadi required|exists, hapus nullable
 Hari ini       #7, #11, #15  Opsi A diterapkan: field Harga Jual manual dihapus dari model, controller, semua view
 Hari ini       #19           Fix logic disable tombol hapus row — hanya row pertama di-disable saat total = 1
 Hari ini       #20           Modal create & edit kini konsisten: description + is_active + total otomatis
 Hari ini       #22           Fix destroy() — cek bill_items sebelum hapus, tolak dengan pesan jelas jika sudah ada tagihan
 Hari ini       #4            Fix duplikat tagihan — whereHas('items') per siswa per bundle sebelum generate
 Hari ini       #3            Migration fix cascade→restrict di pos_bundle_items.pos_item_id
 Hari ini       #2            Opsi A: potong stok saat generate, skip deductBundleStock untuk PAKET, rollback stok saat hapus tagihan
 Hari ini       #1            Rekalkulasi total_amount di server, tidak percaya nilai dari frontend
```

---

## Legenda Status
- `[ ]` — Belum dikerjakan
- `[~]` — Sedang dikerjakan / On Progress  
- `[x]` — Selesai diperbaiki
