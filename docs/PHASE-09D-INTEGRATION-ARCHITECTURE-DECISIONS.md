# PHASE 09D — TU → FINANCE INTEGRATION ARCHITECTURE DECISIONS

Design/decision phase. **No production integration transport, no live data
flow, no DB connection between systems, no Flip, no deploy.** Decisions are
grounded in the verified current state of both repositories (09A–09C.3).

---

## 1. Executive summary
The integration is a **forward-only, at-least-once, idempotent event feed**
from TU (operational source of truth, MariaDB/MySQL) to Finance (accounting
source of truth, PostgreSQL). TU persists each verified financial fact as a
durable integration event (transactional outbox) and pushes it over an
authenticated HTTPS channel; Finance consumes it through the already-built
`TuFinanceIntegrationService` boundary (validate → idempotency → identity →
map → transaction → journal → post → audit). Databases remain separate. No
accounting logic moves into TU; no student mastering moves into Finance.

## 2. Final architecture
```
TU (MariaDB/MySQL)                         FINANCE (PostgreSQL)
┌──────────────────────┐                   ┌───────────────────────────────┐
│ Student Master       │                   │ Integration Boundary          │
│ SPP  (student_bills) │  verified fact    │  1 validate (TuFinanceEvent)  │
│ POS  (pos_orders)    │ ───────────────▶  │  2 idempotency (UNIQUE key)   │
│        │             │  outbox event     │  3 identity (student_uuid)    │
│        ▼             │  over auth HTTPS  │  4 map (Finance-owned COA)    │
│  INTEGRATION OUTBOX  │                   │  5 JournalService.recordKasMasuk
│  (durable, per fact) │                   │  6 Transaction → Journal      │
└──────────────────────┘                   │  7 post → audit               │
        payment provider (Midtrans;        └───────────────────────────────┘
        later Flip) is a TU concern only               │ posted GL / reports
```
- TU DB and Finance DB **remain separate** (different engines by design).
- TU is the **operational** source of truth; Finance is the **accounting**
  source of truth.
- Payment provider is **behind the TU boundary**, never part of Finance identity.

## 3. Student reference decision — **B: `student_uuid` carried on the event**
**Decision:** carry `student_uuid` on the transaction's source payload (event),
**not** a Finance student row, for the initial scope.

**Reason:**
- Finance `Transaction` does not reference `Student` (verified: no student FK on
  transactions). The accounting events (SPP/POS/KANTIN) only need a *reference*
  for reconciliation, not a Finance student entity.
- The existing Finance `students` table exists solely for the **savings** domain
  (`StudentSavingsAccount.student_id`, one-to-one) and its `firstOrCreate` keys
  on `nis` — an existing *local-student* assumption that must NOT be reused as
  TU identity. Creating Finance student rows for TU payment events would risk a
  shadow student master and identity-by-NIS.
- Minimum model that satisfies actual Finance requirements = reference-only.

**Identity rule:** cross-system identity is `student_uuid` only; never name/NIS/NISN.
**Future option (not chosen now):** if a later requirement (e.g. per-student
subledger/report) needs a Finance-side student entity, create a reference row
with `source_system='TU'` + `external_student_id=student_uuid` (the schema and
UNIQUE pair already exist) — but that is deferred and is NOT part of the first
scope. No bulk student sync.

## 4. Transport decision — **A: TU push → Finance over authenticated HTTPS**
**Decision:** TU pushes each outbox event to an authenticated Finance ingest
endpoint (HTTPS POST).

**Reason:** matches the contract's at-least-once + idempotent-consumer model;
TU is the natural initiator (it knows when a fact becomes verified); simplest
reliable push with replay via the outbox; works in local dev without extra
infrastructure. A shared queue/broker adds operational weight with no current
benefit; Finance-pull would require Finance to poll TU and complicates
exactly-once-per-fact semantics; file exchange is fragile and unauditable.

## 5. Outbox / event-persistence decision — **B: transactional outbox (required)**
**Decision:** TU transactionally persists a durable integration-event (outbox)
row in the SAME DB transaction that marks the source fact PAID.

**Reason:** the failure scenario is decisive — if a payment becomes PAID while
Finance is down, the payment **must** remain PAID in TU and the integration
signal **must not** be lost. Generating-and-pushing directly (A) can lose the
signal on crash; rebuilding from source (C) cannot reproduce immutable
snapshots/ids reliably. A transactional outbox guarantees the event is
recoverable and replayable. The outbox is **event persistence, not a new
event platform.**

**Minimum outbox fields:** `event_id`, `event_type`, `event_version`,
`contract_version`, `source_system`, `source_record_id`, `idempotency_key`,
`occurred_at`, `payload` (JSON), `delivery_status` (PENDING/DELIVERED/FAILED),
`attempt_count`, `last_error`, `delivered_at`, timestamps.

**Replay:** an undelivered/failed outbox row is re-pushed; Finance idempotency
makes replay safe. This phase DECIDES the outbox; implementation is 09D.2.

## 6. POS granularity decision — **A: summarized sale**
**Decision:** POS events carry the **summarized** sale (`total_amount`,
`payment_amount`, `change_amount`, payment method/status, transaction_code,
optional student, cashier) — **not** item lines.

**Reason:** Finance is COA-based and currently needs the **total** to post
revenue; there is no verified requirement for per-item accounting. TU
`pos_order_items` (pos_item_id, quantity, price_at_transaction) and `pos_items`
(code/name/category) remain retrievable via `source_record_id` for later
reconciliation. No product/category sync is introduced. If a future requirement
needs item-level revenue split, that is an additive contract change (v1.x), not
part of the first scope.

## 7. Accounting-mapping ownership — **Finance-owned, semantic-code config**
**Decision:** TU supplies operational facts (`event_type`, `amount`,
`payment_method`); Finance maps them to accounts via a **Finance-owned mapping
keyed by semantic identifiers** (`event_type` + `payment_method` → COA **codes**
like `1-101`, `4-101`), never numeric DB IDs and never stored in TU.

**Reason:** matches the 09C mapping boundary and the 09C.2 service, which already
resolves COA by semantic code. A small Finance-side mapping configuration
(table or config) keyed on `event_type`+`payment_method` is the mechanism;
hard-coded numeric IDs are rejected. Journal structure stays in `JournalService`.

## 8. Idempotency ownership
- **TU** guarantees one canonical event identity per source fact:
  `event_id` + `idempotency_key = {source_system}:{event_type}:{source_record_id}`.
- **Finance** guarantees one accounting effect per key, enforced at the DB by
  `UNIQUE(source_system, idempotency_key)` on `transactions`.
- Same event ×10 → same event identity (TU) → one Transaction/Journal/posted
  effect (Finance). Idempotency never depends on student name, transaction
  number, or provider reference alone. Forward-only facts settle once, so the
  key needs no state/version suffix in v1 (documented in 09C §6).

## 9. Retry / replay model
- **Owner: TU (via the outbox + transport worker).** TU owns redelivery; Finance
  is stateless per request and idempotent.
- Retryable (Finance unavailable, timeout, network fail after Finance committed,
  Finance 5xx) → **retry safely** (outbox stays PENDING; replay is idempotent).
- Permanent validation failure (malformed, unsupported version, invalid
  identity/amount/reference) → **do not endlessly retry** (mark FAILED, surface).
- Duplicate → acknowledge safely (`DUPLICATE_EVENT` = success, no new posting).
- Closed period → **deterministic business failure** (`STATE_NOT_ELIGIBLE`)
  requiring reconciliation/manual action; **no** silent reassignment, **no**
  reopen. Finance never alters TU source state on failure.

## 10. Authentication decision — **HMAC-signed requests (shared secret)**
**Decision:** Finance ingest endpoint requires an **HMAC signature** over the
raw body + a timestamp header, using a pre-shared integration secret.

**Reason:** strong replay protection (timestamp window + signature over body),
secret rotation is simple, auditable, and feasible in local dev. mTLS is
heavier than needed; a bare API token lacks replay/tamper protection; internal
network trust alone is insufficient. Secrets are environment-provided — **no
production secrets are created in this phase** and the transport is not yet
implemented.

## 11. Initial scope — **all three, forward-only**
`spp.payment`, `pos.cash_sale`, `pos.kantin_payment`. All three are equally
ready (contract examples validate; the 09C.2 service ingests all three; PG
readiness verified). No refunds/reversals (source systems lack mature support),
no student bulk sync, no historical backfill.

## 12. Historical data policy — **A: forward-only from activation**
No backfill. Avoids inventing source identity for historical Finance rows,
avoids duplicate accounting, and eases reconciliation. If a future phase needs
history, it must define explicit dedup + source-identity rules (deferred).

## 13. Accounting-date policy
- **Canonical source timestamp per event:** `paid_at` for `spp.payment` and
  `pos.kantin_payment`; `occurred_at` (order creation/settle) for
  `pos.cash_sale`.
- Finance derives `transaction_date` from that timestamp **in Asia/Jakarta**
  (pinned in 09C.2). TU does **not** assign the accounting period.
- Edge cases: `23:59 +07:00` and `00:01 +07:00` resolve to the correct Jakarta
  date (tested). Provider `settled_at` is reconciliation-only, never the
  accounting date.

## 14. Failure / reconciliation model
Conceptual delivery states (minimum observable, carried on the outbox + Finance
result): `RECEIVED` → `PROCESSED` / `DUPLICATE` / `REJECTED` /
`FAILED_RETRYABLE` / `FAILED_PERMANENT`. Every event stays traceable via
`event_id` + `idempotency_key` + `source_record_id`. No large monitoring system
yet; minimum observability = outbox `delivery_status`, `attempt_count`,
`last_error`, plus Finance's deterministic result codes.

## 15. Security / data minimization
Send only accounting/traceability/reconciliation fields. Never send passwords,
parent/payment credentials, raw provider payloads, or unnecessary personal
data. Student `name`/`nis`/`nisn` are descriptive snapshots, never identity.
HMAC signature and secret never logged.

## 16. Versioning
`event_version`/`contract_version` = `1.0`. Finance **rejects** unsupported
versions (`UNSUPPORTED_EVENT_VERSION`). **Additive** fields may be tolerated
per validation design; **breaking** changes require a new version. Version
discovery/negotiation is out of scope for v1 (both sides pin 1.0).

## 17. Rejected alternatives and why
- **Student:** Finance student reference row (A) / hybrid (C) — deferred; no
  current Finance need, and reuse of the savings `nis`-keyed `firstOrCreate`
  would risk identity-by-NIS and a shadow master.
- **Transport:** Finance pull (B) — polling + harder exactly-once; shared queue
  (C) — unnecessary broker weight; file exchange (E) — fragile/unauditable.
- **Outbox:** push-directly (A) — can lose the signal; rebuild-from-source (C) —
  cannot reproduce immutable event identity/snapshots.
- **POS granularity:** item-level (B/C) — no current accounting need; adds
  product/category sync burden.
- **Auth:** mTLS (heavy), bare token (no replay protection), network-trust-only
  (insufficient).
- **Mapping:** numeric COA IDs / TU-held account numbers — violates ownership
  boundary; semantic codes chosen.

## 18. Remaining implementation prerequisites
1. TU outbox table + dispatcher (09D.2).
2. Finance authenticated ingest endpoint + HMAC middleware (09D.3).
3. Finance-owned event→COA mapping configuration (semantic codes).
4. Shared integration secret provisioning (env; no repo secrets).

## 19. Rollout sequence
Forward-only activation per event type (SPP → POS cash → KANTIN), with the
outbox replaying any backlog accumulated during rollout. Reconciliation via
`source_record_id`/`idempotency_key` before expanding scope.

## 20. Open questions
1. Exact Finance mapping config storage (table vs config) — pick in 09D.4.
2. Whether KANTIN debt settlement should reference a distinct revenue/liability
   COA than cash POS — Finance accounting decision (09D.5).
3. Final HMAC header/canonicalization spec — finalize in 09D.3.

---

## Implementation plan (not implemented here)
- **09D.1** Finance integration schema completion — event→COA mapping config +
  ingest endpoint skeleton (auth, version gate) wired to existing service.
- **09D.2** TU event persistence/outbox — transactional outbox table + writer
  on SPP/POS settle + dispatcher.
- **09D.3** Authenticated transport — HMAC signing (TU) + verification
  (Finance), timestamp window, replay protection.
- **09D.4** SPP → Finance — end-to-end `spp.payment` ingest (forward-only).
- **09D.5** POS → Finance — `pos.cash_sale` + `pos.kantin_payment` ingest.
- **09D.6** Reconciliation — outbox status vs Finance results; failure surfacing.
- **09D.7** E2E tests — PG-backed ingest + replay/idempotency/closed-period.

**TU modified:** NO · **Finance production code modified:** NO ·
**Databases modified:** NO · **Flip implemented:** NO ·
**Actual TU → Finance integration:** NO.
