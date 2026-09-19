# TU-FINANCE-BATCH-01 — SPP SETTLEMENT BATCH CONTRACT DESIGN

**Status:** DESIGN ONLY. No code, migrations, controllers, services, routes, UI, or schema
changes. The existing per-bill TU→Finance integration (`spp.payment`) is **NOT** removed or
altered.

**Confirmed accounting policy (governing this design):**
- Finance operates on **cash basis** for TU SPP.
- SPP payment treatment: **Dr Cash/Bank — Cr SPP/School Revenue**.
- **No receivable accounting. No deferred revenue.**
- COA names remain configurable by Finance/bendahara; **Finance owns COA mapping**.
- **TU never sends or hardcodes Finance COA IDs.**
- Existing `tu_event_coa_mappings` remains the mapping mechanism; **no new semantic-role
  registry** is created here.

Legend:
- **CURRENT** = verified existing behavior (cited to concrete class/table/migration).
- **TARGET** = proposed future design (not implemented).

Repositories:
- TU: `/home/muse/sistem-tu-latest`
- Finance: `/home/muse/keuangan-mutiara-kasih/backend`

---

## 1. Executive Summary

Today every settled `StudentBill` becomes exactly one Finance accounting transaction
(`kas_masuk` + posted journal). This design introduces a **consolidated SPP settlement
batch** (`spp.settlement_batch`) so that many payments → **one** Finance accounting
transaction per period, while TU retains all student/bill/payment detail.

Key design decisions:
- **Batch is the only thing Finance posts** once batch mode is enabled. Per-bill
  `spp.payment` outbox events become **operational-only / trace**, no longer
  accounting-posted. This is the single rule that prevents double-posting (see §9).
- **Cash basis** maps the batch to one balanced journal: `Dr Cash/Bank / Cr SPP Revenue`,
  resolved by Finance's existing `tu_event_coa_mappings` (semantic codes, never TU IDs).
- **Deterministic batch idempotency key** on both sides prevents Send-twice / retry /
  timeout from double-posting.
- **Migration is gated by a feature flag + a cut-over boundary** so historical per-bill
  rows and future batch rows never overlap in Finance accounting.

---

## 2. Current State

### 2.1 TU side (CURRENT)
- `StudentBill` (`app/Models/StudentBill.php`) is the canonical financial fact. Fields:
  `type`, `name`, `bill_month`, `bill_year`, `amount`, `status` (`UNPAID|PAID|PARTIAL`),
  `paid_at`, `payment_method` (`CASH|MIDTRANS|ONLINE`), `confirmed_by`, `midtrans_order_id`.
  Once PAID, `IMMUTABLE_WHEN_PAID` (`amount, student_id, type, bill_month, bill_year,
  pos_order_id`) is enforced by a model `updating` guard.
- `PaymentAttempt` (`app/Models/PaymentAttempt.php`) is the per-gateway-session ledger;
  `settled_at` = provider clock (differs from `paid_at` = application clock).
- Settlement paths:
  - Cash: `BillingPaymentService::payCash()` → PAID → `recordIntegrationEvent()`.
  - Online single: `PaymentSiswaController::callback()` (order `BILL-{id}-…`) → PAID →
    `recordSppPayment()` (guarded `if ($bill->type !== 'KANTIN')`).
  - Online multi: `PaymentSiswaController::handleMultiCallback()` (order
    `MULTI-{id1}_{id2}_…-…`) → loops bills, each PAID, each `recordSppPayment()` (MULTI-02:
    "each event carries THAT bill's own authoritative amount — never the shared MULTI total").
- Outbox: `IntegrationOutboxService::recordSppPayment()` writes
  `integration_outbox_events` (migration
  `2026_10_09_090000_create_integration_outbox_events_table.php`). Identity:
  `source_system='TU'`, `event_type='spp.payment'`, `source_record_id=student_bills.id`,
  `idempotency_key = TU:spp.payment:{bill_id}`. Constraints:
  `UNIQUE(source_system,event_type,source_record_id)` + `UNIQUE(idempotency_key)`.
  Event emitted only on VERIFIED PAID (`recordSppPayment` returns null unless
  `status==='PAID'`) and gated by an activation boundary (`sppEligible`).
- Delivery: `OutboxDispatcher` (console `integration:dispatch`, `routes/console.php`)
  claims rows, delivers via `HmacIntegrationTransport` (HMAC over raw body;
  `{timestamp}\n{METHOD}\n{path}\n{sha256(body)}`; 2xx→DELIVERED, 408/429/5xx→RETRYABLE,
  4xx→PERMANENT). State machine `PENDING→PROCESSING→DELIVERED|FAILED_RETRYABLE|FAILED_PERMANENT`
  (MAX_ATTEMPTS=10).

### 2.2 Finance side (CURRENT)
- Ingest `POST /api/integration/tu/events` (`routes/api.php`) → `TuHmacAuthentication`
  middleware → `TuIntegrationController::store` → `TuFinanceIntegrationService::process`.
- Flow: `TuFinanceEvent::fromArray` (contract validation) → idempotency check
  (`Transaction` `UNIQUE(source_system,idempotency_key)`, migration
  `2026_10_07_090000_add_source_traceability_to_transactions_table.php`) →
  `resolveMapping` via `TuEventCoaMapping::resolve(event_type, payment_method, provider)`
  → `AccountingPeriodService::requireOpenPeriod(date)` → `JournalService::recordKasMasuk`
  → Transaction + draft Journal + details + post → audit.
- Mapping table `tu_event_coa_mappings` (migration
  `2026_10_08_090000_create_tu_event_coa_mappings_table.php`): key
  `(event_type, payment_method, provider)` → `debit_coa_code`, `credit_coa_code`
  (**semantic codes**, `UNIQUE(event_type,payment_method,provider)`). Missing →
  `MAPPING_REQUIRED`, nothing posted.
- Each `spp.payment` → **one** `Transaction` (`type='kas_masuk'`) + **one** posted
  `Journal`: **Dr cash/bank asset, Cr revenue** (cash basis). Posted journals immutable
  (`Journal`/`JournalDetail` guards).
- `AccountingPeriod` (`app/Models/AccountingPeriod.php`): monthly, `open|closed`, one-way
  `close()` (no reopen).
- Idempotency primitives also exist for interactive writes: `IdempotencyService`
  (`scope,key,request_hash` → reserve + run + persist response atomically).

### 2.3 Net effect (CURRENT)
One gateway `MULTI-…` payment over N bills → N `spp.payment` events → N Finance
transactions/journals. 100 parents settling 250 bill-months → 250 Finance transactions.
All cash-basis `Dr Bank / Cr Revenue`.

---

## 3. Target State

- TU continues to own **all** detail: students, StudentBills, bill/month allocation,
  payment transactions, gateway/provider references, multi-bill grouping.
- A new TU-domain aggregate — the **SPP settlement batch** — groups many settled receipts
  over a period into **one** Finance-facing object.
- Finance receives **one** accounting settlement per batch → **one** `kas_masuk`
  transaction + **one** balanced journal (`Dr Cash/Bank / Cr SPP Revenue`).
- Per-bill `spp.payment` events are retained in TU as **trace/audit** but are **not**
  accounting-posted once batch mode is enabled (see §9).
- The batch is immutable after close, idempotent end-to-end, and reconciles to TU detail
  and to the Finance journal.

---

## 4. SPP Settlement Batch Definition

TARGET concept: `spp.settlement_batch`. (No schema here — field semantics only.)

| Field | Semantics |
|---|---|
| `batch_id` | Stable UUID, generated once at batch creation; never regenerated on retry. Mirrors `event_id` on outbox. |
| `idempotency_key` | Deterministic, unique per (source, category, period). Recommended shape: `TU:SPP:{PERIOD_KIND}:{period_start}` e.g. `TU:SPP:DAILY:2026-09-15`. The **final guard** against duplicate Finance posting. |
| `source_system` | Always `'TU'`. |
| `category` | Always `'SPP'` (only SPP receipts in scope). |
| `period_kind` | `DAILY | WEEKLY | MONTHLY` (chosen per §11). |
| `period_start` | Inclusive period start date (Asia/Jakarta). |
| `period_end` | Inclusive period end date (Asia/Jakarta). |
| `total_amount` | Sum of **settled receipt amounts** in the period (decimal 12,2, exact string; no float). |
| `payment_count` | Number of **distinct operational payments** (gateway transactions + cash settlements) in the batch. |
| `bill_count` | Number of **StudentBill** rows settled by those payments. (`bill_count ≥ payment_count`.) |
| `status` | Lifecycle state (§5). |
| `occurred_at` | Business timestamp the batch represents (typically `period_end` end-of-day, Asia/Jakarta). Basis for the Finance journal date. |
| `closed_at` | When the batch was closed/finalized (set once, immutable). |
| `sent_at` | When first dispatched to Finance. |
| `acknowledged_at` | When Finance confirmed posting. |
| `currency` | `IDR` (implicit single-currency domain). |

**Identity rules (TARGET):**
- `batch_id` and `idempotency_key` are immutable once created.
- One canonical batch per `(source_system, category, period_kind, period_start)` —
  enforced conceptually by a uniqueness rule, mirroring the outbox `UNIQUE` constraints.
- Amounts are deterministic snapshots computed at close time; a closed batch never
  recomputes (mirrors savings-closing immutability, `DailySavingsClosingService`).

---

## 5. Batch Lifecycle

TARGET lifecycle (evaluated, not implemented):

```
DRAFT → READY_TO_CLOSE → CLOSED → SENT → ACKNOWLEDGED
                          │         │
                          │         └─→ FAILED_RETRYABLE ─→ (retry) ─→ SENT
                          │         └─→ FAILED_PERMANENT (parked; manual review)
                          └─ (reopen NOT allowed; corrections go to a NEW batch, §13)
```

- **DRAFT** — batch is open; eligible settled receipts accumulate. Editable/recomputable.
- **READY_TO_CLOSE** — period has ended; batch is complete and awaiting operator/auto close.
- **CLOSED** — snapshot frozen (`total_amount`, counts, membership). Immutable. Not yet sent.
- **SENT** — dispatched to Finance; awaiting acknowledgement. Idempotent re-send allowed.
- **ACKNOWLEDGED** — Finance confirmed it posted the journal. Terminal success state.
- **FAILED_RETRYABLE** — transient transport/5xx/timeout; safe to retry (Finance ingest is
  idempotent on `idempotency_key`).
- **FAILED_PERMANENT** — Finance rejected (4xx business/validation, e.g.
  `MAPPING_REQUIRED`, closed period). No auto-retry; requires correction + a new batch.

Precedents in CURRENT code for each transition:
- Delivery state machine: `OutboxDispatcher` (`PENDING→PROCESSING→DELIVERED|FAILED_*`).
- Immutable-once-closed snapshot: `StudentSavingsDailyClosing` (no reopen/update/delete).
- One-way close: `AccountingPeriodService::close()`.
- Acknowledgement: Finance ingest returns `PROCESSED`/`DUPLICATE` (2xx) — that 2xx **is**
  the acknowledgement; `HmacIntegrationTransport::classify` already treats both as delivered.

**No reopen:** a CLOSED/SENT/ACKNOWLEDGED batch is never reopened (consistent with both
`savings daily closing` and `AccountingPeriod`). Corrections are new batches (§13).

---

## 6. Aggregation Rules

TARGET rules for grouping **TU-originated SPP receipts only** into a batch:

**Included:** `StudentBill` rows where `type='SPP'`, `status='PAID'`, `payment_method IN
(CASH, MIDTRANS, ONLINE)`, and `paid_at` (application clock, Asia/Jakarta) falls within
`[period_start, period_end]`, and not already a member of a prior CLOSED/SENT/ACKNOWLEDGED
batch.

**Excluded:** `KANTIN`, `PAKET`, `GEDUNG`, `SERAGAM`, POS cash sales (`pos.cash_sale` is a
separate event stream), any `UNPAID`/`PARTIAL` bill, and any bill already batched.

**Grain of money = the operational payment, not the bill.** A single operational payment
(e.g. one `MULTI-…` gateway transaction) is counted **once** in `payment_count` and its
amount is the **sum of its constituent settled bill amounts** (which equals the gateway
`gross_amount` — enforced today by the MULTI amount-integrity check in
`handleMultiCallback`). Cash settlements are one payment each (`payment_method='CASH'`,
grouped by `confirmed_by`/`paid_at` as individual receipts).

**Mapping of the examples:**

| Scenario | Operational payments | StudentBills | payment_count | bill_count | total_amount |
|---|---|---|---|---|---|
| 1 parent pays 1 month (500k) | 1 | 1 | 1 | 1 | 500.000 |
| 1 parent pays 4 months (1 MULTI txn) | 1 | 4 | 1 | 4 | 2.000.000 |
| 1 parent pays 12 months (1 MULTI txn) | 1 | 12 | 1 | 12 | 6.000.000 |
| 100 parents, mixed, one day | 100 | e.g. 250 | 100 | 250 | 125.000.000 |

All of these aggregate into **one** batch for the period → **one** Finance accounting
transaction.

**Operational payment identity (for `payment_count` and trace):** the distinct
`midtrans_order_id` for online, or a per-cash-receipt grouping key for cash. The batch
stores the set of contributing payment references (or a link table to the settled
`StudentBill` IDs) so each payment traces back (§8).

---

## 7. Accounting Payload

TARGET Finance-facing batch payload (envelope + payload, mirroring the existing
`spp.payment` contract shape so `TuFinanceEvent`-style validation and
`HmacIntegrationTransport` can be reused):

```
event_id          = batch_id (UUID)
event_type        = "spp.settlement_batch"
event_version     = "1.0"
contract_version  = "1.0"
source_system     = "TU"
source_record_id  = batch_id                // batch is the source fact
idempotency_key   = "TU:SPP:{PERIOD_KIND}:{period_start}"
occurred_at       = ISO-8601 +07:00 (batch business date)
payload:
  category        = "SPP"
  period_kind     = "DAILY|WEEKLY|MONTHLY"
  period_start    = "YYYY-MM-DD"
  period_end      = "YYYY-MM-DD"
  total_amount    = "125000000.00"          // exact 2dp string
  payment_count   = 100
  bill_count      = 250
  payment_methods = ["CASH","MIDTRANS","ONLINE"]  // informational
  description     = "SPP settlement 2026-09-15 (100 payments, 250 bills)"
```

**Deliberately absent from the payload:**
- **No COA IDs** — Finance maps via `tu_event_coa_mappings`. (Per policy + contract §22.)
- **No student IDs / bill IDs required for accounting** — Finance does not need individual
  student identity to post `Dr Cash/Bank / Cr SPP Revenue`. (Student/bill references stay
  in TU for trace; Finance keeps only the batch reference.)

**Finance mapping (TARGET):** a mapping row keyed by `(event_type='spp.settlement_batch',
payment_method, provider)` → `debit_coa_code` (cash/bank asset), `credit_coa_code` (SPP
revenue). Because a batch can mix cash and online receipts, the design supports either:
- (a) one mapping per method/provider with the batch split into per-method sub-journals, or
- (b) a single clearing/bank debit for the consolidated total.
This is a Finance mapping-detail choice for the bendahara; the batch contract is
agnostic and supplies the per-method breakdown if option (a) is preferred.

**Resulting Finance posting (TARGET, cash basis):**
```
Dr Cash/Bank (asset)        total_amount
   Cr SPP Revenue (revenue)            total_amount
```
One balanced journal per batch (or per method within a batch if 7(a) is chosen), resolved
by Finance from `tu_event_coa_mappings`. No COA IDs cross the wire.

---

## 8. Traceability

TARGET reference chain (top-down), with the IDs that must exist at each layer:

```
Finance Transaction/Journal
    source_system        = "TU"
    source_record_id     = batch_id
    event_id             = batch_id
    idempotency_key      = "TU:SPP:{PERIOD_KIND}:{period_start}"
    event_type           = "spp.settlement_batch"
    │  (columns already exist on transactions — migration
    │   2026_10_07_090000_add_source_traceability_to_transactions_table.php)
    ▼
TU Settlement Batch
    batch_id, idempotency_key, period_*, total_amount, payment_count, bill_count
    │  (batch ↔ member receipts link)
    ▼
TU Payment transaction(s)
    online:  midtrans_order_id (BILL-…/MULTI-…), PaymentAttempt.transaction_id
    cash:    StudentBill.confirmed_by + paid_at (operator receipt)
    ▼
StudentBill allocation(s)
    student_bills.id, student_id, bill_month/bill_year, amount
    ▼
Student / month(s)
```

Required references per layer:
- **Finance → batch:** `idempotency_key` (unique) + `source_record_id`/`event_id` =
  `batch_id`. Sufficient to answer "which TU batch created this journal."
- **Batch → payments:** a membership link from batch to the contributing
  `StudentBill` IDs (and thereby their `midtrans_order_id` / cash receipt identity).
- **Payment → bills:** `PaymentAttempt.student_bill_id` and
  `StudentBill.midtrans_order_id` (already exist, CURRENT).
- **Bill → student/month:** `StudentBill.student_id`, `bill_month`, `bill_year`
  (already exist, CURRENT).

Finance never needs student-level identity for accounting; the audit trail to students
lives entirely in TU, reachable through `batch_id`.

---

## 9. Double-Posting Prevention

**The core risk:** the same SPP money being posted to Finance **twice** — once via a
per-bill `spp.payment` event and once via the consolidated batch.

**TARGET rule (chosen design):**

> **Once batch mode is enabled, per-bill `spp.payment` events become operational-only
> (trace) and are NEVER accounting-posted to Finance. Only `spp.settlement_batch` events
> are accounting-posted.**

Rationale for choosing "batch-only posting" over "keep both":
- The existing architecture already separates the **event** from the **posting decision**
  on the Finance side: `TuFinanceIntegrationService` only posts events it can map and that
  pass idempotency. The cleanest cut is at the Finance ingest boundary.
- Keeping both as accounting-posted would require Finance to net/subtract per-bill rows
  against the batch — fragile and unnecessary under cash basis.

**Concrete guard layers (TARGET), reusing CURRENT mechanisms:**
1. **TU emission gate (outbox).** When batch mode is on, `recordSppPayment` continues to
   write the per-bill outbox row for trace but the event is flagged/routed so it is **not
   dispatched for accounting** (e.g. a delivery channel/status that the dispatcher skips).
   Only `spp.settlement_batch` rows are dispatched for posting.
2. **Finance idempotency.** `UNIQUE(source_system, idempotency_key)` on `transactions`
   (exists, CURRENT) ensures one posting per batch key. Per-bill keys
   (`TU:spp.payment:{bill_id}`) and batch keys (`TU:SPP:{PERIOD_KIND}:{period_start}`)
   are **different keyspaces**, so a batch key can never collide with a per-bill key.
3. **Finance event-type gate.** Finance maps/posts only `spp.settlement_batch` once batch
   mode is enabled; `spp.payment` mapping rows are deactivated or routed to a no-post
   handling. A `spp.payment` arriving after cut-over is acknowledged as `DUPLICATE`/ignored
   for accounting.
4. **One batch per period.** Uniqueness on `(source_system, category, period_kind,
   period_start)` prevents two batches for the same period.

**No double-posting invariant:** for any settled SPP rupiah, exactly **one** of {per-bill
event, batch} is accounting-posted in Finance, decided by the cut-over boundary (§14).

---

## 10. Multi-Month Example

Parent A pays Jan–Apr (4 × 500k = **Rp2.000.000**) in **one** Midtrans transaction
`MULTI-11_12_13_14-x7f2aa-1789000000`.

**TU records (CURRENT behavior, unchanged):**
- 4 `StudentBill` rows (ids 11,12,13,14), each `type=SPP`, `amount=500000`,
  `bill_month` = 1..4, all → `status=PAID`, `paid_at=now()`,
  `payment_method=MIDTRANS`, `midtrans_order_id=MULTI-11_12_13_14-…`.
- 4 `PaymentAttempt` rows (one per bill), same `order_id`, each `gross_amount=500000`,
  all `settlement`.
- Amount integrity check (CURRENT, `handleMultiCallback`): provider `gross_amount`
  (2.000.000) must equal `sum(bill.amount)` (4×500k) before any PAID mutation.

**Settlement batch aggregation (TARGET):**
- These 4 bills belong to **1 operational payment** → contribute **1** to
  `payment_count`, **4** to `bill_count`, and **2.000.000** to `total_amount`.
- The payment is counted once even though it settles 4 bills.

**Finance posting (TARGET, cash basis):**
- This payment is one component of the day's batch. If the day's batch total is
  Rp125.000.000 (100 payments / 250 bills), Finance posts **one** journal:
  `Dr Bank 125.000.000 / Cr SPP Revenue 125.000.000`.
- Parent A's 2.000.000 is **not** a separate Finance transaction and is **not** split into
  4 journals. Under cash basis, one consolidated journal suffices; the Jan–Apr allocation
  stays in TU as trace (§8). One payment → one batch line; one batch → one journal.

---

## 11. Daily vs Weekly vs Monthly

**Do not choose blindly — the period is a [BUSINESS/POLICY] confirmation.** Comparison
against concrete system anchors:

| Axis | Daily | Weekly | Monthly |
|---|---|---|---|
| **TU operational closing** | Matches the existing daily-closing precedent (`StudentSavingsDailyClosing`); tightest audit granularity | Coarser; no existing weekly precedent | Coarsest operational granularity |
| **Finance `AccountingPeriod`** | Period is monthly (`AccountingPeriod.year/month`); a daily batch posts many sub-month journals into one open month — valid, but period-close lags the last daily batch | Same as daily (sub-month) | **Aligns 1:1 with the monthly `AccountingPeriod`** — cleanest period close |
| **Auditability** | Highest (smallest blast radius per batch; easiest to isolate a day) | Medium | Lowest granularity per batch |
| **Reconciliation** | Easiest per-batch (small totals, fast to tie to a day's gateway/cash) | Medium | Largest totals; harder to isolate a discrepancy |
| **Volume of Finance journals** | ~30/month | ~4–5/month | 1/month (fewest) |
| **Failure recovery** | Small re-send unit | Medium | Large re-send unit |

**Tension:** Finance periods are monthly, so **monthly** maps cleanly onto
`AccountingPeriod`; but **daily** matches the existing operational-closing precedent and
gives the best auditability/reconciliation granularity. This is a **business decision**
(cash-handling cadence, reconciliation effort, audit needs), not determinable from code.
**No period is chosen in this design** — `period_kind` is parameterized so any of the
three can be adopted without contract changes.

---

## 12. Reconciliation

TARGET — batch total must reconcile to four anchors:

1. **StudentBills:** `total_amount == SUM(amount)` over member `StudentBill` rows
   (`type=SPP`, `status=PAID`, `paid_at ∈ period`, not previously batched). `bill_count ==
   COUNT(members)`.
2. **PaymentAttempts:** for online members, `SUM(gross_amount)` of `settlement` attempts
   whose `student_bill_id ∈ members` equals the online portion; per-MULTI amount integrity
   already guarantees `SUM(bill.amount) == gateway gross_amount` (CURRENT).
3. **Actual received payment transactions:** distinct `midtrans_order_id` /
   cash-receipt count == `payment_count`; per-payment sums tie to gateway settlement
   records / cash log.
4. **Finance posted journal:** batch `total_amount` == the posted `kas_masuk` transaction
   amount on the Finance side, matched by `idempotency_key`. This mirrors the CURRENT
   `ReconciliationEngine` per-event amount comparison, lifted to batch grain
   (`batch total ↔ Finance transaction amount`).

A reconciliation run reports per-batch status: `MATCHED | AMOUNT_MISMATCH | PENDING |
MISSING_IN_FINANCE | ORPHAN`. Detection only — never auto-repair (consistent with the
CURRENT `ReconciliationEngine` design philosophy).

---

## 13. Failure / Retry / Idempotency

TARGET behaviors:

- **Finance unavailable (timeout/5xx):** batch → `FAILED_RETRYABLE`; safe to retry. Finance
  ingest is idempotent on `idempotency_key`, so a retry that arrives after a prior
  successful-but-unacknowledged post returns `DUPLICATE` (2xx) → treated as delivered
  (mirrors `HmacIntegrationTransport::classify`).
- **Same batch sent twice:** the deterministic `idempotency_key` +
  Finance `UNIQUE(source_system, idempotency_key)` → second send is `DUPLICATE`, no second
  journal. TU marks `ACKNOWLEDGED`.
- **Same payment appears in two batches:** prevented by the membership rule (a settled bill
  already in a CLOSED/SENT/ACKNOWLEDGED batch is excluded from later batches) + the one
  batch-per-period uniqueness. If it somehow occurs, the second batch's total would not
  reconcile (§12) and is flagged, not posted.
- **Batch CLOSED but send fails:** batch stays `CLOSED`/`FAILED_RETRYABLE`; the snapshot is
  frozen; retry re-sends the same payload (idempotent). No recomputation.
- **Batch ACKNOWLEDGED:** terminal. Any later correction is a **new** adjusting batch, never
  an edit (consistent with immutable posted journals and no-reopen precedents).
- **Underlying payment data changes after close:** PAID `StudentBill` core fields are
  immutable (`IMMUTABLE_WHEN_PAID`, CURRENT), so settled amounts cannot change. If a
  business-level correction is ever needed (e.g. a reversal the current system does not
  support), it is represented as a **new correcting batch** in a later open period — never
  by mutating a closed batch or a posted Finance journal.

---

## 14. Migration Strategy

TARGET transition from CURRENT per-bill posting to TARGET batch posting **without
duplicate accounting**:

1. **Feature flag + cut-over boundary.** Introduce a batch-mode flag (config) and an
   explicit **activation boundary timestamp** (the same "forward-only activation" concept
   already used by `sppEligible`/`settlementEligible` in `IntegrationOutboxService`).
   Before the boundary: per-bill events post (CURRENT). After: only batches post.
2. **No retroactive batching.** Bills settled before the boundary were already posted
   per-bill; they are **never** re-batched (avoids double-count). This matches the existing
   "no backfill / no fabricated history" rule.
3. **Per-bill events become trace-only at cut-over.** From the boundary, `recordSppPayment`
   still records the per-bill outbox row (trace) but it is no longer accounting-posted
   (§9 layer 1/3). Finance deactivates `spp.payment` posting for post-boundary events.
4. **First batch starts clean.** The first batch's `period_start` is the first period at or
   after the boundary; it includes only bills settled at/after the boundary that were not
   already per-bill posted.
5. **Keyspace separation guarantees no collision.** Per-bill keys
   (`TU:spp.payment:{bill_id}`) and batch keys (`TU:SPP:{PERIOD_KIND}:{period_start}`)
   cannot collide on the Finance `UNIQUE(source_system,idempotency_key)` guard.
6. **Verification before full cut-over:** reconcile that (per-bill posted total before
   boundary) + (batch posted total after boundary) == total settled SPP, with zero overlap.
7. **Rollback:** disabling the flag reverts to per-bill posting for new settlements;
   already-posted batches remain (immutable posted journals).

**Do not implement this now.** No migration/controller/service/route changes are made.

---

## 15. Open Decisions

Business/policy confirmations required before implementation:
1. **Batch period** (`DAILY`/`WEEKLY`/`MONTHLY`) — see §11; not chosen here.
2. **Cash vs online split in Finance:** one consolidated journal per batch, or per-method
   sub-journals (§7 option a vs b) — bendahara mapping preference.
3. **Which COA codes** the bendahara assigns for SPP revenue and the cash/bank/clearing
   accounts (Finance-owned; TU never supplies IDs).
4. **Operational closing cadence** (who closes the batch, auto vs manual) and how it aligns
   with the existing daily-closing habit and the monthly `AccountingPeriod` close.
5. **Confirmation** that per-bill `spp.payment` should become trace-only (the §9 rule), or
   whether Finance should keep a per-bill detail feed for non-accounting reporting.

---

## 16. Next Implementation Task

**Proposed next task (after §15 decisions are confirmed):**

> **TU-FINANCE-BATCH-02 — Implement the SPP settlement batch (schema + lifecycle +
> dispatch), behind a feature flag.**
> - Create the TU batch aggregate + membership link (StudentBills ↔ batch) with the
>   deterministic idempotency key `TU:SPP:{PERIOD_KIND}:{period_start}`.
> - Implement the lifecycle state machine (DRAFT→…→ACKNOWLEDGED) and a dispatcher step for
>   `spp.settlement_batch` reusing `HmacIntegrationTransport`.
> - Add the Finance batch-ingest path that posts one balanced `Dr Cash/Bank / Cr SPP
>   Revenue` journal via `JournalService::recordKasMasuk` +
>   `AccountingPeriodService::requireOpenPeriod`, guarded by
>   `UNIQUE(source_system,idempotency_key)`, mapping via `tu_event_coa_mappings`.
> - Implement the §9 cut-over gate so per-bill events become trace-only once enabled.
> - Add batch reconciliation (§12) and audit entries.

**Explicitly out of scope / NOT done in TU-FINANCE-BATCH-01 (this task):** all of the
above implementation; any migrations, controllers, services, routes, UI, schema, or
semantic-role registry; any change to the existing per-bill integration.
