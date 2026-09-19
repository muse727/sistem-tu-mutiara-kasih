# TU-FINANCE-BATCH-03 — POST-IMPLEMENTATION ACCOUNTING & INTEGRATION AUDIT

**Read-only audit.** No code, migrations, schema, routes, UI, tests, or configuration were
modified. Audited against `docs/TU-FINANCE-BATCH-01-DESIGN.md` and
`docs/TU-FINANCE-BATCH-02-IMPLEMENTATION.md`. Repositories: `~/sistem-tu-latest` (TU) and
`~/keuangan-mutiara-kasih/backend` (Finance). Findings cite exact file/class/method.
Severity classes: **CRITICAL / HIGH / MEDIUM / LOW**.

---

## 1. Executive Verdict

The SPP Settlement Batch implementation is **architecturally sound and safe to proceed to
reconciliation/UI**, **conditional on two operational prerequisites** that are not enforced
in code and on **one HIGH code fix**:

- **0 CRITICAL** — no path double-posts the same rupiah under any single flag position;
  amount integrity holds end-to-end; idempotency and balance are DB/boundary-enforced.
- **1 HIGH** — a payment settling in the race window between batch close and dispatch is
  silently folded into a CLOSED batch (updates the batch snapshot) but is **excluded from
  the already-queued outbox payload**, so it is **never accounted in Finance** (lost
  revenue, no error). See F-1.
- **3 MEDIUM** — (a) flag on→off flip creates a **dispatch gap** (bills neither batched nor
  dispatched); (b) two buckets mapping to the same debit account produce duplicate
  same-account debit lines (harmless but messy ledger); (c) `period_end < period_start` and
  other period-shape inputs are not validated Finance-side.
- **4 LOW** — first-bucket shared-revenue coupling; multi-bucket mapping-row collision;
  controller duplicate-code constant coupling; READY_TO_CLOSE unused.

**The accounting model is cash basis throughout and is internally consistent.** No new
accounting-policy decision is required, **except** one clarification: whether Finance ever
intends to map different settlement buckets to **different revenue accounts** (currently
the shared credit takes the first bucket's revenue code — safe only while all SPP buckets
share one revenue account). See §12.

**Prerequisites for go-live (operational, not code):**
1. Finance must seed one `tu_event_coa_mappings` row per settlement bucket that can appear
   (`event_type='spp.settlement_batch'`), otherwise every batch returns `MAPPING_REQUIRED`
   and nothing posts (fail-safe, but blocks go-live).
2. The cut-over must be coordinated: enable the flag and the Finance mapping together at a
   chosen boundary so no bill falls in the "neither dispatched nor batched" gap (§6).

---

## 2. Scope

Audited TU components:
- `app/Services/Integration/SppSettlementBatchService.php` (`recordEligibleSettlement`,
  `close`, `buildOutboxEvent`, `recompute`, `openBatchFor`, `periodFor`, `batchEligible`,
  `operationalPaymentKey`, `provider`)
- `app/Models/SppSettlementBatch.php`, `SppSettlementBatchItem.php`
- `app/Services/Integration/OutboxDispatcher.php` (`claimNext`, `restrictAccounting`,
  `recordOutcome`, `syncBatchStatus`)
- `app/Services/BillingPaymentService.php::recordIntegrationEvent`
- `app/Http/Controllers/Siswa/PaymentSiswaController.php` (single callback ~L447/L529,
  MULTI ~L698/L747)
- `config/integration.php`; migrations `2026_10_10_090000/090001`

Audited Finance components:
- `app/Services/Integration/TuSettlementBatchEvent.php`, `TuSettlementBatchService.php`
  (`process`, `resolvePlan`)
- `app/Services/JournalService.php::recordBatchSettlement`, `post`, `sourceTraceability`
- `app/Http/Controllers/TuIntegrationController.php::store`
- `app/Models/TuEventCoaMapping.php`, `Transaction.php`

---

## 3. Findings (index)

| ID | Severity | Finding |
|----|----------|---------|
| F-1 | **HIGH** | Late payment folded into CLOSED batch but excluded from queued payload → never posted to Finance (lost revenue) |
| F-2 | **MEDIUM** | Flag ON→OFF flip: eligible bills are neither dispatched (trace-only) nor batched → accounting gap |
| F-3 | **MEDIUM** | Two buckets sharing one debit account → duplicate same-account debit lines |
| F-4 | **MEDIUM** | Finance validator does not reject `period_end < period_start` / malformed period shape |
| F-5 | **LOW** | Shared revenue credit = first bucket's `credit_coa_code`; incorrect only if buckets map to different revenue accounts |
| F-6 | **LOW** | Buckets sharing `(payment_method, provider)` resolve one mapping row; provider granularity collapses within same pair |
| F-7 | **LOW** | Controller duplicate branch uses legacy `ERR_DUPLICATE_EVENT` constant (value-identical today) |
| F-8 | **LOW** | `READY_TO_CLOSE` state defined but never entered by any code path |

---

## 4. Accounting Correctness

**Verdict: CORRECT under cash basis, with F-5 caveat.**

- Cash basis is preserved: `recordBatchSettlement` posts `Dr Cash/Bank (per bucket) / Cr
  SPP Revenue` at the batch business date. No receivable, no deferral introduced. Matches
  the confirmed policy.
- The journal posts through the existing trusted boundary `JournalService::post()`, which
  enforces exact-decimal balance (`bccomp($totalDebit,$totalCredit,2)===0`) — an unbalanced
  journal **cannot** post. `recordBatchSettlement` debits Σbuckets and credits
  `total_amount`; because the TU-side batch guarantees Σbuckets == total_amount (and Finance
  re-validates this), the journal is balanced by construction.
- Transaction is `type='kas_masuk'`, consistent with the existing SPP classification, so
  Laba Rugi / Neraca / Arus Kas treat it identically to prior per-bill SPP rows.
- One balanced journal per batch (not per bill), as designed. Aggregation does not change
  the accounting result — only the grain.

**No incorrect-accounting defect found** beyond the conditional F-5.

---

## 5. Double-Posting Analysis

**Verdict: SAFE under any single flag position. The only double-posting-adjacent risks are
F-2 (gap, not double) and a flag-oscillation edge (below).**

Traced every path that can create `spp.payment` and `spp.settlement_batch`:

- `spp.payment` events are created by `IntegrationOutboxService::recordSppPayment` from
  exactly three call sites (cash `recordIntegrationEvent`, single callback L529, MULTI L747),
  all gated on `integration.enabled` + `activated_at` (PAID only).
- `spp.settlement_batch` events are created only by `SppSettlementBatchService::buildOutboxEvent`
  for a CLOSED batch (idempotent `firstOrCreate`).

**Flag OFF (Scenario A):** `restrictAccounting` does NOT filter; per-bill `spp.payment`
events dispatch normally → **exactly one Finance posting per settled bill** (existing flow).
`recordEligibleSettlement` returns immediately (flag check) → no batch, no batch event.
Single payment = 1 posting; MULTI = N postings (one per bill), matching current behavior.
**Confirmed: exactly one posting per bill, no batch posting.**

**Flag ON (Scenario B):** `recordEligibleSettlement` adds the bill to a batch;
`restrictAccounting` filters `event_type != 'spp.payment'` in `claimNext`, so per-bill
events are **never claimed/dispatched** (trace-only). Only the CLOSED batch's
`spp.settlement_batch` dispatches → **exactly one Finance posting per batch**, which is the
**only** accounting posting for those bills. New single = batched (not dispatched per-bill);
new MULTI = one payment in the batch (payment_count=1). **Confirmed: batch is the sole
accounting posting; per-bill is trace-only.**

**Flag oscillation (ON→OFF→ON) — edge, not a clean double-post:**
- ON→OFF→ON: a bill settled while OFF is neither dispatched nor batched (F-2 gap, not a
  double-post).
- OFF→ON: pre-existing PENDING per-bill events become stranded (never claimed while ON).
  They do not double-post; they are simply not posted. No duplicate.
- There is **no flag position where the same bill is posted both per-bill and via batch**,
  because the dispatcher gate and the membership recorder are driven by the **same**
  `integration.spp_batch_enabled` value at their respective times. The only way to get a
  double-post would be to dispatch a per-bill event while OFF for a bill that is ALSO in a
  batch — but membership only happens while ON, and while ON the per-bill event is not
  dispatched. These windows do not overlap for the same bill. **No double-posting path
  exists.**

**Finance-side final guard:** `transactions UNIQUE(source_system, idempotency_key)` makes
any concurrent/duplicate ingest post exactly once regardless of the above. Per-bill keys
(`TU:spp.payment:{id}`) and batch keys (`TU:SPP:…`) are distinct keyspaces and cannot
collide.

---

## 6. Activation Boundary Analysis

**Verdict: forward-only and no-backfill is correctly enforced; the boundary interaction
between the two independent gates is the F-2 risk.**

- Batch eligibility uses `integration.spp_batch_activated_at` (`batchEligible`), independent
  of the per-bill `integration.activated_at` (`settlementEligible`). `paid_at` is the
  canonical timestamp on both.
- **Before `spp_batch_activated_at`:** `recordEligibleSettlement` returns (no membership).
  Whether the bill is also posted per-bill depends on the per-bill gate
  (`integration.activated_at`) and the flag. **A payment before the batch boundary is never
  retroactively batched — confirmed.**
- **Exactly around the boundary:** `greaterThanOrEqualTo` — a payment with `paid_at ==
  activated_at` is eligible. Deterministic, no off-by-one gap.
- **After the boundary:** membership recorded (if flag ON).

**Can any transaction be excluded, double-posted, or posted in both paths?**
- **Excluded:** YES — F-2. If the flag is OFF at settle time but later turned ON, that bill
  is in no batch; and once ON, its per-bill event is never dispatched. It is **excluded from
  Finance accounting entirely** unless caught by reconciliation. This is an exclusion, not a
  double-post.
- **Double-posted / both paths:** NO (see §5). The same bill cannot be both dispatched
  per-bill and batched.

**Required operational rule (not enforced in code):** choose a single cut-over instant —
set `spp_batch_activated_at`, enable the flag, and seed the Finance mapping together — and
do not toggle the flag repeatedly afterward.

---

## 7. Aggregation Analysis

**Verdict: CORRECT.**

Canonical timestamp = `StudentBill.paid_at` (Asia/Jakarta). Eligibility: `type='SPP'`,
`status='PAID'`, at/after boundary, not already a member (`UNIQUE(student_bill_id)`). Period
boundaries derived from `paid_at` via `periodFor` (DAILY/WEEKLY/MONTHLY). Operational grain:
`operational_payment_key` = `PG:{midtrans_order_id}` for online (so a MULTI order = ONE
payment), `CASH:{bill_id}` for cash.

Traced against the scenarios (and confirmed by passing TU tests):

| Scenario | payment_count | bill_count | total_amount |
|---|---|---|---|
| 1 parent / 1 bill (500k) | 1 | 1 | 500.000,00 |
| 1 parent / 4 bills (1 MULTI) | 1 | 4 | 2.000.000,00 |
| 1 parent / 12 bills (1 MULTI) | 1 | 12 | 6.000.000,00 |
| 100 parents / 250 bill-months | 100 | 250 | 125.000.000,00 |

- `payment_count` counts distinct `operational_payment_key` (MULTI collapses correctly).
- `bill_count` counts member items.
- `total_amount` = Σ member `bill.amount` (exact decimal via `bcadd`).
- Unpaid bills excluded (`status !== 'PAID'` returns). Out-of-period bills land in their own
  period batch. Duplicate membership impossible (DB unique + `firstOrCreate`).

**No aggregation defect found.**

---

## 8. Payment Breakdown Analysis

**Verdict: breakdown total always equals batch total (enforced twice); multi-account
preservation is correct; two defects noted (F-3, F-6).**

- `recompute` builds buckets keyed on `payment_method|provider` and sums `amount`,
  `payment_count`, `bill_count` per bucket. `TuSettlementBatchEvent` re-validates Finance-side
  that Σ bucket amounts == total_amount and Σ counts match. **Batch total == Σ buckets is
  guaranteed on both sides.**
- Conceptual scenarios:
  - **CASH only** → one bucket `(CASH, null)` → one debit line (e.g. `1-101`). Correct.
  - **One online provider** → one bucket `(MIDTRANS, midtrans)` → one debit line (e.g.
    `1-102`). Correct.
  - **CASH + online** → two buckets → two debit lines. **No collapse** — guardrail satisfied.
  - **Multiple online providers** (Midtrans + Flip) → buckets `(MIDTRANS, midtrans)` and
    `(ONLINE, flip)` → two debit lines, IF Finance seeds a mapping for each pair. Correct,
    provided distinct mappings exist.
- **F-3 (MEDIUM):** `resolvePlan` adds one debit line per bucket even when two buckets map to
  the **same** debit account (e.g. both Midtrans and Flip → `1-102`). The journal then has two
  debit lines to the same COA. Total is still correct and balanced, so **accounting is not
  wrong**, but Buku Besar shows two lines where one consolidated line would be cleaner. No
  silent cross-account collapse — this is the opposite (over-granularity), so it is MEDIUM/LOW
  in impact.
- **F-6 (LOW):** buckets are keyed `payment_method|provider`. If two genuinely different
  settlement accounts ever share the same `(payment_method, provider)` pair (not possible with
  current `midtrans`/`flip` providers), they would collapse into one bucket. Not reachable
  today.

---

## 9. Concurrency / Idempotency

**Verdict: DB constraints + boundary locks are sufficient for Finance; TU has one race
window (F-1) and one benign race.**

- **Two workers recording the same payment:** `SppSettlementBatchItem` `UNIQUE(student_bill_id)`
  + `firstOrCreate` → exactly one item. The settle paths themselves are idempotent (PAID guard,
  `firstOrCreate` outbox). Safe.
- **Two workers closing the same batch:** `close()` takes `lockForUpdate()` on the batch row;
  `isClosed()` re-checked under lock → second closer sees CLOSED and returns. Safe.
- **Two workers creating the period batch (`openBatchFor` firstOrCreate):** the
  `UNIQUE(source_system,category,period_kind,period_start)` constraint collapses concurrent
  creates to one row; the loser gets a `UniqueConstraintViolationException` which is caught by
  `recordEligibleSettlement`'s outer `try/catch` (logged, non-fatal). **Benign but lossy:** the
  losing worker's bill is not retried into the batch in the same call (it is swallowed). It would
  be picked up only if that settle path runs again (it does not for a single settlement). This is
  a **rare edge** subsumed by F-1's class (a bill can miss the batch); noted here for completeness.
- **Duplicate outbox dispatch:** `claimNext` locks the outbox row; transport is idempotent;
  Finance UNIQUE guard catches the rest. Safe.
- **Duplicate Finance ingestion:** pre-check + `transactions UNIQUE(source_system,
  idempotency_key)` inside `recordBatchSettlement`'s transaction → exactly one transaction/journal;
  `UniqueConstraintViolationException` → `duplicate`. Concurrent duplicates resolve to one. Safe.
- **Balance/atomicity:** `recordBatchSettlement` runs in one `DB::transaction`; a throw rolls back
  transaction+journal+details together (no partial state).

---

## 10. Lifecycle Analysis

**Verdict: transitions are guarded; CLOSED immutability is enforced; F-1 and F-8 noted.**

Traced states `DRAFT → READY_TO_CLOSE → CLOSED → SENT → ACKNOWLEDGED` + `FAILED_RETRYABLE` /
`FAILED_PERMANENT`:

- **Illegal transitions:** `close()` refuses to re-close (`isClosed()` under lock); `buildOutboxEvent`
  only builds for `status === CLOSED`. A CLOSED batch cannot return to DRAFT.
- **Mutation after CLOSED:** the model `updating` guard throws `LogicException` for any change to
  `total_amount`, `payment_count`, `bill_count`, `breakdown`, identity, or period once closed;
  `deleting` guard blocks deletion. Operational fields (`status`, `attempt_count`, `last_error`,
  `sent_at`, `acknowledged_at`) may change via `forceFill` in `syncBatchStatus`. **However**, see
  F-1: `recordEligibleSettlement` does not check `isClosed()` before adding an item and calling
  `recompute`, and `recompute`'s `forceFill(...)->save()` targets a row whose **in-memory** status is
  stale (`DRAFT` from `firstOrCreate` cache), so the guard does not fire and the frozen snapshot is
  silently recomputed — the mutation-after-CLOSED guard is **bypassed** in this specific path.
- **Retry after failure:** `FAILED_RETRYABLE` re-enters `claimNext` (status PENDING/FAILED_RETRYABLE);
  safe (idempotent Finance ingest).
- **Resend after SENT:** safe — same payload, same idempotency key → Finance `DUPLICATE`.
- **ACK handling:** 2xx (PROCESSED/DUPLICATE) → DELIVERED → batch `ACKNOWLEDGED`. Correct.
- **F-8 (LOW):** `READY_TO_CLOSE` is never set by any code path (batches go DRAFT→CLOSED directly).
  Dead state; harmless but the lifecycle is partially unimplemented.

---

## 11. Period / Cut-off

**Verdict: period eligibility and Finance period rejection are correct and safe; F-4 noted.**

- **Controlling timestamp:** `StudentBill.paid_at` (Asia/Jakarta) for TU membership/period;
  Finance derives the journal date from the batch `occurred_at` (set to period end-of-day) via
  `accountingDate()` (Asia/Jakarta). Consistent.
- **CLOSED batch sent into an already-closed Finance period:** `recordBatchSettlement` calls
  `requireOpenPeriod($date)`; a closed/missing period throws `AccountingException` → caught →
  `STATE_NOT_ELIGIBLE` (422) → TU classifies 4xx as PERMANENT → batch `FAILED_PERMANENT`. **Nothing
  posts. Safe.** This is the correct fail-safe (mirrors the savings no-reopen precedent).
- **Retry after period closure:** a batch that failed with `STATE_NOT_ELIGIBLE` is PERMANENT and will
  not auto-retry; recovery requires a bendahara decision (post into next open period via a new batch
  or reopen policy) — consistent with the no-reopen design, but it is a **manual** step (documented).
- **F-4 (MEDIUM):** the Finance validator does not check `period_end >= period_start` or that the
  breakdown is internally shaped to the period kind. A malformed (but sums-consistent) event with
  inverted period dates would be accepted and posted to the `occurred_at` date. Low likelihood
  (TU generates the payload), but the boundary is not defensively validated.

---

## 12. Finance Mapping Analysis (explicit audit target)

**Verdict: SAFE under current configuration; F-5 is a real coupling that must be documented.**

- **Debit mapping per bucket:** `resolvePlan` resolves `TuEventCoaMapping::resolve(
  'spp.settlement_batch', bucket.payment_method, bucket.provider)` per bucket and debits that
  bucket's amount to the mapped account. Correct and Finance-owned; TU supplies no COA IDs
  (verified — injected `coa_id`/`debit_coa_id`/`credit_coa_id` in the payload are ignored).
- **Shared revenue credit (F-5, explicit target):** the credit account is taken as
  `$credit = $credit ?? $creditCoa` — i.e. the **first** bucket's `credit_coa_code`, applied to the
  **whole** batch total. **This is correct only if every bucket's mapping row credits the same SPP
  revenue account.** If a bendahara maps, say, the Flip bucket's `credit_coa_code` to a different
  revenue account than the Cash bucket, the entire batch total is credited to whichever account the
  **first breakdown bucket** resolved — misallocating revenue and leaving the other revenue
  account(s) unposted. Because breakdown bucket order follows item-insertion order, the "first"
  bucket is not guaranteed to be any particular method, making the result order-dependent in that
  misconfigured scenario.
- **Is it safe under current configuration?** YES — provided all `spp.settlement_batch` mapping rows
  point `credit_coa_code` at the **same** SPP revenue account (e.g. `4-101`). That is the documented
  operating assumption (single SPP revenue account under cash basis). **This must be an explicit,
  documented configuration invariant.** If Finance ever wants per-method revenue accounts, the code
  must change to per-bucket credit lines — that is a **bendahara accounting-policy decision**, not a
  code default.
- **Missing mapping:** any unmapped bucket → `MAPPING_REQUIRED`, nothing posts (fail-safe).

---

## 13. Traceability

**Verdict: COMPLETE.**

```
Finance Transaction/Journal
  source_system=TU, event_type=spp.settlement_batch,
  source_record_id=batch_id, event_id=batch_id,
  idempotency_key=TU:SPP:{PERIOD_KIND}:{period_start}     [transactions source cols]
    ↓
TU spp_settlement_batches (batch_id, idempotency_key, period_*, total, counts)
    ↓ spp_settlement_batch_items (UNIQUE student_bill_id, operational_payment_key)
operational payment (PG:{midtrans_order_id} | CASH:{bill_id})
    ↓ student_bill_id
StudentBill (student_id, bill_month, bill_year, amount)
    ↓
Student / month(s)
```

Every link is present and persisted. Finance stores the batch identity on the transaction;
TU holds the full bill/student/month detail. No fabricated identity.

---

## 14. Amount Integrity

**Verdict: HOLDS end-to-end.**

- Σ items == batch total: `recompute` sums member `bill.amount` into `total_amount`.
- Batch total == Σ breakdown buckets: built together in `recompute`; Finance re-validates
  (`TuSettlementBatchEvent` cross-field check) and rejects on mismatch.
- Finance debit total == Finance credit total == batch total: `recordBatchSettlement` debits
  Σ buckets and credits `total_amount`; `post()` enforces Σdebit == Σcredit with exact decimals.
- The full chain `Σ items = batch total = Σ buckets = Σ debit = Σ credit` is therefore enforced.
- Data-type note: `StudentBill.amount` is a signed `decimal(12,2)` (no non-negative check). A
  negative bill amount would produce a negative bucket amount and be rejected Finance-side
  (`requireMoney` requires `> 0`). Not reachable in normal operation (billing never creates
  negative SPP), so LOW risk; noted for completeness.

---

## 15. Historical Safety

**Verdict: SAFE.**

- No backfill: `batchEligible` enforces `paid_at >= spp_batch_activated_at`; pre-boundary bills
  are never batched. `recordEligibleSettlement` is the only membership writer and only runs at
  settle time.
- Existing historical per-bill Finance transactions are untouched (no migration or update
  touches `transactions`).
- Keyspace separation (per-bill vs batch idempotency keys) prevents any collision with
  historical per-bill rows.

---

## 16. Severity Classification

- **CRITICAL: 0**
- **HIGH: 1** — F-1
- **MEDIUM: 3** — F-2, F-3, F-4
- **LOW: 4** — F-5, F-6, F-7, F-8

---

## 17. Required Fixes (NOT implemented — for a future task)

1. **F-1 (HIGH) — close the close/dispatch race.** In
   `SppSettlementBatchService::recordEligibleSettlement`, after `openBatchFor`, re-load the batch
   with `lockForUpdate()` and **refuse membership if `isClosed()`**; a payment arriving after close
   must land in a **new** batch for the same period (or the next open batch) rather than mutating
   the closed one. Additionally make `recompute` operate on the locked row so the model
   immutability guard cannot be bypassed. Exact location:
   `app/Services/Integration/SppSettlementBatchService.php::recordEligibleSettlement` (inner
   `DB::transaction`) and `::recompute`.
2. **F-2 (MEDIUM) — eliminate the flag-flip gap.** Either (a) make `recordEligibleSettlement`
   **also** enqueue a per-bill `spp.payment` dispatch when the flag is OFF so the legacy path stays
   live (current behavior already does this — the gap is specifically the ON→OFF window where a
   bill was batched but its per-bill event was suppressed), or (b) operationally forbid flag
   oscillation and reconcile at cut-over. Minimum: document the single-cut-over rule and add a
   reconciliation check for "settled, not batched, not dispatched" bills.
3. **F-3 (MEDIUM) — consolidate same-account debit lines.** In
   `TuSettlementBatchService::resolvePlan`, aggregate buckets that resolve to the same
   `debit_coa_id` into a single debit line (sum amounts). Exact location:
   `app/Services/Integration/TuSettlementBatchService.php::resolvePlan`.
4. **F-4 (MEDIUM) — validate period shape.** In `TuSettlementBatchEvent::fromArray`, reject
   `period_end < period_start` (and optionally require breakdown consistency with `period_kind`).
   Exact location: `app/Services/Integration/TuSettlementBatchEvent.php::fromArray`.

---

## 18. Non-Blocking Improvements

- **F-5:** make the shared-revenue invariant explicit — either assert in `resolvePlan` that all
  bucket `credit_coa_code` values are identical (reject otherwise), or document the single-revenue
  invariant in the bendahara mapping guide. (Code change optional if documented.)
- **F-6:** document that bucket granularity is `(payment_method, provider)`; sufficient for
  current providers.
- **F-7:** use `TuSettlementBatchService::ERR_DUPLICATE_BATCH` in the batch branch of
  `TuIntegrationController::store` instead of the legacy constant (values match today).
- **F-8:** either drive batches through `READY_TO_CLOSE` before `CLOSED` or remove the unused
  state to keep the lifecycle honest.
- Add a TU-side reconciliation query: settled PAID SPP bills that are neither batch members nor
  dispatched per-bill (catches F-1/F-2 stragglers).
- Consider a non-negative check on `spp_settlement_batches.total_amount` for defense-in-depth.

---

## 19. Recommendation for BATCH-04

**BATCH-02 is SAFE to proceed to reconciliation/UI**, subject to:
1. implementing the **F-1 fix** (highest priority — it is the only revenue-loss path),
2. seeding Finance `tu_event_coa_mappings` for every settlement bucket **before** enabling the
   flag, and
3. coordinating a **single** cut-over instant (flag + Finance mapping + `spp_batch_activated_at`).

**BATCH-04 scope recommendation:** implement F-1 (and ideally F-3, F-4), add the
settled-not-batched-not-dispatched reconciliation report, and build the batch list/detail UI plus
the close action — **after** F-1 lands. Do not proceed to production enablement with F-1 open.

---

### Final tally
- **CRITICAL: 0**
- **HIGH: 1** (F-1)
- **MEDIUM: 3** (F-2, F-3, F-4)
- **LOW: 4** (F-5, F-6, F-7, F-8)
- **Safe to proceed to reconciliation/UI?** Yes, **after** the F-1 fix and the two operational
  prerequisites (mapping seed + coordinated single cut-over).
- **Exact required fixes:** see §17 (F-1, F-2, F-3, F-4).
- **Accounting policy needs another decision?** Only one clarification: confirm Finance will keep a
  **single SPP revenue account** for all buckets (the shared-credit assumption). If per-method
  revenue accounts are ever desired, that is a new bendahara policy decision requiring a code change.
  No other policy decision is needed.
