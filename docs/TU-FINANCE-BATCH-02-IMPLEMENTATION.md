# TU-FINANCE-BATCH-02 — SPP SETTLEMENT BATCH IMPLEMENTATION

**Status:** Implemented behind a feature flag. Default behavior (flag disabled) is
**unchanged** — the per-bill `spp.payment` integration continues to post exactly as before.
Reference design: `docs/TU-FINANCE-BATCH-01-DESIGN.md`.

Accounting policy: **cash basis** — `Dr Cash/Bank … Cr SPP Revenue`. COA names are
bendahara-configurable; **Finance owns COA mapping**; **TU never sends Finance COA IDs**.

---

## 1. Schema (TU)

### `spp_settlement_batches`
Migration: `2026_10_10_090000_create_spp_settlement_batches_table.php`

| Column | Purpose |
|---|---|
| `batch_id` (uuid) | stable batch identity, generated once |
| `idempotency_key` (unique) | `TU:SPP:{PERIOD_KIND}:{period_start}` — deterministic anti-duplicate guard |
| `source_system` | `'TU'` |
| `category` | `'SPP'` |
| `period_kind` | `DAILY | WEEKLY | MONTHLY` (from `integration.spp_batch_period_kind`) |
| `period_start` / `period_end` (date) | period boundaries (inclusive) |
| `total_amount` (decimal 14,2) | sum of settled member bill amounts |
| `payment_count` / `bill_count` (int) | distinct operational payments / member bills |
| `breakdown` (json) | per-`(payment_method, provider)` bucket: `{payment_method, provider, amount, payment_count, bill_count}` |
| `status` | lifecycle (below) |
| `occurred_at` / `closed_at` / `sent_at` / `acknowledged_at` | business + lifecycle timestamps |
| `attempt_count`, `last_error` | delivery bookkeeping |

Uniqueness: `UNIQUE(source_system, category, period_kind, period_start)` (one canonical
batch per period) + `UNIQUE(idempotency_key)`.

### `spp_settlement_batch_items`
Migration: `2026_10_10_090001_create_spp_settlement_batch_items_table.php`

Links each settled `StudentBill` to exactly one batch. Stores only the link + operational
settlement bucket + operational payment identity — **no duplicated StudentBill financial
data**. `UNIQUE(student_bill_id)` makes cross-batch duplication impossible at the DB level.
`operational_payment_key` groups one operational payment: online → `PG:{midtrans_order_id}`
(a `MULTI-…` order = ONE payment), cash → `CASH:{bill_id}`.

---

## 2. Lifecycle

`DRAFT → READY_TO_CLOSE → CLOSED → SENT → ACKNOWLEDGED`, with `FAILED_RETRYABLE` /
`FAILED_PERMANENT`. Implemented in `App\Models\SppSettlementBatch` +
`App\Services\Integration\SppSettlementBatchService` and the outbox dispatcher:

- **DRAFT** batches are recomputed as members arrive (`recordEligibleSettlement` → `recompute`).
- **close()** freezes the snapshot → `CLOSED` (idempotent; empty batch stays DRAFT).
- A CLOSED batch's accounting snapshot (`total_amount`, counts, `breakdown`, identity,
  period, `occurred_at`) is **immutable** — enforced by a model `updating`/`deleting` guard
  (`LogicException`). **No reopen; corrections are new batches.**
- `SENT` / `ACKNOWLEDGED` / `FAILED_*` are synced from the outbox delivery outcome
  (`OutboxDispatcher::syncBatchStatus`) — operational timestamps only, never the snapshot.

Console: `integration:spp-close-batches` closes open batches and queues their
`spp.settlement_batch` outbox events (idempotent `firstOrCreate`).

---

## 3. Feature flag

Config `config/integration.php` (env-driven):

- `TU_SPP_BATCH_ENABLED` → `integration.spp_batch_enabled` (default **false**)
- `TU_SPP_BATCH_ACTIVATED_AT` → `integration.spp_batch_activated_at` (ISO-8601 +07:00)
- `TU_SPP_BATCH_PERIOD_KIND` → `integration.spp_batch_period_kind` (`DAILY` default)

**Disabled (default):** nothing in this task runs; per-bill `spp.payment` posts unchanged.
**Enabled:** newly eligible SPP settlements become batch members (forward-only — only
`paid_at` at/after `spp_batch_activated_at`; **no historical backfill**), and per-bill
`spp.payment` outbox rows are still recorded for trace but are **not dispatched for
accounting** (see §5/§6). Existing per-bill `spp.payment` events recorded **before** the
flag flips remain dispatchable (they predate batch mode); the gate only applies while the
flag is enabled.

---

## 4. Event payload (`spp.settlement_batch`)

Built by `SppSettlementBatchService::buildOutboxEvent`, delivered by the existing
`OutboxDispatcher` + `HmacIntegrationTransport` (no second transport). Deterministic
`event_id = batch_id`, `idempotency_key = TU:SPP:{PERIOD_KIND}:{period_start}`.

```
envelope: event_id, event_type=spp.settlement_batch, event_version=1.0,
          contract_version=1.0, source_system=TU, source_record_id=batch_id,
          idempotency_key, occurred_at
payload:  category=SPP, period_kind, period_start, period_end,
          total_amount, payment_count, bill_count,
          breakdown=[{payment_method, provider, amount, payment_count, bill_count}],
          description
```

**No COA IDs, no student/bill IDs in the payload** — Finance needs neither to post the
journal; student/bill detail stays in TU for trace.

---

## 5. Payment-method / settlement breakdown (guardrail)

Finance's mapping (`TuEventCoaMapping`) resolves a debit account by
`(event_type, payment_method, provider)`. A mixed-method batch must NOT be collapsed into
one debit. The batch therefore carries a **per-bucket breakdown**, and Finance posts **one
balanced journal with one debit line per bucket** plus a single SPP revenue credit:

```
Dr Cash        40.000.000   (bucket CASH)
Dr Bank BCA    50.000.000   (bucket MIDTRANS/midtrans)
Dr Bank BSI    35.000.000   (bucket ONLINE/flip → mapped to BSI by Finance)
   Cr SPP Revenue          125.000.000
```

`JournalService::recordBatchSettlement` adds one debit `JournalDetail` per bucket and one
credit line, then posts through the same trusted `post()` boundary (exact-decimal balance
check). Buckets that share the same settlement account naturally produce separate lines to
that account (no silent cross-account collapse). The single shared **revenue** credit uses
the first bucket's `credit_coa_code` — see §11 Known limitations.

---

## 6. Finance ingest

`POST /api/integration/tu/events` (`TuIntegrationController::store`) routes
`event_type === 'spp.settlement_batch'` to `TuSettlementBatchService::process`; all other
events keep the existing `TuFinanceIntegrationService` path.

- **Validation** — `TuSettlementBatchEvent::fromArray`: envelope (event/type/version/
  source_system/idempotency), `total_amount` format, counts, `breakdown` presence and
  per-line shape, and **cross-field consistency** (Σ breakdown.amount == total_amount;
  Σ payment_count / bill_count match; bill_count ≥ payment_count). Deterministic codes:
  `INVALID_EVENT`, `INVALID_AMOUNT`, `INVALID_BREAKDOWN`, `UNSUPPORTED_EVENT_VERSION`.
- **Mapping** — one `TuEventCoaMapping` row per bucket keyed on
  `event_type='spp.settlement_batch'`; missing bucket or revenue mapping →
  `MAPPING_REQUIRED`, nothing posted.
- **Posting** — `JournalService::recordBatchSettlement` with
  `AccountingPeriodService::requireOpenPeriod(date)` (date derived server-side from
  `occurred_at`). Closed/missing period → `STATE_NOT_ELIGIBLE`.

### Finance mapping seeding (operational note)
Batch mappings are Finance-owned and must be seeded per settlement bucket, e.g.:
`(spp.settlement_batch, CASH, null) → debit 1-101, credit 4-101`,
`(spp.settlement_batch, MIDTRANS, midtrans) → debit 1-102, credit 4-101`,
`(spp.settlement_batch, ONLINE, flip) → debit <BSI code>, credit 4-101`.
The seeded COA (`CoaSeeder`) has `1-101`, `1-102`, `4-101` but **no Bank BSI account** —
a bendahara would add it (configurable name/code) before mapping an ONLINE/flip bucket.

---

## 7. Idempotency

- Deterministic batch key `TU:SPP:{PERIOD_KIND}:{period_start}` on both sides.
- Finance: existing `transactions UNIQUE(source_system, idempotency_key)` — replay and
  concurrent duplicate delivery post **exactly one** transaction/journal; the second returns
  `DUPLICATE`.
- TU outbox: `UNIQUE(idempotency_key)` + `firstOrCreate` on `(source_system, event_type,
  source_record_id)` — re-queuing a closed batch never creates a second event.

---

## 8. Traceability

```
Finance Transaction/Journal
   source_system=TU, event_type=spp.settlement_batch,
   source_record_id=batch_id, event_id=batch_id,
   idempotency_key=TU:SPP:{PERIOD_KIND}:{period_start}     (existing source columns)
        ↓
TU spp_settlement_batches (batch_id, idempotency_key, period_*, totals)
        ↓ spp_settlement_batch_items (operational_payment_key)
operational payment  (PG:{midtrans_order_id} | CASH:{bill_id})
        ↓ student_bill_id
StudentBill(s) → student_id, bill_month/bill_year
```

---

## 9. Migration boundary

Forward-only. `spp_batch_activated_at` is the explicit activation boundary: only SPP
payments settled (`paid_at`) at/after it become batch members; pre-boundary (historical)
bills are **never** batched and existing historical per-bill Finance transactions are
untouched. Per-bill keys (`TU:spp.payment:{bill_id}`) and batch keys (`TU:SPP:…`) are
different keyspaces and can never collide on the Finance idempotency guard.

---

## 10. Reconciliation (minimum checks, no auto-repair)

- **Batch integrity (TU):** `total_amount == Σ member bill.amount`; `payment_count` ==
  distinct `operational_payment_key`; enforced at close (`recompute`) and validated again
  on Finance ingest (breakdown Σ == total). No unpaid bills (membership requires PAID), no
  out-of-period bills (period derived from `paid_at`), no cross-batch duplication
  (`UNIQUE(student_bill_id)`).
- **Finance balance:** `recordBatchSettlement` posts through `JournalService::post()`, which
  enforces Σdebit == Σcredit with exact decimal arithmetic — so Σ debit == Σ credit ==
  batch total. A mismatch can never post.

---

## 11. Known limitations / open points (documented, not invented)

1. **Shared revenue credit.** Under the current cash-basis design the batch posts a single
   SPP revenue credit taken from the **first** bucket's `credit_coa_code`. If a bendahara
   ever maps different buckets to different revenue accounts, that is a new policy decision
   and out of scope here (would need per-bucket credit lines).
2. **Batch period kind** is config-driven (`DAILY` default). Daily vs weekly vs monthly is a
   business decision (see BATCH-01 §11); the contract is period-kind-agnostic.
3. **Closing cadence** is operator/scheduler-driven (`integration:spp-close-batches`); no
   automatic scheduler was added.
4. **READY_TO_CLOSE** state is defined but transitions to CLOSED happen via the close
   command; no separate UI marks batches READY_TO_CLOSE.
5. **Reconciliation** is limited to the integrity/balance checks above; no cross-system
   batch↔journal reconciliation report was built in this task.

---

## 12. Files changed

**TU (`~/sistem-tu-latest`)** — added:
- `database/migrations/2026_10_10_090000_create_spp_settlement_batches_table.php`
- `database/migrations/2026_10_10_090001_create_spp_settlement_batch_items_table.php`
- `app/Models/SppSettlementBatch.php`, `app/Models/SppSettlementBatchItem.php`
- `app/Services/Integration/SppSettlementBatchService.php`
- `tests/Feature/SppSettlementBatchTest.php`

**TU — modified:**
- `config/integration.php` (batch feature flag)
- `app/Models/IntegrationOutboxEvent.php` (`EVENT_SPP_SETTLEMENT_BATCH`)
- `app/Services/Integration/OutboxDispatcher.php` (accounting-only gate + batch status sync)
- `app/Services/BillingPaymentService.php` (batch membership hook)
- `app/Http/Controllers/Siswa/PaymentSiswaController.php` (batch hooks, single + MULTI)
- `routes/console.php` (`integration:spp-close-batches`)

**Finance (`~/keuangan-mutiara-kasih/backend`)** — added:
- `app/Services/Integration/TuSettlementBatchEvent.php`
- `app/Services/Integration/TuSettlementBatchService.php`
- `tests/Feature/TuSettlementBatchIngestTest.php`

**Finance — modified:**
- `app/Services/JournalService.php` (`recordBatchSettlement`)
- `app/Http/Controllers/TuIntegrationController.php` (batch routing)

**Not touched:** KANTIN integration; `~/sistem-tu`; any per-bill integration logic.

---

## 13. Test results

- **TU batch tests** (`tests/Feature/SppSettlementBatchTest.php`): **14 passed** — period
  inclusion/exclusion, unpaid exclusion, activation boundary, MULTI grain (1 payment/4
  bills), totals, duplicate-membership blocked, breakdown preserved, immutable-after-CLOSED,
  lifecycle + deterministic key/payload, idempotent event build, dispatcher SENT/
  ACKNOWLEDGED + retry, flag on/off dispatch behavior.
- **Finance batch tests** (`tests/Feature/TuSettlementBatchIngestTest.php`): **12 passed** —
  one balanced multi-debit journal, traceability columns, breakdown-sum / count / empty /
  source-system rejection, missing-mapping rejection (no posting), closed + missing period
  rejection, duplicate idempotency, endpoint replay DUPLICATE, TU-COA-IDs-ignored.
- **Full TU suite:** 409 passed, 1 skipped.
- **Full Finance suite:** 442 passed, 8 skipped (PostgreSQL integration tests require a
  provisioned PG test DB; unchanged).
- No existing tests weakened or removed.
