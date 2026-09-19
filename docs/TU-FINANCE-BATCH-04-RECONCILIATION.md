# TU-FINANCE-BATCH-04 — SPP SETTLEMENT RECONCILIATION

Service-level reconciliation for the existing SPP Settlement Batch architecture. **Read-only,
detection-only.** No redesign, no accounting-basis change, no COA-mapping change, no UI, no
auto-repair, no retroactive re-batching, no alteration of posted journals, no KANTIN changes.
Reference: BATCH-01/02/03/03-FIX.

---

## 1. Objective

Answer, deterministically: **"Apakah Settlement Batch TU sudah sama persis dengan accounting
result di Finance?"** — comparing TU batch total, item/payment aggregation, payment-method/
provider breakdown, Finance posted debit/credit totals, the Finance transaction/journal
linked to the batch, and counts/references where available.

Reconciliation **does not assume correctness from debit = credit alone**: a perfectly
balanced journal can still carry the wrong amount, wrong source identity, or wrong
per-account breakdown. The service independently verifies the TU snapshot's internal
consistency, the Finance traceability, the posted amount, the debit/credit totals, and the
per-bucket debit lines against the Finance-owned COA mapping.

---

## 2. Reconciliation Model

Two read-only components, following the existing `ReconciliationEngine` architecture
(Finance-side, TU data as normalized arrays, **no cross-database coupling**):

1. **TU — `SppSettlementBatchReconciliationSnapshot`** (`~/sistem-tu-latest`): builds a
   deterministic normalized snapshot of one batch. It carries the **frozen** accounting
   snapshot (total/counts/breakdown as closed/sent) AND a **live recomputation** from member
   bills (so Finance can detect a frozen-vs-live divergence), plus member bill references.
   TU never reads Finance and never posts or repairs. Entry point:
   `php artisan integration:spp-reconcile-snapshot {batch_id}` (prints JSON).

2. **Finance — `SppSettlementBatchReconciliationService`** (`~/keuangan-mutiara-kasih/backend`):
   reconciles a snapshot against Finance's own `transactions` / `journals` / `journal_details`
   / `tu_event_coa_mappings` tables. Detection only.

The caller (out of scope here) transports the snapshot across the existing HMAC boundary and
invokes the Finance service — exactly how `ReconciliationEngine` is fed normalized TU records.

**Target invariants** (verified, exact decimal, no floats):

```
sum(TU eligible settlement amounts)  =  batch.total_amount      (live == frozen)
batch.total_amount                   =  sum(batch.breakdown buckets)
batch.total_amount                   =  Finance total debit
batch.total_amount                   =  Finance total credit
+ payment_count, bill_count, batch identity, idempotency key, Finance source traceability
```

---

## 3. Status Definitions

`App\Services\Integration\SppBatchReconciliationStatus` — minimal, deterministic vocabulary.
Uncertain states are **never** reported as MATCHED.

| Status | Meaning | Audit category |
|---|---|---|
| `MATCHED` | TU snapshot internally consistent AND Finance posted effect matches | — |
| `TU_MISMATCH` | Frozen snapshot diverges from live aggregation, or breakdown/count inconsistency | A. TU-side mismatch |
| `MISSING_FINANCE` | ACKNOWLEDGED but no Finance transaction for the idempotency key | B. Finance missing |
| `AMOUNT_MISMATCH` | Posted transaction amount differs from batch total | C. Finance amount mismatch |
| `IMBALANCE` | Journal debit total ≠ credit total, or debit total ≠ batch total | D. Finance imbalance |
| `BREAKDOWN_MISMATCH` | Finance debit lines don't match bucket→mapping expectation (wrong/missing account, or credit ≠ total) | E. Breakdown mismatch |
| `TRACE_MISMATCH` | Finance source identity (event_id/source_record_id/event_type) differs from batch identity | F. Traceability mismatch |
| `PENDING` | Not yet accounted (DRAFT/READY_TO_CLOSE/CLOSED-not-sent/SENT-not-posted/journal-not-posted) | G. Pending/unacknowledged |
| `DUPLICATE_FINANCE` | >1 Finance transaction for the same idempotency key | duplicate detection |
| `FINANCE_REJECTED` | Batch permanently rejected (FAILED_PERMANENT) — business rejection | delivery outcome |
| `INVALID` | Snapshot is structurally not a settlement batch | input validity |

---

## 4. TU-side Checks

`SppSettlementBatchReconciliationSnapshot::build()` (read-only) and the Finance
`checkTuInternal()` verify:
- **Frozen vs live:** `total_amount == live_total_amount`, `payment_count ==
  live_payment_count`, `bill_count == live_bill_count` (recomputed from member bills). Any
  divergence → `TU_MISMATCH`.
- **Breakdown sum:** Σ bucket `amount` == `total_amount`; Σ bucket counts == batch counts →
  else `TU_MISMATCH`.
- **Aggregation correctness** (covered by TU tests): MULTI one-payment/many-bills does not
  inflate `payment_count`; 4-month and 12-month MULTI aggregate correctly; CASH+ONLINE
  breakdown buckets sum to total; CLOSED and overflow snapshots are well-formed.

---

## 5. Finance-side Checks

`SppSettlementBatchReconciliationService::reconcile()`:
- **Lookup:** `Transaction` by `source_system='TU'` + exact `idempotency_key`.
  - 0 rows → `MISSING_FINANCE` (if ACKNOWLEDGED) or `PENDING` (if CLOSED/SENT/FAILED_RETRYABLE).
  - >1 rows → `DUPLICATE_FINANCE`.
- **Traceability (F):** `event_id`, `source_record_id`, `event_type` must equal the batch
  identity → else `TRACE_MISMATCH`.
- **Posted (G):** journal must exist and be `posted` → else `PENDING`.
- **Amount (C):** `txn.amount == total_amount` → else `AMOUNT_MISMATCH`.
- **Balance (D):** Σ journal debit == Σ journal credit == batch total → else `IMBALANCE`.

---

## 6. Breakdown Reconciliation

`checkBreakdown()` compares the operational breakdown buckets to the **Finance-owned** COA
mapping (TU never supplies COA IDs):
- For each bucket `(payment_method, provider)`, resolve `TuEventCoaMapping` → expected debit
  COA. Missing/inactive mapping → `BREAKDOWN_MISMATCH`.
- Sum posted debit lines per account; each mapped bucket account must be debited exactly its
  bucket sum (consolidated same-account buckets sum together — consistent with F-3). Wrong
  amount or wrong account → `BREAKDOWN_MISMATCH`.
- No posted debit may target an account with no corresponding bucket (catches a mis-posted
  debit even when the journal balances) → `BREAKDOWN_MISMATCH`.
- Credit total must equal batch total → else `BREAKDOWN_MISMATCH`.

This proves **CASH + ONLINE provider buckets = batch.total_amount** at the Finance line level.

---

## 7. Multi-Month Payment Reconciliation

`operational_payment_key` groups one operational payment (`PG:{midtrans_order_id}` for online,
`CASH:{bill_id}` for cash), so a MULTI order contributes **one** to `payment_count` regardless
of how many `StudentBill`s it settles, while each bill's amount still contributes to
`total_amount`. TU snapshot tests cover:
- **single bill** (1 payment / 1 bill),
- **4-month MULTI** (1 payment / 4 bills, total = 4 × amount),
- **12-month MULTI** (1 payment / 12 bills, total = 12 × amount).

Member references (`bill_month`, `bill_year`, `operational_payment_key`) are included in the
snapshot for traceability.

---

## 8. Traceability Checks

Finance-side, the chain is verified:
```
Finance Transaction (source_system, source_record_id=batch_id, event_id=batch_id,
                     idempotency_key=TU:SPP:…, event_type=spp.settlement_batch)
   → TU settlement batch (batch_id, idempotency_key)        [snapshot identity]
   → batch items (operational_payment_key, student_bill_id) [snapshot members]
   → operational payment → StudentBill (bill_month/bill_year)
```
TRACE_MISMATCH fires if the Finance transaction's stored identity fields differ from the
batch identity. **Limitation:** Finance stores no StudentBill rows (reference-only
architecture) — Finance can only verify the batch-level identity and amount, not per-bill
drill-down. Per-bill detail lives in the TU snapshot for the caller to inspect.

---

## 9. Overflow Batch Handling

Per BATCH-03-FIX, a period can have a canonical batch plus overflow batch(es) (e.g. main
Rp100m + late-settlement overflow Rp5m). Reconciliation treats **each batch independently by
its own idempotency key** — it does NOT assume one batch per period. Canonical key
`TU:SPP:{KIND}:{period_start}` and overflow key `TU:SPP:{KIND}:OVR:{batch_id}` are distinct
Finance lookups; a CLOSED/ACKNOWLEDGED overflow batch reconciles exactly like a normal batch.
Covered by `test_overflow_batch_reconciles_independently` (both MATCHED) and the TU snapshot
overflow test.

---

## 10. Batch Lifecycle Behavior

Finance comparison happens only when a posted accounting effect should exist:

| Batch status | Reconciliation result |
|---|---|
| `DRAFT`, `READY_TO_CLOSE` | `PENDING` (not yet closed/dispatched) |
| `CLOSED` (not yet sent) | `PENDING` (no Finance transaction yet) |
| `SENT` (in flight) | `PENDING` if no txn / journal not posted; full compare once posted |
| `ACKNOWLEDGED` | fully reconciled (`MATCHED` or a specific mismatch) |
| `FAILED_RETRYABLE` | `PENDING` (retry in flight) until posted |
| `FAILED_PERMANENT` | `FINANCE_REJECTED` |

ACKNOWLEDGED with no Finance transaction is an inconsistency → `MISSING_FINANCE` (not PENDING).

---

## 11. Read-only Safety

- The Finance service performs only `SELECT`s (`Transaction::where…get`, relation loads,
  mapping/COA lookups). It never inserts/updates/deletes, never posts journals, never mutates
  source state, never repairs, never retries.
- The TU snapshot builder performs only `SELECT`s.
- `test_reconcile_is_read_only_and_deterministic` asserts Transaction/Journal/JournalDetail
  counts are unchanged and two runs return identical results; the TU `build is read-only`
  test asserts the batch row is unchanged. Safe and deterministic to run repeatedly.

---

## 12. Failure Interpretation

- `TU_MISMATCH` → the TU snapshot itself is inconsistent (frozen ≠ live, or breakdown/count
  mismatch). Investigate TU batch integrity first; do NOT trust Finance comparison.
- `MISSING_FINANCE` → batch believes it was delivered but Finance has nothing — a lost
  posting. Investigate delivery/ingest.
- `AMOUNT_MISMATCH` / `IMBALANCE` → Finance posted a different amount or an unbalanced/
  short journal. Do not treat debit==credit as sufficient.
- `BREAKDOWN_MISMATCH` → the per-account debits don't match the operational buckets/mapping
  (wrong account, wrong split, or wrong credit). Correct total but wrong accounts.
- `TRACE_MISMATCH` → the Finance row carries a different identity than the batch (wrong link).
- `DUPLICATE_FINANCE` → more than one Finance transaction for the key (guarded by DB UNIQUE;
  detection retained for defense-in-depth).
- `PENDING` → not yet accounted; re-run after close/dispatch/posting.
- `FINANCE_REJECTED` → Finance permanently rejected; a bendahara decision is required.

No status auto-repairs; each is a detection signal for a human.

---

## 13. Implementation Details

**TU** (`~/sistem-tu-latest`):
- `app/Services/Integration/SppSettlementBatchReconciliationSnapshot.php` — snapshot builder.
- `routes/console.php` — `integration:spp-reconcile-snapshot {batch_id}` export command.
- No schema changes; reuses `spp_settlement_batches` / `spp_settlement_batch_items` /
  `student_bills`.

**Finance** (`~/keuangan-mutiara-kasih/backend`):
- `app/Services/Integration/SppBatchReconciliationStatus.php` — status vocabulary.
- `app/Services/Integration/SppSettlementBatchReconciliationService.php` — reconciliation
  service (`reconcile`, `checkTuInternal`, `checkBreakdown`, `norm`/`differs`, `result`).
- No schema changes; reads existing `transactions` / `journals` / `journal_details` /
  `tu_event_coa_mappings` / `coas`.

Exact-decimal arithmetic via `bcadd`/`bccomp`; no float money math.

---

## 14. Tests

**TU** (`tests/Feature/SppSettlementBatchTest.php`, +8 snapshot tests → 28 in file):
exact total match + traceability refs; MULTI one-payment/many-bills; 12-month MULTI;
breakdown buckets sum to total; CLOSED snapshot; overflow batch; read-only/deterministic.

**Finance** (`tests/Feature/SppBatchReconciliationTest.php`, 19 tests):
fully matched; consolidated debit lines match; TU frozen-vs-live mismatch; TU breakdown-sum
mismatch; TU payment-count divergence; missing Finance (acknowledged); pending (closed/draft);
duplicate Finance (subclass pattern) + DB-unique-blocks-second-insert; amount mismatch;
imbalance; breakdown mismatch (wrong account); trace mismatch; failed_permanent → rejected;
journal-not-posted → pending; invalid snapshot; overflow independent reconcile; read-only +
deterministic.

**Full suites (regression):**
- TU: **423 passed, 1 skipped.**
- Finance: **465 passed, 8 skipped** (PostgreSQL integration tests; need provisioned PG DB).

No existing tests weakened or removed.

---

## 15. Known Limitations

- **No cross-database coupling by design:** the Finance service consumes a normalized
  snapshot passed by a caller; the transport/orchestration that fetches the TU snapshot over
  HMAC and feeds it to Finance is out of scope (same pattern as `ReconciliationEngine`).
- **Per-bill drill-down:** Finance stores no StudentBill rows, so it verifies batch-level
  identity/amount/breakdown only; per-bill detail is in the TU snapshot for the caller.
- **DUPLICATE_FINANCE** is unreachable via normal insert (DB UNIQUE); detection retained for
  defense-in-depth and exercised via the established subclass test pattern.
- **READY_TO_CLOSE** remains unused by the batch lifecycle (documented in BATCH-03-FIX);
  reconciliation maps it to PENDING.
- No reconciliation report persistence/history and no UI (out of scope).

---

## 16. Next Task

**TU-FINANCE-BATCH-05 — reconciliation transport + report orchestration (optional) + batch
list/detail UI.** Recommended scope:
1. A caller/orchestrator that exports the TU snapshot (reusing
   `integration:spp-reconcile-snapshot`), transports it over the existing HMAC boundary, and
   invokes `SppSettlementBatchReconciliationService`, presenting the deterministic status to a
   bendahara.
2. Read-only batch list/detail UI (DRAFT/CLOSED/SENT/ACKNOWLEDGED/FAILED + reconciliation
   status) — display only; no posting, no repair.
3. Optional: persist reconciliation runs for audit history (new, minimal, read-mostly table)
   if bendahara wants a trail — a separate, explicitly-scoped decision.

Do not build auto-repair; reconciliation remains detection-only.
