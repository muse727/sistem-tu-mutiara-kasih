# SPP-BATCH-FINAL-CERTIFICATION

Read-only final audit certifying that, **after the PAYMENT-FEE cleanup**
(`PAYMENT-FEE-CLEANUP` + `PAYMENT-FEE-CLEANUP-DB-01`), the remaining SPP
Settlement Batch **v1** architecture across TU (`~/sistem-tu-latest`) and
Finance (`~/keuangan-mutiara-kasih/backend`) is internally consistent and
contains **no active fee dependency**.

No code, schema, or migration was changed by this audit. No commit, no push.

## 1. Scope

- Repositories: `~/sistem-tu-latest` (TU) and
  `~/keuangan-mutiara-kasih/backend` (Finance).
- Subject: the SPP Settlement Batch v1 pipeline — TU aggregation → outbox →
  HMAC dispatch → Finance ingest → one transaction / one balanced journal →
  detection-only reconciliation.
- Verified: no fee machinery remains active; v1 contract intact end-to-end;
  security surfaces untouched by the cleanup; no historical mutation.

## 2. Fee Cleanup Verification

Repo-wide search of **active** code/config/routes/factories/tests for:
`fee_amount`, `fee_payer`, `customer_surcharge`, `net_amount`,
`FeePayerResolver`, `feeAttributesForCreation`, `gateway_fee_state`,
`spp_batch_v2_enabled`, `buildV2Payload`, `spp_gross_amount`,
`total_net_settlement_amount`, `total_gateway_fee_amount`.

| Surface | Result |
| :-- | :-- |
| TU `app/ config/ routes/ database/factories/ tests/` | **0 references** |
| TU `database/migrations/` (other than the cleanup drop migration) | **0 references** |
| Finance `app/ config/ routes/ database/ tests/` | **0 references** |

The only files matching these tokens are the cleanup migration
(`2026_11_05_000001_drop_fee_fields_from_payment_attempts_table.php` — required
for the drop/rollback) and historical/superseded documentation. Neither is an
active runtime reference.

The `fee_payer` config block, the `FeePayerResolver` service, the
`AppServiceProvider::validateFeePayerConfig()` boot hook, the
`spp_batch_v2_enabled` flag, and the batch v2 payload path are all **gone**.
`PaymentAttempt` carries no fee casts/constants/helpers.

**Active fee references: 0.**

## 3. Batch V1 Verification

`App\Services\Integration\SppSettlementBatchService` emits the v1.0 contract
only (`event_version`/`contract_version` hardcoded `'1.0'`; the v2 branch and
`buildV2Payload()` were removed).

| Property | Verification | Status |
| :-- | :-- | :-- |
| `total_amount` = gross SPP | `recompute()` sums `bcadd` over `StudentBill.amount`; payload sends `decimal($batch->total_amount)` | ✅ |
| `payment_count` = operational payments | `count($paymentKeys)` keyed on `operational_payment_key` | ✅ |
| `bill_count` = StudentBills | `$items->count()` | ✅ |
| `breakdown` buckets | per `(payment_method, provider)` with `amount`, `payment_count`, `bill_count` | ✅ |
| MULTI = one payment / many bills | `operational_payment_key()` → `PG:{midtrans_order_id}` groups all bills of one MULTI order into one operational payment | ✅ |
| Idempotency | outbox `firstOrCreate` on `(source_system, event_type, source_record_id=batch_id)` + deterministic `idempotency_key` | ✅ |
| Closed-batch immutability | `close()` locks row, returns early if `isClosed()` — no reopen; late settlement never mutates a CLOSED batch | ✅ |
| Overflow behavior | late post-close settlement routed via `openOverflowBatch()` with a distinct `overflowIdempotencyKey` | ✅ |
| Forward-only activation | `batchEligible()` honors `spp_batch_activated_at` (no backfill) | ✅ |

**Batch v1: intact, fee-free, internally consistent.**

## 4. TU → Finance Flow

Verified end-to-end:

1. **TU batch** — settled SPP bills aggregate into a DRAFT batch (period grain),
   closed via `close()`, emitted as a `spp.settlement_batch` v1.0 outbox event.
2. **Outbox** — `IntegrationOutboxEvent` row with deterministic
   `event_id = batch_id`, `contract_version = 1.0`, `idempotency_key`.
3. **HMAC** — `HmacIntegrationTransport` signs each event
   (`base64(HMAC-SHA256(secret, canonical))` over
   `{ts}\n{METHOD}\n{path}\n{sha256(body)}`) and POSTs to Finance. Secret is
   env-only, never logged. `OutboxDispatcher` selects with `SKIP LOCKED` and
   never holds the lock across the HTTP call.
4. **Finance** — `POST /api/integration/tu/events` guarded by `tu.hmac`
   middleware (constant-time `hash_equals`, timestamp tolerance, replay guard).
   `TuSettlementBatchEvent::fromArray()` contract-validates (v1.0, required
   fields, valid breakdown). `TuSettlementBatchService::process()` posts.
5. **One transaction** — `JournalService::recordBatchSettlement()` creates a
   single `kas_masuk` `Transaction` inside one `DB::transaction`.
6. **One balanced journal** — one DEBIT per settlement bucket (Cash/Bank per
   `payment_method`/`provider`) + one CREDIT to SPP revenue for the total;
   `post()` enforces `bccomp(totalDebit,totalCredit,2)===0`.

**TU → Finance: v1.0 contract, one transaction, one balanced journal — intact.**

## 5. Finance Accounting

- **Contract version:** `TuFinanceEvent::CONTRACT_VERSION = '1.0'`;
  `TuSettlementBatchEvent` rejects any `event_version`/`contract_version`
  ≠ `'1.0'` (`UNSUPPORTED_EVENT_VERSION`).
- **Fields read:** `total_amount`, `breakdown[].amount` (+ `payment_method`,
  `provider`, `payment_count`, `bill_count`), `payment_count`, `bill_count`.
  No fee/v2 field is referenced anywhere in Finance.
- **Accounting (cash basis):**
  - `Dr` Cash/Bank — one debit line per settlement bucket, mapped via
    Finance-owned `tu_event_coa_mappings` (semantic COA codes; TU never sends
    COA IDs).
  - `Cr` SPP Revenue — single shared revenue account for the batch total.
- **Idempotency:** `transactions UNIQUE(source_system, idempotency_key)` →
  replays/concurrent delivery post exactly once (`DUPLICATE_EVENT`).
- **Mapping guard:** missing bucket/revenue mapping → `MAPPING_REQUIRED`,
  nothing posted.

**Finance: v1.0, Dr Cash/Bank + Cr SPP Revenue, balanced, idempotent.**

## 6. Reconciliation

`SppSettlementBatchReconciliationService` (Finance, detection-only; TU snapshot
via `SppSettlementBatchReconciliationSnapshot`, which uses only v1 fields)
verifies, with exact `bccomp` decimal arithmetic:

- TU internal consistency (frozen vs live aggregation, breakdown sum, counts).
- Finance identity/traceability (`event_id`/`source_record_id`/`event_type` =
  `batch_id`).
- **Posted amount == batch `total_amount`.**
- **Journal debit total == credit total** (IMBALANCE otherwise).
- **Journal debit total == batch `total_amount`.**
- Per-bucket Finance debit lines vs the Finance-owned COA mapping.

Chain: **TU gross total = Finance transaction amount = Σ debit = Σ credit** ✅.
Reconciliation is read-only — never posts, never mutates, never repairs.

**Reconciliation: consistent and exact.**

## 7. Security

The cleanup removed only fee machinery; every security surface below is
unchanged and verified present:

| Surface | Mechanism | Status |
| :-- | :-- | :-- |
| Midtrans signature | SHA-512 over `order_id+status_code+gross_amount+server_key`, `hash_equals` timing-safe | ✅ |
| Flip callback authenticity | `verifyCallbackToken()` constant-time; only verified `SUCCESSFUL` settles; amount mismatch rejected | ✅ |
| Ownership | `student_id` guard (`403`) on bill resolution in `PaymentSiswaController` / `FlipPaymentController`; query-scoped lookups | ✅ |
| Amount validation | `StudentBill.amount` authoritative; provider amount compared; never trust frontend | ✅ |
| Idempotency | one-open-attempt guard; outbox `firstOrCreate`; Finance `UNIQUE(source_system,idempotency_key)` | ✅ |
| HMAC | TU signs; Finance verifies constant-time with timestamp tolerance + replay guard; secret env-only | ✅ |
| Batch locking | `lockForUpdate` on close/record; `SKIP LOCKED` dispatch; lock never held across HTTP | ✅ |
| Overflow handling | late settlements → new overflow batch; frozen CLOSED batch never mutated | ✅ |

**Security: no weakening introduced by the cleanup.**

## 8. Historical Safety

- **No historical transaction changed.** The cleanup touched only
  `payment_attempts` fee columns (dropped) and removed inert code; no UPDATE to
  any transaction/journal/bill row was performed.
- **No historical batch rebatching.** Batch close is one-way (`isClosed()`
  early-return); late settlements route to overflow; the outbox event is
  `firstOrCreate`d once per `batch_id`. There is no re-emit/rebatch path.
- **No migration side effects beyond the fee cleanup.** The only schema change
  is `2026_11_05_000001_drop_fee_fields_from_payment_attempts_table` (Ran,
  batch 6), dropping exactly the six orphan columns. Final `payment_attempts`
  schema: 21 columns, no fee fields. Historical `migrations`-table rows were not
  altered.

**Historical safety: confirmed.**

## 9. Test Results

| Suite | Result |
| :-- | :-- |
| TU batch + reconciliation + outbox (8 batch/recon/dispatch suites) | **58 passed** (203 assertions) |
| TU payment/webhook/security (Midtrans, Flip, MULTI, callbacks) | **29 passed** (127 assertions) |
| TU integration/HMAC + token/idempotency | **34 passed, 1 skipped** (115 assertions) |
| **TU full suite** | **434 passed, 1 skipped** (1,359 assertions) |
| Finance batch/recon/HMAC (6 suites) | **93 passed** (223 assertions) |
| **Finance full suite** | **492 passed, 8 skipped** (1,443 assertions) |

Skips are pre-existing and unrelated to the cleanup:
- TU: 1 skip — `ParentApiPaymentTest` (requires a Midtrans facade mock;
  integration-only).
- Finance: 8 skips — Postgres-dependent tests (`tests/Pg/PgTestCase.php`).

**No test was weakened or removed by this audit.**

## 10. Findings

No defects found. Observations (non-blocking):

1. **Database scope (informational):** the orphan fee columns were dropped from
   the TU **local development** database only. Any other environment that had
   applied the original fee migrations must run the idempotent cleanup migration
   there (it no-ops where the columns are absent).
2. **Inert history (by design):** historical `migrations`-table rows for the
   removed fee migrations remain; they record that those migrations once ran and
   have no runtime effect.
3. **Shared SPP revenue account (documented design):** Finance credits a single
   SPP revenue account for the batch (first bucket's revenue mapping wins). If
   buckets ever need distinct revenue accounts, that is a bendahara policy
   decision — out of scope and unchanged by the cleanup.

## 11. Final Verdict

**CERTIFIED.** After the payment-fee cleanup, the SPP Settlement Batch **v1**
architecture is internally consistent across TU and Finance and contains **zero
active fee dependency**. The end-to-end flow (batch → outbox → HMAC → one
transaction → one balanced journal → detection-only reconciliation) is intact,
the v1.0 contract is preserved on both sides, security surfaces are unweakened,
no historical data was mutated, and both full test suites pass.

## 12. Next Task

**None required for this track.** The payment-fee cleanup and v1 batch
certification are complete; the architecture is stable.

Optional follow-ups (new scope, only if prioritized):
- **Env rollout of DB cleanup:** apply
  `2026_11_05_000001_drop_fee_fields_from_payment_attempts_table` to any
  non-local environment that previously ran the fee migrations (idempotent).
- **Re-introduce fee modeling (only if a real requirement emerges):** design
  fee/net capture from an *authoritative provider settlement field* rather than
  config-derived estimates, with its own audit, contract version bump (v2), and
  Finance mapping plan — do not resurrect the removed config-driven approach.
