# Phase E.3 — Student Frontend Flip Payment Integration

## Scope
Wire the **existing** student payment UI to the **existing** Flip backend (E.1).
No new backend endpoint was created; no settlement logic moved to the frontend.
Backend (Flip callback) remains the **sole authority** for payment state.

## Active payment flow

**Before (E.1 → E.3):**
`Bayar` → `POST /siswa/tagihan/{bill}/pay` (Midtrans `createToken`) → `{ snap_token }` → `window.snap.pay(...)` (Midtrans popup).

**After (E.3):**
`Bayar` → `POST /siswa/tagihan/{bill}/pay-flip` (Flip `FlipPaymentController@createBill`) → `{ payment_url }` → **browser redirect** to the Flip-hosted payment page. No Midtrans Snap popup.

## Endpoint + response contract
`POST /siswa/tagihan/{bill}/pay-flip` (route name `siswa.payment.flip`), auth `siswa` guard, CSRF token required.

Success (HTTP 200), provider-neutral:
```json
{
  "provider": "flip",
  "payment_reference": "356094",
  "payment_url": "flip.id/pwf-sandbox/.../#...",
  "status": "ACTIVE"
}
```
- The frontend **requires** `payment_url`; it prefixes `https://` if the value is scheme-relative.
- Failure: HTTP 400 (already paid / business), HTTP 403 (not owner), HTTP 502 (Flip provider error) — each returns `{ "error": "..." }`.
- No Flip secret, callback token, or HMAC credential is ever sent to the browser.

## Frontend behavior (UX preserved)
- On click: button disabled, spinner + "Memproses..." (double-click safe). Backend idempotency guarantees ONE open `PaymentAttempt` per bill; a repeat returns the **existing** `payment_url` (no duplicate Flip bill).
- On success: `window.location.href = payment_url` (hosted redirect). The button is intentionally **not** restored because the page navigates away.
- On failure / missing `payment_url`: button restored, error shown. The bill is **never** marked PAID by the frontend.

## Redirect / return handling (backend authoritative)
- Flip `redirect_url` sends the student back to `GET /siswa/tagihan` (the dashboard).
- The dashboard/bill-detail render status from the **server-side `StudentBill.status`** — never from URL params, browser navigation, or Flip's frontend.
- Until the Flip callback settles the bill, the bill shows UNPAID/PENDING. Only after the backend callback marks it PAID does the UI show PAID (and the receipt/struk link).

## Single vs multi selection (dashboard)
- The dashboard unpaid-bill list uses a select-then-pay pattern: tapping a bill **selects** it (checkbox) and reveals the bulk action bar.
- **Exactly 1 selected** → the action ("Bayar") opens the **single-bill confirmation sheet**, whose "Bayar Sekarang" is **enabled** and calls `pay-flip` for that bill id.
- **2+ selected** → the action ("Bayar Dipilih") opens the multi sheet, which is **disabled** (Flip supports one bill per hosted page) with a note to pay one at a time.
- The historical Midtrans multi backend (`POST /siswa/payment/multi`) is **left intact** (not removed) but is no longer invoked by the UI.
- The bill-detail page (`bill-detail.blade.php`) has its own always-visible enabled "Bayar Sekarang" → `pay-flip` for that bill.

## Files changed (frontend only)
- `resources/views/siswa/dashboard.blade.php` — single-pay now posts to `pay-flip` and redirects to `payment_url`; removed Snap.js script tag; removed Snap-based single/multi JS; disabled multi-pay button; updated helper copy.
- `resources/views/siswa/bill-detail.blade.php` — `bayarTagihan()` now posts to `pay-flip` and redirects to `payment_url`; removed Snap.js script tag; updated helper copy.

## What remains backend/provider authoritative
- Payment state (PAID/UNPAID) — only the Flip callback settles.
- Amount — authoritative `StudentBill.amount`; the frontend never sends an amount.
- Ownership (403), one-open-attempt idempotency, callback token + amount validation, outbox, Finance.

## Verification
- `php artisan test --filter=Flip` → 32 passed.
- `php artisan test` → 348 passed / 1 skipped / 0 failed (no regression).
- Blade compiles cleanly for both edited views.

## Remaining limitation
- **Real browser E2E (click → Flip page → return) requires a human in a browser** with Turnstile login; not automated here. No sandbox settlement is claimed by this phase. A fresh genuine callback E2E remains tracked separately (E.2 sandbox stale-callback caveat).
