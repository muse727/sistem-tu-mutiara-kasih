# TU-FINANCE-BATCH-03-FIX — AUDIT FINDING FIXES

Implements **only** the findings from `docs/TU-FINANCE-BATCH-03-AUDIT.md`. No architecture
redesign, no UI, no reconciliation feature, no KANTIN changes, no `~/sistem-tu` changes.

---

## 1. Findings addressed

| ID | Severity | Status |
|----|----------|--------|
| F-1 | HIGH | **Fixed** — CLOSED batch close/dispatch race |
| F-2 | MEDIUM | **Fixed** — flag-flip accounting gap |
| F-3 | MEDIUM | **Fixed** — same-debit-COA consolidation |
| F-4 | MEDIUM | **Fixed** — inverted period range validation |

LOW findings (F-5 shared-revenue invariant, F-6 bucket granularity, F-7 duplicate-code
constant, F-8 unused READY_TO_CLOSE state) were **not** changed — they are documented as
configuration/cleanliness notes, not defects requiring code.

---

## 2. Root cause

**F-1:** `SppSettlementBatchService::recordEligibleSettlement` added a member and called
`recompute` on the period batch without checking `isClosed()`. During the close→dispatch
race, a settlement landing after `close()` was folded into the CLOSED batch; `recompute`
then recomputed the (frozen) totals — and because the in-memory model still held the stale
`DRAFT` status, the model immutability guard did not fire. The already-built outbox payload
(created at close) did **not** include the late settlement, so it was **never posted to
Finance** (silent revenue loss). A secondary blocker: the original
`UNIQUE(source_system, category, period_kind, period_start)` allowed only ONE batch per
period, so a late settlement had nowhere to go.

**F-2:** `OutboxDispatcher::restrictAccounting` gated per-bill `spp.payment` dispatch on the
**live** value of `integration.spp_batch_enabled`. Flipping the flag ON→OFF caused bills
batched while ON to be re-dispatched per-bill (double-post against their batch); flipping
OFF→ON stranded per-bill events for bills that were never batched (accounting gap).

**F-3:** `TuSettlementBatchService::resolvePlan` emitted one debit journal line per
breakdown bucket even when several buckets resolved to the **same** Finance debit account,
producing duplicate same-account debit lines (correct total, but a messy Buku Besar).

**F-4:** `TuSettlementBatchEvent::fromArray` validated date format but never rejected
`period_end < period_start`.

---

## 3. Fix

### F-1 — CLOSED batch race (TU)
- `SppSettlementBatchService::recordEligibleSettlement` now reloads the period batch under
  `lockForUpdate()` and checks `isClosed()` **on the locked/current row** before writing.
- A CLOSED batch is **never mutated**: late settlements are routed to a **new overflow
  batch** for the same period via `openOverflowBatch()`.
- `recompute` operates on the **locked/current** row (a DRAFT batch only), preserving
  atomicity of membership + totals in one transaction.
- `SppSettlementBatch::overflowIdempotencyKey()` generates a unique, stable key for overflow
  batches (`TU:SPP:{KIND}:OVR:{batch_id}`), so an overflow batch never collides with the
  canonical batch's deterministic key.
- Migration `2026_10_11_090000_make_spp_batch_period_unique_partial` replaces the full
  period-window unique with a **partial** unique that enforces **at most one DRAFT (open)
  batch per period** while allowing multiple CLOSED/overflow batches sharing a
  `period_start`. MariaDB: stored generated column `period_window` ('OPEN' only for DRAFT)
  + composite unique; SQLite (tests): partial unique index `WHERE status='DRAFT'`.

### F-2 — flag-flip gap (TU)
- `OutboxDispatcher::restrictAccounting` (query filter) replaced by
  `OutboxDispatcher::isAccountingEvent($event)`, evaluated **per event** at claim time.
- Rule: a per-bill `spp.payment` event is dispatched for accounting **only if its source
  `StudentBill` is NOT a batch member** (`SppSettlementBatchItem` lookup by
  `source_record_id`). Batched bills stay trace-only (their batch posts); un-batched bills
  dispatch per-bill. All other event types always dispatch.
- This makes the dispatch decision depend on **actual batch membership**, not the flag, so
  every settled bill retains exactly one accounting path regardless of flag oscillation.

### F-3 — same-debit-COA consolidation (Finance)
- `TuSettlementBatchService::resolvePlan` now keys the debit plan by `debit_coa_id` and
  `bcadd`s amounts, so buckets mapping to the same account consolidate into **one** debit
  line; distinct accounts stay separate. The plan is re-indexed (`array_values`) before
  posting. Totals and balance are preserved exactly.

### F-4 — period validation (Finance)
- `TuSettlementBatchEvent::fromArray` rejects `period_end < period_start` with
  `INVALID_EVENT`. No other validation was expanded (per the audit scope).

---

## 4. Concurrency behavior (F-1)

- **Membership vs close:** both `recordEligibleSettlement` and `close()` take a row lock
  (`lockForUpdate()`) on the batch. A settlement that arrives after close sees the locked
  row's `isClosed() === true` and is routed to the overflow batch — it can never mutate the
  frozen snapshot.
- **Overflow reuse:** `openOverflowBatch` reuses the period's single DRAFT overflow batch
  (or creates it), and the partial unique index guarantees only one open batch exists per
  period, so concurrent late settlements converge on one overflow batch.
- **Immutability guard:** because the locked row now carries the true `CLOSED` status, the
  model `updating` guard would fire on any attempted frozen-field mutation (defense-in-depth
  — the fix prevents reaching that state).

---

## 5. Flag boundary behavior (F-2)

- **ON→OFF:** a bill batched while ON remains a batch member → its per-bill event is NOT
  dispatched (no double-post); the batch posts it.
- **OFF→ON:** a bill settled while OFF is not a batch member → its per-bill event stays
  dispatchable → it is accounted (no gap).
- **Mixed:** batched and un-batched bills in the same dispatch run are handled independently
  — batched skipped, un-batched dispatched.
- Net: the accounting outcome for a bill is determined by **whether it was actually
  batched**, not by the flag value at dispatch time. Forward-only activation
  (`spp_batch_activated_at`) is unchanged.

---

## 6. COA consolidation behavior (F-3)

Example: Bucket A (MIDTRANS) → Bank BCA 50m, Bucket B (ONLINE/flip) → Bank BCA 30m now posts
`Dr Bank BCA 80m / Cr SPP Revenue 80m` as a single debit line, instead of two Bank BCA lines.
Distinct settlement accounts still produce one line each. Total and balance are exact.

---

## 7. Period validation (F-4)

A batch event with `period_end < period_start` is rejected with `INVALID_EVENT` (422) and
nothing posts. Equal start/end (a single-day period) remains valid.

---

## 8. Tests

Focused (all passing):
- **TU `tests/Feature/SppSettlementBatchTest.php` — 21 passed.**
  - F-1 (4): late settlement routes to new overflow batch without mutating the closed one;
    closed batch payload never recomputed; multiple late settlements reuse one overflow
    batch; at most one DRAFT batch per period.
  - F-2 (4): ON→OFF no re-dispatch of batched bill; OFF→ON un-batched bill still dispatched;
    mixed run; plus the updated membership-based "batched bill not dispatched" test.
- **Finance `tests/Feature/TuSettlementBatchIngestTest.php` — 16 passed.**
  - F-3 (2): same-debit buckets consolidate to one line; distinct-debit buckets stay
    separate.
  - F-4 (2): inverted period rejected; equal start/end accepted.

Regression (full suites):
- **TU:** 416 passed, 1 skipped.
- **Finance:** 446 passed, 8 skipped (PostgreSQL integration tests; need a provisioned PG
  test DB — unchanged).

One pre-existing TU test was **updated** (not weakened/removed): the "batch mode enabled →
per-bill not dispatched" test now makes the bill an actual batch member, reflecting the
corrected F-2 membership-based dispatch contract. All assertions are as strict or stricter.

---

## 9. Remaining limitations

- **F-5 (LOW):** the shared SPP revenue credit still uses the first bucket's
  `credit_coa_code`. This is correct only while all buckets map to a single SPP revenue
  account (documented invariant). Per-method revenue accounts would require a bendahara
  policy decision + code change. Unchanged in this task.
- **F-6 (LOW):** bucket granularity is `(payment_method, provider)` — sufficient for current
  providers (midtrans/flip).
- **F-7 (LOW):** the controller's duplicate branch still references the legacy
  `ERR_DUPLICATE_EVENT` constant (value-identical); cosmetic.
- **F-8 (LOW):** `READY_TO_CLOSE` remains defined but unused (batches go DRAFT→CLOSED).
- Reconciliation report (settled-not-batched-not-dispatched stragglers) is intentionally out
  of scope for this fix task.
- Overflow batches introduce a second Finance posting per affected period (canonical +
  overflow). This is correct and intended; Finance sees two balanced journals rather than
  one when a late settlement occurs after close. The canonical key remains
  `TU:SPP:{KIND}:{period_start}`; overflow keys are `TU:SPP:{KIND}:OVR:{batch_id}`.
