# PHASE 09B.3 — POS Source Hardening


## Scope
Harden the existing POS (canteen) payment lifecycle so TU remains the reliable
authoritative source. **No** Finance integration, **no** Flip, **no** refunds/
reversals, **no** Midtrans removal. Legacy repo `~/sistem-tu` untouched.

---

## 1. POS identity hierarchy
- **`pos_orders.id`** — strongest internal identity (auto-increment PK).
- **`transaction_code`** — operational/public reference (`TRX-{time}-{rand3}`).
- **Provider reference** — `pos_orders.midtrans_order_id` (`POS-{id}-{rand6}-{ts}`),
  a correlation key for the Midtrans callback.

## 2. transaction_code policy
Generated as `'TRX-' . time() . Str::upper(Str::random(3))`. There **is** a
UNIQUE constraint (`pos_orders_transaction_code_unique`), which is the final
database protection against concurrent duplicate creation. Generation is **not**
cryptographically collision-proof (3 random chars/sec), but the DB UNIQUE index
guarantees no two rows ever share a code — a collision simply fails the insert
inside the order transaction and rolls back cleanly. No historical codes were
regenerated or rewritten. **Smallest safe change: keep generation, rely on the
existing UNIQUE constraint.** transaction_code is never the sole permanent
identity — `id` is.

## 3. payment_status authority
`payment_status` (enum `UNPAID|PAID|EXPIRED|CANCELLED`) is the **authoritative
POS payment state**. All payment truth (cash settle, QR/online settle, debt
generation, completion) is driven by it. The `is_paid` / `is_unpaid` accessors
read only `payment_status`.

## 4. Legacy status role
`pos_orders.status` (enum `UNPAID|PAID|CANCELLED|COMPLETED`) is a legacy
display/operational field. It is **not** used to infer payment truth and was
left untouched (not removed) for backward compatibility. The POS store path
does not rely on it for money semantics; `payment_status` alone governs
financial state. No conflicting payment truth is derived from it.

## 5. Payment state machine
Supported transitions:
- `UNPAID → PAID` — only via verified provider callback (`handlePosCallback`)
  or cash-at-counter (set `PAID` at order creation when `payment_amount ≥ total`).
- `PAID → PAID` — idempotent (guard returns early, no mutation).
- `UNPAID` stays `UNPAID` on expire/cancel/deny (logged, no PAID).
An already-PAID order must not: re-increment totals, re-settle a KANTIN bill,
create duplicate audit, or overwrite cash-settled amounts. A stale/replayed
callback on a PAID order is a no-op.

## 6. Webhook verification
`ParentApiController::callback` verifies the Midtrans **signature** first
(`hash_equals` SHA512 of `order_id+status_code+gross_amount+server_key`), then
routes `POS-` order_ids to `handlePosCallback`, which parses the id, looks up
the order, **validates `gross_amount === total_amount`**, applies the
idempotency guard, and only then settles inside a DB transaction.

## 7. Duplicate callback handling
`payment_status === 'PAID'` → early return (no mutation, no audit). Replays of
the same settlement change nothing and write no duplicate `PAYMENT_CONFIRMED`.
Settlement is wrapped in a transaction so order + KANTIN bill settle atomically.
**Fix:** the POS settle previously wrote non-existent columns `paid_amount` and
`payment_method`, which would throw and roll back every settlement. It now
writes only real columns: `payment_status`, `payment_amount`,
`change_amount`, `midtrans_order_id`.

## 8. Amount / money representation
Domain is **integer IDR**: `pos_items.price` and `pos_orders.total_amount` are
`int`. `payment_amount` / `change_amount` are `decimal(12,2)` columns but always
hold whole-rupiah values; the model casts all three to **integer** for
consistent arithmetic (the phantom `paid_amount` cast was removed). Totals are
recalculated **server-side** from DB prices — frontend totals are never trusted.
Change = `payment_amount - total` (cash only; 0 for debt/QRIS). No silent
decimal truncation is introduced.

## 9. Cash settlement
Cash is settled **at order creation** in `PosTransactionController::store`:
when `payment_amount ≥ serverTotal`, the order is created `payment_status=PAID`,
`change_amount=payment_amount-total`, inside a transaction with atomic stock
decrement. There is no separate re-settle endpoint, so a cash order cannot be
paid twice. Cash settlement is independent of any provider identity.

## 10. KANTIN bill relationship
For a debt (non-cash) order with a `student_id`, exactly one `StudentBill`
(`type=KANTIN`) is created at order-creation time, linked by
`student_bills.pos_order_id → pos_orders.id`. It is created **once** (inside
the same transaction) — retries/callbacks do not create duplicates. The
provider callback resolves the KANTIN bill via `pos_order_id` and marks it
PAID with `midtrans_order_id`, keeping `POS order ↔ KANTIN StudentBill`
traceable. A cash-paid order has no KANTIN bill and a later provider callback
on it is a no-op (cash vs provider stay separate).

## 11. Redemption separation
`redemption_status` (`PENDING|COMPLETED`) is **operational fulfillment**, not
payment truth. Cash orders are created `redemption_status=COMPLETED`; debt
orders `PENDING`. `PAID` does not imply `COMPLETED` and vice versa. The two
fields are independent and neither is inferred from the other.

## 12. Concurrency protection
- Order creation + stock decrement + KANTIN bill in one transaction;
  `PosItem::lockForUpdate()` for stock.
- POS settlement (order + KANTIN bill) in one transaction.
- Duplicate-settlement race is bounded by the `payment_status === 'PAID'` guard
  plus transactional atomicity — sufficient for the actual race (no broad
  locking added).

## 13. Audit behavior
- KANTIN bill created → `FinancialAuditLogger::billCreated`.
- KANTIN debt settled via provider → `PAYMENT_CONFIRMED` (`SOURCE_MIDTRANS`).
- Duplicate settlement → **no** `PAYMENT_CONFIRMED` (idempotent).
- Invalid signature / amount mismatch / nonexistent order → application log
  (`Log::warning/error`), no financial event, no secrets/raw signatures.

## 14. Deletion / refund limitations
No mature refund/void/reversal model exists — **none was implemented**. POS
orders are not exposed to a delete endpoint in this flow; completed orders
remain traceable. This is documented as a future consideration, not changed.

## 15. Authorization model
POS transaction/receipt routes are behind `role:admin,tu,staf`; reports behind
`role:admin,tu,kepala_sekolah` (role policy unchanged). External callbacks use
**signature verification only** (no frontend auth). No permissions were
broadened.

## 16. Remaining blockers for Phase 09C
- None blocking. Notes:
  - No refund/reversal framework (documented; out of scope).
  - transaction_code generation is collision-safe **only via** the DB UNIQUE
    constraint (3-char random suffix); acceptable now, could be strengthened in
    a later phase if volume grows.
  - Legacy `status` field remains for compatibility; payment truth is
    `payment_status`.

## 17. Flip migration
**Separate phase.** No Flip credentials/client/webhook, no Midtrans removal, no
column renames, no historical data migration.

---

## Test summary
- **Added:** `tests/Feature/PosPaymentCallbackTest.php` — 7 tests (valid POS
  settle + KANTIN sync, replay idempotency, amount mismatch, invalid signature,
  nonexistent order, cash-PAID no-op, phantom-column regression).
- **Modified:** none (only app code).
- **Relied on (existing):** SPP/webhook/idempotency suites from 09B.2.

## Full suite result
```
0 failed / 1 skipped / 246 passed (797 assertions)
```
(1 skipped = pre-existing intentional skip `ParentApiPaymentTest.php:213`.)
