# TU-FINANCE-BATCH-05A — SPP RECONCILIATION TRANSPORT & ORCHESTRATION

Implements **only** the cross-system orchestration path for the existing SPP batch
reconciliation feature (BATCH-04 intentionally left the caller out). Read-only,
detection-only. No UI, no accounting change, no COA/mapping/lifecycle change, no auto-repair,
no persistence of reconciliation history, no KANTIN, no cross-database coupling.

---

## 1. Objective

Close the loop:

```
TU batch
  → build deterministic reconciliation snapshot (read-only evidence)
  → transport snapshot across the existing HMAC integration boundary
  → Finance authenticates + validates + executes reconciliation
  → deterministic reconciliation status returned to the TU caller
```

Reuse the existing integration transport architecture; do **not** create a second HMAC
mechanism.

---

## 2. Existing Transport Reuse

The existing `HmacIntegrationTransport` is **one-way fire-and-forget**: `deliver()` signs and
POSTs an outbox event and returns only a delivery outcome, discarding the response body.
Reconciliation needs the Finance **answer**, so a complementary response-returning client was
added that reuses the **same** primitives rather than duplicating the mechanism:

- **Same signing spec** (`docs/TU-FINANCE-HMAC-SPEC.md`, mirrored by Finance's
  `TuHmacVerifier`): canonical `{timestamp}\n{METHOD}\n{path}\n{sha256(raw_body)}`, signature
  `base64(HMAC-SHA256(secret, canonical))`.
- **Same headers:** `X-TU-Key-Id`, `X-TU-Timestamp`, `X-TU-Signature`.
- **Same config:** `integration.finance.{url,key_id,secret,timeout}` (env-only secret).
- **Same verifier/middleware:** Finance `TuHmacAuthentication` + `tu.hmac` guard the new
  endpoint exactly as they guard the ingest endpoint.

New TU primitive: `App\Services\Integration\HmacIntegrationClient` — a thin request→response
sender returning a normalized outcome (OK / AUTH_FAILED / CONTRACT_REJECTED / UNAVAILABLE /
NOT_CONFIGURED). It carries no business logic and never logs secrets. It does **not** touch
the outbox — reconciliation is a direct query, not a queued delivery.

---

## 3. Request/Event Contract

TU `POST /api/integration/tu/reconciliation` body:

```json
{
  "event_type": "spp.reconciliation",
  "event_version": "1.0",
  "contract_version": "1.0",
  "source_system": "TU",
  "batch_id": "<batch uuid>",
  "source_record_id": "<batch uuid>",
  "idempotency_key": "TU:SPP:{KIND}:{period_start}",
  "snapshot": { /* SppSettlementBatchReconciliationSnapshot::build() output */ }
}
```

- `event_type = spp.reconciliation` distinguishes the query from accounting ingest
  (`spp.settlement_batch`, `spp.payment`, `pos.cash_sale`).
- The snapshot is **read-only evidence**: batch identity, idempotency key, lifecycle status,
  frozen total/counts/breakdown, live recomputation, and member bill references.
- **No Finance COA IDs are sent from TU.** The snapshot carries only operational bucket
  identity (`payment_method`, `provider`); Finance resolves COA via its own mapping.

---

## 4. TU Orchestration

`App\Services\Integration\SppSettlementBatchReconciliationTransportService`:

1. Load the target `SppSettlementBatch` (by `batch_id`) — `INVALID_BATCH` if not found.
2. Build the snapshot via `SppSettlementBatchReconciliationSnapshot` (read-only).
3. Construct the reconciliation request envelope (§3).
4. Send via `HmacIntegrationClient::post(self::ENDPOINT, …)`.
5. Normalize the Finance response into a deterministic result (§6).

It never mutates StudentBill / PaymentAttempt / batch membership, never posts or repairs
accounting, never creates outbox/accounting events.

---

## 5. Finance Handling

`App\Http\Controllers\TuReconciliationController@store` (thin), route
`POST /api/integration/tu/reconciliation` guarded by `tu.hmac`:

1. HMAC auth runs first (existing middleware).
2. Envelope validation: required fields present → `INVALID_ENVELOPE`; `event_type ==
   spp.reconciliation` → else `INVALID_EVENT`; `source_system == TU` → else `INVALID_SOURCE`;
   `event_version/contract_version == 1.0` → else `UNSUPPORTED_EVENT_VERSION`; `snapshot` is
   an array → else `INVALID_SNAPSHOT`.
3. Snapshot identity must match envelope identity (`batch_id`, `idempotency_key`) → else
   `IDENTITY_MISMATCH`.
4. Hand the snapshot to `SppSettlementBatchReconciliationService::reconcile()` and return its
   result. **No reconciliation logic is duplicated in the controller.**

---

## 6. Response Contract

Finance HTTP 200:

```json
{ "result": "OK", "reconciliation": { /* SppSettlementBatchReconciliationService result */ } }
```

The `reconciliation` object (from BATCH-04) carries: `status`, `batch_id`,
`idempotency_key`, `period_kind`, `period_start`, `batch_status`, `total_amount`,
`payment_count`, `bill_count`, `finance_amount`, `finance_transaction_id`, `reason`, plus
specific mismatch fields. No secrets or internal detail are exposed.

TU normalized result (`reconcile()` return):

```
result      RECONCILED | INVALID_BATCH | UNAVAILABLE | AUTH_FAILED | CONTRACT_REJECTED
batch_id, idempotency_key
status      Finance reconciliation status (MATCHED / TU_MISMATCH / …) when Finance answered
category    reconciliation reason when not MATCHED
tu          { total_amount, payment_count, bill_count, batch_status }   (from snapshot)
finance     { total_amount, finance_transaction_id, batch_status }       (when available)
reconciliation  full Finance result object
reason      transport/validation reason when not RECONCILED
```

---

## 7. Error Semantics

| Case | Outcome |
|---|---|
| A. TU snapshot invalid / batch not found | TU `INVALID_BATCH` (no HTTP call) |
| B. Finance unavailable (timeout/network/5xx/408/429) | TU `UNAVAILABLE` |
| C. Authentication failure (401) | TU `AUTH_FAILED`; Finance `ERROR/INVALID_SIGNATURE` 401 |
| D. Contract/version failure (422) | TU `CONTRACT_REJECTED`; Finance `ERROR/<code>` 422 |
| E. Batch not found | TU `INVALID_BATCH` |
| F. Finance transaction not found | reconciliation `MISSING_FINANCE` / `PENDING` (HTTP 200) |
| G. Reconciliation mismatch | reconciliation `*_MISMATCH`/`IMBALANCE`/`TRACE_MISMATCH` (HTTP 200) |
| H. Internal unexpected | TU `UNAVAILABLE` (network) / Finance 5xx → TU `UNAVAILABLE` |

**A reconciliation mismatch (MATCHED / MISMATCHED / PENDING) is a successful answer, never a
transport failure.** Transport failures (B/C) remain strictly distinguishable from accounting
outcomes (F/G).

---

## 8. Read-only Guarantees

The entire path is detection-only. A reconciliation call never creates a journal or
transaction, never mutates AccountingPeriod / StudentBill / PaymentAttempt / batch
membership, never retries or posts accounting, and never repairs a mismatch. Finance performs
only `SELECT`s; TU performs only `SELECT`s plus one signed POST. Verified by tests asserting
Transaction/Journal/JournalDetail and batch/item/bill counts are unchanged across calls.

---

## 9. Idempotency / Repeat Calls

Reconciliation is read-only, so it is naturally idempotent. Repeated calls for unchanged
state return the same deterministic result (`repeated_calls_are_deterministic_and_create_nothing`
on Finance; `repeat reconciliation is deterministic…` on TU). No persistence was introduced to
achieve this — there is no reconciliation-history table in this task.

---

## 10. Concurrency

- **Two reconciliation calls on the same batch:** both are read-only SELECTs; results are
  identical for unchanged state. No lock needed.
- **Finance journal being posted while reconciling:** reconciliation reads committed state.
  A journal mid-post is not yet `posted`, so the batch reconciles as `PENDING` — a correct,
  non-destructive answer; re-running after commit yields the final status.
- **Batch changing state while snapshot builds:** the snapshot is a point-in-time read of the
  batch row + members. Because reconciliation is read-only and repeatable, a torn read simply
  yields a result that a subsequent call will correct; no broad lock is warranted (none was
  added).

---

## 11. CLI Invocation

Single command (no duplicate/confusing commands):

```
php artisan integration:spp-reconcile-snapshot {batch_id}            # TU snapshot only (BATCH-04)
php artisan integration:spp-reconcile-snapshot {batch_id} --send     # end-to-end via HMAC (BATCH-05A)
```

Default prints the deterministic TU snapshot (evidence). `--send` runs the full orchestration
and prints the normalized result (exit 0 when `RECONCILED`, else 1). This keeps one clear
entry point consistent with the existing `integration:*` console pattern.

---

## 12. Tests

**TU** (`tests/Feature/SppReconciliationTransportTest.php`, 11): snapshot transported with
correct HMAC signature to the reconciliation endpoint; RECONCILED result surfaces Finance
status verbatim; mismatch is a reconciliation result not a transport failure; invalid batch →
INVALID_BATCH with no HTTP call; 401 → AUTH_FAILED; network/timeout and 5xx → UNAVAILABLE;
422 → CONTRACT_REJECTED; repeat determinism + no TU mutation; client derives endpoint root
from configured URL; NOT_CONFIGURED when secret/url missing.

**Finance** (`tests/Feature/TuReconciliationEndpointTest.php`, 13): invalid signature → 401;
wrong event_type / source / version; snapshot-envelope identity mismatch; missing snapshot;
MATCHED; mismatch returned as HTTP 200 (not transport failure); MISSING_FINANCE; PENDING;
traceability mismatch; breakdown mismatch; repeated calls deterministic and create nothing.

**Regression (full suites):**
- TU: **434 passed, 1 skipped** (includes HmacTransport, SppSettlementBatch, IntegrationOutbox).
- Finance: **478 passed, 8 skipped** (PostgreSQL integration tests need a provisioned PG DB).

No existing tests weakened or removed.

---

## 13. Known Limitations

- **Request→response client is new but reuses the same HMAC spec/config/headers** — it is a
  sibling to `HmacIntegrationTransport` (which remains the outbox delivery path), not a second
  mechanism. Two classes share the signing spec because one is fire-and-forget and the other
  returns a body; the spec itself is single-sourced in `TuHmacVerifier`.
- **No reconciliation-history persistence** in this task (per constraints); repeat calls are
  deterministic but not logged as a trail.
- **In-process end-to-end** is covered by Finance endpoint tests (full HMAC round-trip against
  the Laravel app) and TU `Http::fake` orchestration tests; a live two-process network test is
  out of scope (matches the existing `Http::fake` test approach).
- **Batch lookup by `batch_id` only** (the stable cross-system identity). No lookup by period
  in this task.

---

## 14. Next Task

**TU-FINANCE-BATCH-05B — read-only reconciliation/batch UI (optional) + optional audit
trail.** Recommended scope:
1. A read-only bendahara-facing view that lists batches with lifecycle status and, on demand,
   invokes the BATCH-05A orchestration to display the deterministic reconciliation status
   (display only — no posting, no repair).
2. Optional: persist reconciliation runs for an audit trail (new, minimal, read-mostly table)
   — a separate, explicitly-scoped decision if bendahara wants history.
Auto-repair remains out of scope; reconciliation stays detection-only.
