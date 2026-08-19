# Catatan Perbaikan Bug — Sistem POS
> File ini diupdate setiap ada progress perbaikan. Format status: `[ ]` Belum · `[~]` On Progress · `[x]` Selesai

---

## 🔴 P1 — Kritis (Harus Diperbaiki Segera)

| No | Status | Lokasi | Temuan |
|----|--------|--------|--------|
| 1 | [ ] | `PosTransactionController::store()` | **`total_amount` tidak divalidasi ulang di server** — nilai dikirim dari JS frontend, bisa dimanipulasi user untuk bayar Rp 1 |
| 2 | [ ] | `PosBundleController::generateBills()` | **Stok tidak berkurang saat generate bills** — berbeda dengan transaksi kasir yang ada `decrement('stock')`, generate bundle tidak kurangi stok sama sekali |
| 3 | [ ] | Migration `pos_bundle_items` | **`onDelete('cascade')` ke `pos_items`** — kalau admin hapus barang dari master, item dalam bundle ikut terhapus diam-diam tanpa warning. Bundle tetap aktif tapi komposisinya rusak |
| 4 | [ ] | `PosBundleController::generateBills()` | **Tidak ada cek duplikat tagihan** — tidak ada pengecekan apakah siswa sudah pernah dapat tagihan untuk bundle yang sama. Bisa double billing kalau klik dua kali |
| 22 | [x] | `PosBundleController::destroy()` | **Hapus bundle crash dengan FK violation** — controller langsung `$bundle->delete()` tanpa cek apakah bundle sudah dipakai di `bill_items`. DB menolak dengan `SQLSTATE 23000 RESTRICT`. Diperbaiki: cek dulu jumlah bill_items, kalau ada → tolak dengan pesan informatif, sarankan nonaktifkan saja |
| 5 | [x] | `PosBundleController::store()` & `update()` | **Modal Edit bundle selalu kosong** — `fetchAndEditBundle()` baca `data.items` tapi controller kirim `data.bundle.items`. Items bundle tidak pernah muncul saat edit |
| 6 | [x] | `PosBundleController` validasi | **Server validasi `products.*` nullable tapi frontend required** — inkonsistensi, bisa bypass validasi server dengan kirim array berisi null |

---

## 🟠 P2 — Penting (Berdampak pada Fitur & Akurasi Data)

| No | Status | Lokasi | Temuan |
|----|--------|--------|--------|
| 7 | [x] | `PosItem` model + migration | **`cost_price` / `buy_price` tidak ada di DB** — diselesaikan dengan Opsi A: field harga manual dihapus sepenuhnya, harga bundle dihitung otomatis dari sum komposisi item |
| 8 | [ ] | `PosTransactionController::repay()` | **Pelunasan tidak ada input nominal bayar** — `payment_amount` langsung diset = `total_amount` tanpa input dari user. Tidak akurat untuk kasus bayar lebih / transfer beda nominal |
| 9 | [ ] | `pos/transaction/print.blade.php` | **Struk hardcode nama sekolah** — nama, alamat, dan label di struk hardcode ("SMK Unggulan Jakarta", "Jl. Pendidikan No. 123"). Tidak ambil data dari tabel `school_settings` |
| 10 | [ ] | `PosBundleController::index()` | **N+1 query tersembunyi untuk `avgItems`** — `PosBundle::withCount('items')->get()` load semua bundle ke memori hanya untuk hitung rata-rata. Harus diganti query agregat langsung |
| 11 | [x] | `generate-bills.blade.php` vs `generateBills()` | **`$bundleTotal` di view vs `$bundle->price` di controller bisa berbeda** — diselesaikan: `bundle->price` dihapus, satu-satunya sumber kebenaran adalah sum komposisi item di kedua sisi |
| 12 | [ ] | `pos/transaction/index.blade.php` | **`payAmount` string comparison** — `this.payAmount` dari Alpine `<input type="number">` bisa jadi string, perbandingan `payAmount < totalAmount` bisa misbehave di JavaScript |

---

## 🟡 P3 — Minor (Inkonsistensi & UX)

| No | Status | Lokasi | Temuan |
|----|--------|--------|--------|
| 13 | [ ] | `resources/views/pos/bundles/` | **`create.blade.php` dan `edit.blade.php` dead code** — flow aktual pakai modal di `index.blade.php`. Dua file halaman penuh ini tidak pernah dicapai dari UI, tapi route masih terdaftar |
| 14 | [ ] | `PosBundle` model | **Dua relasi identik `items()` dan `bundleItems()`** — alias tidak perlu. Menyebabkan eager loading tidak konsisten di seluruh codebase |
| 15 | [x] | `PosBundle` model + migration | **Tipe `price` tidak konsisten** — diselesaikan: `price` dihapus dari `$fillable` dan `$casts` di model. Kolom di DB dibiarkan (tidak breaking), tidak lagi dipakai |
| 16 | [ ] | Sistem keseluruhan | **Dua sistem hutang tidak tersinkron** — hutang kasir masuk `pos_orders`, tagihan bundle masuk `student_bills`. Tidak ada rekonsiliasi, siswa bisa punya piutang ganda di dua tempat berbeda |
| 17 | [ ] | `pos/history/index.blade.php` | **Tidak ada detail item di tabel history** — kolom "Detail Transaksi" hanya tampil total dan jumlah item, tidak ada breakdown nama barang. Perlu cetak struk untuk audit |
| 18 | [ ] | `pos/history/index.blade.php` | **Tidak ada sorting piutang by tanggal terlama** — tidak bisa prioritaskan hutang yang sudah lama belum dibayar |
| 19 | [x] | `pos/bundles/index.blade.php` | **Logic disable tombol hapus row terbalik** — diperbaiki: hanya row pertama yang di-disable saat total = 1, row baru selalu bisa dihapus |
| 20 | [x] | `create.blade.php` & `index.blade.php` (modal) | **Dua jalur buat bundle punya field berbeda** — diselesaikan: field harga dihapus dari kedua jalur, modal create kini konsisten dengan field `description` dan `is_active` |
| 21 | [ ] | Semua view POS | **SweetAlert2 & dependencies via CDN tanpa SRI hash** — tidak ada `integrity` attribute. Minor security concern |

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
```

---

## Legenda Status
- `[ ]` — Belum dikerjakan
- `[~]` — Sedang dikerjakan / On Progress  
- `[x]` — Selesai diperbaiki
