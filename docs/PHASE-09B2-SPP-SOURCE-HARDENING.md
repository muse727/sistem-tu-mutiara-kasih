# PHASE 09B.2 — SPP Source Hardening

## Scope
Harden the existing SPP payment lifecycle so it can later support a
provider-agnostic payment integration. `student_bills` remains the
authoritative business state; the payment provider is **not** the source of
truth for the school's final bill state. **Flip was NOT implemented. Midtrans
was NOT removed.** No Finance integration, journal/GL, refunds, or reversals.

The legacy repository `~/sistem-tu` was **not** touched.

---

## 1. Current SPP payment lifecycle (audited from code)

```
student_bill (UNPAID)
   │  createToken / createPayment        (PaymentSiswaController / ParentApiController)
   ▼
payment_attempt (order_id BILL-{id}-{rand}-{ts} | MULTI-{id1}_{id2}-{rand}-{ts}, status=pending)
   │  Midtrans Snap session
   ▼
provider request  →  Midtrans
   ▼
provider callback (POST /siswa/payment/callback | /api/midtrans-callback)
   │  1. signature_key = SHA512(order_id+status_code+gross_amount+server_key)  (hash_equals)
   │  2. order_id parsed (BILL- / MULTI-)
   │  3. bill / bills looked up
   │  4. gross_amount validated against authoritative amount(s)
   │  5. idempotency: bill.status / midtrans_order_id + attempt status
   ▼
bill state transition (UNPAID → PAID)  +  PaymentAttempt status update
   ▼
financial audit trail (FinancialAuditLogger → audit_logs)
```

## 2. Authoritative bill state
`student_bills` is the source of truth. Core financial identity fields
(`amount`, `student_id`, `type`, `bill_month`, `bill_year`, `pos_order_id`)
are **immutable once PAID** (enforced by a model `updating()` hook that throws).
Payment fields (`status`, `paid_at`, `payment_method`, `confirmed_by`,
`midtrans_order_id`) are writable only through the settlement paths.

## 3. PaymentAttempt role
Per-session ledger. One bill may accumulate many attempts
(expire/cancel → retry → settle). Holds the **provider channel labels**
(`payment_method`, `bank`, `va_number`), `transaction_id`, `snap_token`,
`gross_amount`, and lifecycle timestamps. Attempts are never the final bill
state — they are inputs that must be verified.

## 4. Provider identity role
Four distinct identities are kept separate (not collapsed):
- **A. Internal bill identity** — `student_bills.id`.
- **B. Payment attempt identity** — `payment_attempts.id` (+ `order_id`).
- **C. Provider transaction/reference** — `payment_attempts.order_id` /
  `transaction_id`, and `student_bills.midtrans_order_id` (correlation key).
- **D. Future integration identity** — `students.student_uuid` (Phase 09B.1)
  and a validated TU payment state/event (this phase).

`student_bills.midtrans_order_id` is **not** globally unique (multi-bill orders
share one order_id; the historical UNIQUE constraint was dropped 2026_09_02).
It is a plain index — a correlation key, not an external identity.

## 5. Idempotency boundary
**Bill-level**, not attempt-level:
- Single-bill: `student_bills.midtrans_order_id === $orderId` + `status === PAID`
  → duplicate callback returns early (no mutation, no duplicate audit).
- Multi-bill: per-bill `status === PAID` check skips already-settled bills.
- Attempt-level: `PaymentAttempt::order_id` is a plain (non-unique) index so a
  shared MULTI order_id can settle several attempts; uniqueness is enforced by
  the "one pending attempt per bill" rule in `createToken` (atomic
  `lockForUpdate` + check + reserve).
- Repeated/replayed callbacks are **idempotent** — no double total, stable
  `paid_at`, no duplicate `PAYMENT_CONFIRMED` audit.

## 6. Webhook verification
Both webhook paths (`PaymentSiswaController::callback`,
`ParentApiController::callback`) verify, in order:
1. `signature_key` via `hash_equals` (timing-safe SHA512).
2. `order_id` format (`BILL-` / `MULTI-`).
3. bill(s) exist.
4. `gross_amount` equals the authoritative amount.
5. idempotency / attempt state.
An invalid callback returns HTTP 200 (to stop provider retries) and mutates
**nothing**.

## 7. State machine
Supported bill transitions:
- `UNPAID → PAID` — only via a verified callback or an authorized cash payment.
- `PAID → PAID` — idempotent (no-op).
- Already PAID must not: re-increment totals, change `paid_at`, create a
  duplicate payment effect, or write a duplicate `PAYMENT_CONFIRMED` audit.
- PAID-bill core-field mutation is rejected by the model hook.
- **PARTIAL is not supported** by the domain (no partial accounting exists);
  it appears only as a legacy display colour. Not implemented here.

## 8. Amount integrity
Single-bill: `round((float)$gross_amount) === round((float)$bill->amount)`.
**Fix this phase:** the **MULTI-bill** callback previously had **no**
`gross_amount` validation. It now requires
`gross_amount === Σ bill.amount` for the order's bills; mismatch (under/over/
zero) is rejected and no bill is settled. Tests cover exact, under, over, zero,
and duplicate amounts.

## 9. paid_at semantics
`paid_at` uses the **application clock** (`now()`) — deterministic and
timezone-consistent. The provider timestamp is stored separately on the
attempt (`PaymentAttempt.settled_at`). `paid_at` is **never overwritten** by a
duplicate/replayed callback (idempotency guard returns before the write).

## 10. Cash flow
`BillingPaymentService::payCash`: validates payable state (already-PAID →
throws), single authoritative write `status=PAID, paid_at=now(),
payment_method='CASH', confirmed_by=<user>`, deducts bundle stock atomically,
writes `PAYMENT_CONFIRMED`. Cash settlement **does not** depend on any
Midtrans identifier. A cash-paid bill is `PAID`, so a later provider callback
is treated as a duplicate and ignored — cash and provider payments stay
separate (provider writes `MIDTRANS`, never `CASH`).

## 11. Audit behavior
- Legitimate settlement → `PAYMENT_CONFIRMED` (`SOURCE_MIDTRANS` / `SOURCE_API`
  / `SOURCE_WEB`).
- Attempt lifecycle → `PAYMENT_ATTEMPT_CREATED` / `PAYMENT_ATTEMPT_CANCELLED`.
- Settlement for a stale/terminal attempt → `PAYMENT_ATTEMPT_SETTLEMENT_IGNORED`.
- Duplicate/replayed legitimate callbacks → **no** `PAYMENT_CONFIRMED`
  (idempotent), so no misleading "new payment" effect.
- Invalid-signature / amount-mismatch / nonexistent-order callbacks mutate
  nothing and are recorded in the application log (`Log::warning/error`), not
  as financial events. No secrets/raw provider credentials are logged.

## 12. Deletion safety
`BillController::destroy()` rejects deletion of PAID bills (HTTP 422 /
redirect with error). A completed payment therefore remains traceable. PAID
core fields are immutable. No new destructive or archival behaviour introduced.

## 13. Provider-agnostic boundary
Conceptually separated without a large refactor:
- **Provider layer** (`PaymentSiswaController`, `ParentApiController`):
  provider order/reference, callback signature, provider status, channel
  metadata.
- **Business layer** (`BillingPaymentService`, `StudentBill`, `PaymentAttempt`):
  bill, payment attempt, verified amount, payment status, `paid_at`, payment
  method.
No new abstraction was introduced — the existing split already satisfies the
boundary.

## 14. Why Flip is NOT implemented yet
Flip is the future gateway target, but this phase must not: create Flip
credentials/client/webhook, call the Flip API, rename Midtrans tables, delete
Midtrans support, or migrate historical Midtrans data. Gateway migration is a
separate controlled phase. This phase only ensured the business state is
clean and verified so a provider swap is later possible.

## 15. Remaining blockers before 09B.3 POS hardening
- None blocking. Notes:
  - PARTIAL payment is unsupported by the domain (documented, not implemented).
  - POS hardening / integration contract (consuming a validated TU payment
    state/event) is the subject of the next phase, not this one.

---

## Test summary
- **Added:** `tests/Feature/SppMultiCallbackTest.php` — 7 tests (valid MULTI
  settlement, under/over/zero amount rejection, repeated-settlement idempotency,
  invalid signature, nonexistent order).
- **Existing coverage relied on:** `MidtransWebhookTest` (11),
  `PaymentAttemptLifecycleTest` (15), `PaymentTortureTest` (20),
  `PaymentWritePathTest` (8), `FinancialTimestampTest` (8) — signature, amount
  mismatch, duplicates, idempotency, cash, concurrency, legacy webhooks, audit.

## Full suite result
```
0 failed / 1 skipped / 239 passed (769 assertions)
```
(1 skipped = pre-existing intentional skip `ParentApiPaymentTest.php:213`.)
