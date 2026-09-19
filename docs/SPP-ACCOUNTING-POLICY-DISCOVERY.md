# SPP ACCOUNTING POLICY & COA MAPPING — DISCOVERY (READ-ONLY)

**Status:** Discovery / design only. No code, schema, routes, controllers, services,
migrations, tests, or UI were changed. The existing per-bill TU→Finance integration
is untouched. Nothing here creates schema, semantic-role enums, or COA changes.

Repositories inspected:
- TU (operational source of truth): `/home/muse/sistem-tu-latest`
- Finance (accounting source of truth): `/home/muse/keuangan-mutiara-kasih/backend`

Legend:
- **[EXISTING]** = verified current behavior in code/migrations.
- **[RECOMMENDED]** = proposed architecture (not implemented).
- **[POLICY]** = a business/accounting decision that cannot be determined technically.

---

## 1. Executive Summary

- Finance today treats **every settled SPP StudentBill as an independent cash-basis
  `kas_masuk` posting**: debit a cash/bank (asset) account, credit a revenue account.
  There is **no receivable, no deferred/unearned revenue, and no accrual step** anywhere
  in Finance. Verified by full-repo search — no `piutang`, `receivable`, `deferred`,
  `diterima dimuka`, `unearned`, or `accrued` accounting concept exists. **[EXISTING]**
- Finance already has a **configurable COA-mapping boundary** (`tu_event_coa_mappings`)
  keyed by **semantic COA codes** (never numeric DB IDs, never TU-supplied values). This
  is the correct seam for any future accounting-model change. **[EXISTING]**
- COA **names are fully configurable** by the bendahara; only the account **`type`**
  (`asset|liability|equity|revenue|expense`) and the **unique `code`** are structural.
  Reports group by `type`, never by name, so renames are safe. **[EXISTING]**
- The system currently runs effectively on **Model 1 (Cash Basis)**. Moving to **Model 2
  (Accrual/Receivable)** or **Model 3 (Deferred Revenue)** is a **[POLICY]** decision,
  not derivable from code. The architecture can support any of the three via the existing
  semantic-code mapping — **without TU choosing COA IDs** and **without hardcoding COA
  names**. **[RECOMMENDED]**
- A periodic TU→Finance SPP batch is compatible with all three models; the batch journal
  shape differs per model and is a **[POLICY]** choice (see §8).

---

## 2. Current Finance Accounting Flow

### 2.1 COA implementation

- **Model:** `app/Models/Coa.php` — `code` (unique), `name`, `type`, `is_active`,
  `description`. UUID PK, `SoftDeletes`.
- **Schema:** `database/migrations/2026_09_09_081016_create_coas_table.php` — `code`
  (string 20 UNIQUE), `name` (string 100), `type` (string 50 indexed).
- **Account types:** exactly five — `asset, liability, equity, revenue, expense` —
  enforced at `CoaController::validateCoa`. **[EXISTING]**
- **Hierarchy:** **None.** Flat list, no `parent_id`, no category tree; reports group by
  `type` only. **[EXISTING]**
- **Configurability / rename:** `CoaController::update` allows editing `code`, `name`,
  `type`, `description`, `is_active`. Accounts with journal usage **cannot be deleted**
  (`Coa::journalDetails()` usage count + delete restriction), but **can be renamed**.
  Reports select by `coas.type` and display `coas.name`, so renames are safe. **[EXISTING]**
- **Existing mapping mechanism:** `app/Models/TuEventCoaMapping.php` +
  `database/migrations/2026_10_08_090000_create_tu_event_coa_mappings_table.php`. Key
  `(event_type, payment_method, provider)` → `debit_coa_code`, `credit_coa_code`, using
  **semantic codes**, never numeric IDs, never TU-supplied values. Missing mapping →
  `MAPPING_REQUIRED`, nothing posted. **[EXISTING]**
- **Hardcoded numeric COA IDs?** **No.** Resolves by code via `TuEventCoaMapping::resolve`.
  `docs/TU-FINANCE-INTEGRATION-CONTRACT.md §22` mandates "no hard-coded Finance COA IDs";
  `PHASE-E2-FLIP-SANDBOX-E2E.md` states "No hardcoded COA id." **[EXISTING]**
- **Semantic-role concept (CASH_BANK etc.)?** **Not present.** De-facto semantics live in
  `coas.type` and the mapping key only. **[EXISTING]**

### 2.2 Existing SPP accounting flow (complete trace)

```
TU StudentBill (PAID)
  → IntegrationOutboxService::recordSppPayment()        [TU]
  → integration_outbox_events (spp.payment)             [TU]
  → OutboxDispatcher → HmacIntegrationTransport         [TU]
  → POST /api/integration/tu/events                     [Finance]
  → TuHmacAuthentication (verify)                       [Finance]
  → TuIntegrationController::store                      [Finance]
  → TuFinanceIntegrationService::process                [Finance]
      1. TuFinanceEvent::fromArray   (contract validation)
      2. idempotency check           (Transaction UNIQUE(source_system,idempotency_key))
      3. resolveMapping              (TuEventCoaMapping by event_type/method/provider)
      4. AccountingPeriodService::requireOpenPeriod(date)
      5. JournalService::recordKasMasuk → Transaction + draft Journal + details + post
      6. audit
  → Transaction (kas_masuk) + Journal (posted)          [Finance]
```

- **Debit:** `debit_coa_code` from mapping. Online/Flip → `1-102 Bank BCA` (asset); cash →
  `1-101 Kas Sekolah` (asset). **Debit = cash/bank asset.** **[EXISTING]**
- **Credit:** `credit_coa_code` from mapping — `4-101 Pendapatan Sekolah` (**revenue**).
  **Credit = revenue.** **[EXISTING]**
- **Revenue recognition vs receivable settlement:** **revenue recognition at payment time
  (cash basis)**; credit straight to revenue; no receivable leg, no prior accrual. **[EXISTING]**
- **StudentBill creation → accounting entry?** **No.** Outbox emits only on VERIFIED PAID
  (`recordSppPayment` returns null unless `status==='PAID'`). UNPAID creation → no Finance
  event, no journal. Finance never sees the receivable. **[EXISTING]**
- **Piutang SPP / education receivable in Finance?** **No.** No receivable account, no
  `piutang`/`receivable` usage. **[EXISTING]**
- **Deferred / unearned revenue in Finance?** **No.** No unearned-revenue liability; the
  only seeded liability is `2-301 Titipan Tabungan Siswa` (student savings, "bukan
  pendapatan"). **[EXISTING]**
- **Payment timing vs recognition:** the **same instant** — journal dated from event
  timestamp at settle; no separate recognition schedule. **[EXISTING]**
- **Multi-month payments today:** one Midtrans `MULTI-…` over N month-bills → **N separate
  `spp.payment` events**, each its own `kas_masuk` transaction + posted journal, all
  sharing `provider_reference = MULTI-…`. A Jan–Dec single payment → **12 Finance
  transactions**. **[EXISTING]**

---

## 3. Current COA Structure and Capabilities

| Capability | Status | Evidence |
|---|---|---|
| Configurable/renameable names | Yes | `CoaController::update`; reports group by `type` |
| Account types | 5 fixed (`asset/liability/equity/revenue/expense`) | `CoaController::validateCoa` |
| Parent/child hierarchy | None (flat) | `Coa` model, migration |
| Mapping mechanism (event→COA) | `tu_event_coa_mappings` by semantic code | `TuEventCoaMapping` |
| Hardcoded numeric COA IDs | None | contract §22; resolves by code |
| Semantic role concept (CASH_BANK etc.) | Not present | full-repo search |
| Receivable (Piutang) account | Absent | full-repo search |
| Deferred/unearned revenue account | Absent | full-repo search |

**Seeded COA** (`CoaSeeder`): `1-101 Kas Sekolah` (asset), `1-102 Bank BCA` (asset),
`2-301 Titipan Tabungan Siswa` (liability), `3-101 Dana Sekolah` (equity),
`4-101 Pendapatan Sekolah` (revenue), `5-201/5-202` expenses. There is **no** seeded
SPP-specific revenue, receivable, or deferred account — SPP currently books to the generic
`4-101 Pendapatan Sekolah`. **[EXISTING]**

---

## 4. Existing SPP Accounting Treatment

**[EXISTING] — Cash basis, single generic revenue account.**

- SPP payment posts **Dr Kas/Bank (asset) / Cr Pendapatan (revenue)** at settle time.
- No receivable is ever raised; no accrual exists; no deferral exists.
- Revenue is recognized **when cash is received**, regardless of which SPP month(s) the
  payment covers.
- A parent paying Jan–Dec in January books the full amount as **January revenue** in
  Finance (spread across 12 transactions, all dated at payment time). Finance has **no
  knowledge of bill period allocation** — that lives only in TU.

This is internally consistent and reconcileable, but it is **not** accrual accounting and
does **not** represent period-earned revenue in Finance.

---

## 5. Accounting Model Comparison

Evaluated against the **actual implementation** (not general theory).

### Model 1 — Cash Basis
```
Payment:   Dr Kas/Bank        Cr Pendapatan SPP
```
- **Fit to current code:** Perfect — literally what the system does now.
- **Finance capability:** Fully supported (asset + revenue accounts exist).
- **Receivable in Finance:** No. TU remains the only place that knows what is owed.
- **Pros:** Zero new Finance concepts; simplest batch (one Dr Bank / Cr Revenue per
  period); reconciliation trivial (batch total = cash received); matches `ArusKas`.
- **Cons:** Laba Rugi shows revenue on cash receipt, not when earned; advance multi-month
  payments overstate the receipt month; no Finance view of outstanding SPP.

### Model 2 — Accrual / Receivable
```
When SPP becomes receivable:   Dr Piutang SPP     Cr Pendapatan SPP
When payment received:         Dr Kas/Bank        Cr Piutang SPP
```
- **Fit to current code:** Requires a **new event** (bill becomes receivable) that the
  current integration **does not emit** (TU only emits on PAID). Needs a new contract event
  (e.g. `spp.billed`) **and** a new `Piutang` COA. Neither exists.
- **Finance capability:** Needs a new asset (receivable) account — supported by the COA
  model, but absent today.
- **Pros:** Finance shows outstanding SPP; revenue recognized when billed (earned); receipt
  is a clean receivable settlement.
- **Cons:** Two-sided integration (billed + paid), double the events, more reconciliation
  surface, period-close coupling (receivable must reconcile to TU's UNPAID bills);
  recognition timing ("when does SPP become receivable?") is itself a **[POLICY]** question.
- **Verdict:** Most accounting-correct but the **largest architectural change**, depending
  on a billing-event contract that does not exist.

### Model 3 — Deferred Revenue (advance payment)
```
Cash received before earning:  Dr Kas/Bank                   Cr Pendapatan Diterima Dimuka (liability)
Recognize as earned:           Dr Pendapatan Diterima Dimuka Cr Pendapatan SPP
```
- **Fit to current code:** Needs a **liability** account for unearned revenue and a
  **recognition schedule** — neither exists. Recognition needs a driver (monthly run) with
  no current analogue.
- **Finance capability:** Liability accounts supported, but no unearned-revenue account and
  no recognition engine.
- **Pros:** Correctly handles advance multi-month payments (defers future months).
- **Cons:** Most moving parts; requires a periodic recognition process and a clear school
  revenue-recognition policy **[POLICY]**; highest complexity.

> **[POLICY]** None of the three can be chosen from code alone. The choice is a
> school/bendahara accounting-policy decision (cash vs accrual vs deferral, and the
> revenue-recognition policy for advance payments). This report does not decide it.

---

## 6. Recommended Accounting Semantics

**[RECOMMENDED]** Keep the integration **semantically neutral** and let the **existing
Finance-owned mapping** decide the treatment:

1. **TU continues to emit operational facts only** (event_type, payment_method, provider,
   amount, timestamp). TU never sends COA IDs or accounting instructions — already enforced
   by contract §22 and `tu_event_coa_mappings`. **[EXISTING → keep]**

2. **The accounting model is a Finance-side configuration concern**, expressed by which COA
   codes the bendahara assigns in the mapping:
   - Cash basis → `credit_coa_code` = a revenue account.
   - Deferred → `credit_coa_code` = an unearned-revenue (liability) account.
   - Accrual → requires a new billed-event contract (out of scope for config-only change).

3. **Default-safe stance:** only Model 1 is fully supported by current Finance capability,
   so **Model 1 (cash basis) is the only treatment usable today without schema/contract
   changes.** Models 2 and 3 are **[POLICY]**-gated upgrades.

**[POLICY — cannot be determined technically]:** whether the school books SPP on cash
receipt, on billing (accrual), or defers advance payments.

---

## 7. Recommended COA Semantic Roles

**Are semantic roles needed?** Yes — as a **concept layered on the existing mechanism**,
not a schema change in this task.

**Why:** The bendahara must be free to name accounts anything (`Piutang SPP`, `Piutang
Pendidikan`, `Pendapatan Pendidikan Bulanan`, …). The integration and reports must **not**
depend on those names. The stable anchor is the **semantic role**, resolved to a
bendahara-chosen COA **code** at mapping time.

**Conceptual roles (design only — NOT added to the DB):**
- `CASH_BANK` — where money lands (cash drawer vs bank/clearing). Already effectively
  modeled by `debit_coa_code` differing per `payment_method`/`provider`. **[EXISTING-equivalent]**
- `RECEIVABLE_SPP` — the SPP receivable account (only if Model 2 adopted).
- `SPP_REVENUE` — the SPP revenue account (credit under cash basis, or recognition target
  under accrual/deferral).
- `DEFERRED_REVENUE` — the unearned-revenue liability (only if Model 3 adopted).

**Recommendation:** the existing `tu_event_coa_mappings` key `(event_type, payment_method,
provider) → (debit_coa_code, credit_coa_code)` is **already a two-role semantic mapping**
(debit-role, credit-role) resolved by code — the correct minimal pattern. A formal
named-role registry is **only warranted if** the school adopts Model 2 or Model 3 (which
need more than two accounts / more than one posting step). Under current cash-basis, the
existing two-column mapping is sufficient. **[RECOMMENDED]**

**Do NOT add these enums/roles to the schema yet** — per task rules and because the need is
**[POLICY]**-dependent.

---

## 8. TU → Finance Batch Implications

Scenario: 100 parents, 250 StudentBills, one-day total **Rp125.000.000**. TU keeps every
StudentBill allocation, payment-transaction detail, gateway/provider references, and
student-level traceability. Finance should potentially receive **one** settlement/batch
representation: `SPP Settlement — Rp125.000.000`.

**What the single batch entry should represent, per model (all [POLICY]-gated except M1):**

- **Model 1 (cash basis):**
  `Dr Bank/Kas  125.000.000   Cr Pendapatan SPP  125.000.000`
  One balanced journal. Cash in = revenue. Matches today's semantics exactly, aggregated.
  **[RECOMMENDED default — supported by current Finance]**

- **Model 2 (accrual):**
  `Dr Bank/Kas  125.000.000   Cr Piutang SPP  125.000.000`
  The batch *settles* the receivable. But this requires the receivable to have been raised
  earlier (a billed-event), which does not exist. Without Model 2's billing side, a
  `Cr Piutang` batch would credit an account that was never debited — **not valid alone.**

- **Model 3 (deferred):**
  `Dr Bank/Kas  125.000.000   Cr Pendapatan Diterima Dimuka  125.000.000`
  Defers all of it, then recognizes per policy. Only correct if the school treats SPP
  receipts as unearned until the service month — a **[POLICY]** stance, and requires the
  recognition engine.

**Answer to "Dr Bank / Cr Pendapatan OR Dr Bank / Cr Piutang?":**
- Under the **current** Finance design (cash basis, no receivable), the batch is
  **`Dr Bank / Cr Pendapatan SPP`**.
- `Dr Bank / Cr Piutang SPP` is only meaningful **if** Model 2 is adopted with its billing
  event. **Cannot be recommended from current code.**

**One multi-month parent payment (Jan–Dec) in a batch — one or many journals?**
- **TU:** remains **one operational payment** (one gateway transaction), allocated to many
  StudentBills (12). Traceability preserved in TU. **[EXISTING → keep]**
- **Finance (batch):** should become **one settlement amount** within the period batch,
  requiring **one Finance journal** for the whole batch (not one per parent, and not 12).
  The bill/period allocation is **supporting detail held in TU**, referenced by the batch —
  not separate Finance journals.
- Splitting one payment into 12 Finance journals is only needed if Finance must do
  per-month revenue recognition (Model 3). Under cash basis, **one journal** suffices.

---

## 9. Multi-Month Payment Treatment

- **Current [EXISTING]:** one Midtrans `MULTI-…` → N per-bill `spp.payment` events → N
  Finance `kas_masuk` transactions/journals, all dated at payment time, full amount booked
  as current-month revenue. Finance cannot see which months were covered.
- **Cash-basis batch [RECOMMENDED default]:** the payment contributes its full amount once
  to the period total (no double-count across the N bills — the batch sums **payments**,
  or equivalently the distinct settled amounts, not bill count). One journal.
- **Deferred model [POLICY-gated]:** only here would the payment be split across months —
  current month recognized, future months held in `Pendapatan Diterima Dimuka` and released
  monthly. That split is a **recognition** concern in Finance, driven by a schedule TU does
  not currently supply and Finance does not currently run.
- **Traceability (must hold in all models):**
  `Finance batch → TU report → gateway transaction (MULTI order_id) → N StudentBill
  allocations → Jan…Dec`. Finance stores only the batch reference + total; the month
  breakdown stays in TU.

---

## 10. Period Closing / Cut-off Implications

**[EXISTING]**
- `AccountingPeriod` (`app/Models/AccountingPeriod.php`): `open|closed`, monthly
  (`year, month, start_date, end_date`), `closed_at/closed_by`.
- `AccountingPeriodService::requireOpenPeriod($date)` blocks posting into a closed/missing
  period; `close()` is one-way (no reopen).
- Journal/transaction dates are derived server-side from the event timestamp
  (`JournalService::recordKasMasuk` uses `$data['date']`, resolved from the event
  `occurred_at`/`paid_at`), never client input.
- Authoritative TU source timestamp for SPP = `StudentBill.paid_at` (Asia/Jakarta).
- Posted journals are immutable (`Journal`/`JournalDetail` guards).

**Batch interaction [RECOMMENDED]:**
- A TU period batch must carry a batch date whose **Finance accounting period is open**;
  `requireOpenPeriod` already rejects a batch dated into a closed month.
- A **monthly** TU close aligns naturally with Finance's **monthly** `AccountingPeriod`;
  daily/weekly closes would post multiple sub-period batches into one open Finance month —
  valid, but Finance period-close then lags the last TU batch of the month.
- Late/after-close settlements must land in the **next open** period (mirrors the savings
  daily-closing "no reopen" precedent), never force a Finance period reopen.

---

## 11. Reporting Implications

All Finance reports read **posted journal lines joined to COA**, never `transactions.amount`.
They classify by `coas.type` and are **name-agnostic** (so renames are safe).

| Report | Source of truth | Impact of cash-basis SPP (current) |
|---|---|---|
| **Laba Rugi** (`LabaRugiReportController`) | posted lines, `type∈{revenue,expense}`, by `journal_date` | SPP appears as **revenue in the month cash was received**; advance multi-month payments inflate the receipt month |
| **Neraca** (`NeracaReportController`) | posted lines, `asset/liability/equity` + current-result | Cash asset up, revenue in current result; **no receivable, no deferred liability** shown |
| **Arus Kas** (`ArusKasReportController`) | posted lines on `type='asset'` (cash/bank proxy); inflow=Σdebit, outflow=Σcredit | SPP cash appears as operating **inflow** in the period received — accurate under cash basis |
| **Buku Besar** (`GeneralLedgerController`) | posted lines per `coa_id`, normal-balance by `type` | Each SPP transaction is a line on the cash and revenue accounts |
| **Audit trail** (`AuditLog`) | `integration.processed/duplicate/mapping_required` + `transaction.created` | Per-event trace today; a batch would need a batch-level audit entry |

- Under a **batch** model, Laba Rugi / Neraca / Arus Kas are unchanged in total (the batch
  journal is balanced and dated), but **Buku Besar** would show one aggregate line per batch
  instead of per bill. **[RECOMMENDED]**
- If Model 2/3 were adopted, **Neraca** would start showing Piutang / Pendapatan Diterima
  Dimuka — currently impossible (accounts don't exist). **[POLICY-gated]**

---

## 12. Traceability Requirements

Regardless of model or batching:
- TU remains the source of StudentBill allocation, payment detail, gateway/provider
  references, and student identity.
- Finance stores the batch reference + total on the resulting `Transaction`
  (`source_system`, `source_record_id`, `event_id`, `idempotency_key`, `provider_reference`
  columns already exist for exactly this).
- Batch idempotency must be DB-unique on the Finance side, analogous to the existing
  `UNIQUE(source_system, idempotency_key)` guard, so Send-twice / retry / timeout cannot
  double-post.
- Chain: `Finance batch → TU report → payment transaction → StudentBill allocations →
  month(s)`. Never break this by losing the batch→report link.
- Do **not** fabricate source identity for historical rows (consistent with the existing
  no-backfill rule in `PHASE-09D` docs).

---

## 13. Open Decisions Requiring School/Bendahara Policy

These are **[POLICY]** and cannot be resolved technically:
1. **Basis:** cash vs accrual (receivable) vs deferred-revenue for SPP.
2. **Revenue-recognition policy for advance payments:** recognize on receipt, or spread
   across covered months? If spread, what schedule/driver?
3. **When SPP "becomes receivable"** (if accrual): on bill generation, on due date, on
   period start?
4. **COA naming/numbering** for any new SPP revenue / receivable / deferred accounts
   (bendahara-owned).
5. **Batch period** (daily/weekly/monthly) and how it aligns with Finance monthly close.
6. **Whether Finance needs a student-level receivable subledger** or TU remains the only
   subledger (current architecture keeps it in TU).

---

## 14. Recommended Next Implementation Task

**Next task (design→build, after the [POLICY] decisions above are made):**

> **TU-FINANCE-BATCH-01 — Define the SPP settlement batch contract and Finance batch
> ingest, under the (default) cash-basis model.**
> - Confirm the accounting basis with bendahara (**[POLICY]** gate — do not build Model 2/3
>   until decided).
> - Specify a batch event (e.g. `spp.settlement_batch`) carrying period, total, transaction
>   count, bill count, and a deterministic batch idempotency key — **no COA IDs from TU**.
> - Extend `tu_event_coa_mappings` resolution (or add a batch-keyed mapping) so Finance maps
>   the batch to `Dr CASH_BANK / Cr SPP_REVENUE` by **semantic code**.
> - Add a Finance batch-ingest path that posts **one balanced journal per batch** with a
>   DB-unique batch idempotency guard, reusing `JournalService::recordKasMasuk` and
>   `AccountingPeriodService::requireOpenPeriod`.
> - Preserve the per-bill outbox as internal trace (do not delete it); decide whether it
>   stays forward-only to Finance or is superseded by the batch.
> - Define batch reconciliation (TU batch total ↔ Finance batch journal amount ↔ gateway
>   totals).

**Explicitly NOT in this task and NOT done:** schema/migrations, semantic-role enums, COA
changes, TU integration changes, route/controller/service changes, tests.
