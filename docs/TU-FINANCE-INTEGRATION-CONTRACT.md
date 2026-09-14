# TU → FINANCE INTEGRATION CONTRACT (v1 — DESIGN)

**Status:** CONTRACT DESIGN ONLY. No Finance integration is implemented. No
Finance API is called. No Flip is implemented. Midtrans remains the provider.

**Contract version:** `1.0` (draft for review)

**Supported events (Phase 09D.6-FIX):** REQUIRED `spp.payment` · OPTIONAL
`pos.cash_sale`. **`pos.kantin_payment` is NOT SUPPORTED / OUT OF SCOPE** — the
product has no KANTIN Finance integration module (see §11).

---

## 1. Purpose
Define WHAT TU is allowed to publish to the future Finance system and WHAT each
field means, so later implementation phases can consume it without guessing.

TU remains the **authoritative operational source**. Finance is a **downstream
accounting consumer**. Finance performs accounting interpretation and posting;
TU never does accounting.

## 2. Architecture boundary

```
TU (Student master · SPP · POS)
        │  validated integration event (this contract)
        ▼
Finance integration layer
        │  idempotency → mapping → Transaction → Journal → Posted GL → Reports
        ▼
   (Finance owns all accounting)
```

TU provides validated operational **facts**. Finance owns COA mapping, journal,
GL, and reports. No accounting responsibility moves into TU.

**Finance MUST NOT become the student master. Finance MUST NOT infer identity
from names.**

## 3. Identity model
Identity hierarchy for a student:

| Field | Role |
|---|---|
| `student_uuid` | **stable external identity** (immutable, opaque, unique). The ONLY cross-system student identity. |
| `students.id` | TU **internal** identity. Never used alone as external identity. |
| `nis` | operational/administrative identifier (snapshot). Nullable, can change. |
| `nisn` | supporting attribute where available (snapshot). |
| `name` | **descriptive snapshot only**. Never identity. |

**The contract MUST NOT use:** name as identity; NIS as permanent cross-system
identity; database ID alone as external identity.

## 4. Source system identity
- `source_system` — **fixed enum string**, value `"TU"` (versioned with the
  contract; not free-form).
- Distinct fields (never collapsed): `source_system`, `source_record_id`,
  `event_id`, `provider_reference`.

## 5. Event envelope
Every integration event is an envelope:

| Field | Type | Required | Notes |
|---|---|---|---|
| `event_id` | string (UUID) | yes | stable per operational fact (see §6) |
| `event_type` | enum | yes | `spp.payment`, `pos.cash_sale` |
| `event_version` | string | yes | `1.0` |
| `contract_version` | string | yes | `1.0` |
| `source_system` | enum | yes | `TU` |
| `source_record_id` | string | yes | authoritative TU record id for the event |
| `occurred_at` | ISO-8601 | yes | the authoritative event timestamp (see §13) |
| `payload` | object | yes | event-specific body |

## 6. Idempotency semantics
**Delivery is at-least-once.** Finance may receive the same event many times.

`idempotency_key` = deterministic, **source-system scoped**, **unique per
event type + source record + settled state**:

```
idempotency_key = "{source_system}:{event_type}:{source_record_id}"
```

- SPP: `TU:spp.payment:{student_bills.id}`
- POS cash: `TU:pos.cash_sale:{pos_orders.id}`
- KANTIN: ~~`TU:pos.kantin_payment:{student_bills.id}`~~ — **OUT OF SCOPE** (no KANTIN integration, 09D.6-FIX). Was:
  authoritative debt record; `pos_orders.id` is carried in payload for
  reconciliation)

Because each operational fact settles **once** (forward-only, no reversal), one
deterministic key per fact is sufficient and safe. `source_record_id` alone is
**not** the key — the event_type must disambiguate (a `pos_orders.id` and a
`student_bills.id` can coincide numerically).

**Consumer contract:** on a repeated `idempotency_key`, respond
`DUPLICATE_EVENT` (already processed) and do **not** create another accounting
transaction.

## 7. Student reference (payload object)
```json
"student": {
  "student_uuid": "<uuid>",        // IDENTITY — required
  "nis":  "2024001",               // snapshot — nullable
  "nisn": "1234567890",            // snapshot — nullable
  "name": "Ahmad Siswa"            // snapshot — descriptive only
}
```
A name/NIS change must NOT make Finance treat this as a different student —
matching is by `student_uuid` only. `source_student_id` (TU internal id) is
**NOT CURRENTLY exposed** in events (internal implementation detail); open
question §22.

## 8. SPP payment event
Represents a **verified** successful payment state of `student_bills` — NOT a
checkout start, token creation, provider order, or page open.

`event_type = spp.payment`, `source_record_id = student_bills.id`

Payload fields (all authoritative today unless marked):

| Field | Source | Class |
|---|---|---|
| `student` | §7 | identity + snapshot |
| `bill_id` | `student_bills.id` | source ref |
| `bill_type` | `student_bills.type` (`SPP`\|`PAKET`\|`LAINNYA`\|`KANTIN`) | source fact |
| `bill_month` | `student_bills.bill_month` | source fact (nullable) |
| `bill_year` | `student_bills.bill_year` | source fact (nullable) |
| `amount` | `student_bills.amount` | source financial fact |
| `payment_method` | `student_bills.payment_method` (`CASH`\|`MIDTRANS`) | source payment fact |
| `paid_at` | `student_bills.paid_at` (app clock) | source timestamp |
| `provider` | `midtrans` or `null` for CASH | reconciliation |
| `provider_reference` | `student_bills.midtrans_order_id` or `null` for CASH | reconciliation |
| `payment_attempt_id` | **NOT CURRENTLY AVAILABLE** (no FK from bill → settling attempt) | — |
| `description` | `student_bills.name` | descriptive |

## 9. SPP payment types
Real `student_bills.type` values: `SPP`, `KANTIN`, `PAKET`. (`LAINNYA` is
referenced in the UI vocabulary; treat any non-SPP/KANTIN/PAKET type as an
opaque operational type.) The event carries the operational type verbatim;
**accounting mapping belongs to Finance**. Types do not necessarily map to the
same account.

## 10. POS cash sale event
A **successfully paid** POS cash sale. `source_record_id = pos_orders.id`.

| Field | Source | Class |
|---|---|---|
| `transaction_code` | `pos_orders.transaction_code` | operational reference |
| `amount` | `pos_orders.total_amount` (integer IDR) | source financial fact |
| `payment_amount` | `pos_orders.payment_amount` | source payment fact |
| `change_amount` | `pos_orders.change_amount` | source payment fact |
| `payment_method` | `CASH` | source payment fact |
| `payment_status` | `PAID` | source fact (settled) |
| `occurred_at` | `pos_orders.created_at` (settle happens at creation) | source timestamp |
| `cashier_id` | `pos_orders.user_id` | source ref (nullable) |
| `student` | §7 — **optional** (cash sale may be anonymous) | snapshot |
| `items` | **OPEN QUESTION** — item-level vs summarized (§22) | — |
| `provider` / `provider_reference` | `null` (cash) | — |

## 11. KANTIN debt payment — OUT OF SCOPE (Phase 09D.6-FIX)
The product has **no KANTIN Finance integration module**. `pos.kantin_payment`
is **NOT a supported event type** and has been removed from this contract.
KANTIN debt bills remain a TU-internal domain concern and settle normally, but
they emit **no** Finance integration event and are never sent to Finance. If an
old `pos.kantin_payment` event is encountered, Finance rejects it as an
unsupported event type; it is never reinterpreted as `pos.cash_sale` or SPP.


## 12. Payment provider semantics
Provider-agnostic. Distinguish:
- `provider` — the gateway (`midtrans` today; `flip` later). **Not** the method.
- `payment_method` — how it was paid (`CASH`, or provider-settled → `MIDTRANS`).
- `provider_reference` — provider order/correlation id (`midtrans_order_id`).
- `payment_channel` / `provider_event_reference` — **NOT CURRENTLY AVAILABLE**
  at the event level (channel labels exist only on `PaymentAttempt`
  bank/va_number; no stable provider event id is persisted).

For cash: `provider = null`, `provider_reference = null`, `payment_method = CASH`.
No provider events are invented that TU cannot currently supply. The contract
can represent a generic provider so Flip can slot in later without payload
change.

## 13. Money semantics
- `currency` = `IDR` (verified — no multi-currency anywhere in config/code).
- **SPP** uses decimal money: `amount` is `decimal(12,2)` → emit as a
  **decimal string with 2 places** (e.g. `"150000.00"`).
- **POS** is **integer IDR**: `total_amount` is `int` → emit as an integer
  (e.g. `15000`). `payment_amount`/`change_amount` are decimal columns holding
  whole-rupiah values → emit as integers.
- Precision/rounding: **no silent rounding/truncation**. SPP = 2dp; POS = 0dp.
- `amount > 0` required. Zero and negative amounts are **invalid** and rejected.

## 14. Date/time semantics
TU timezone: `Asia/Jakarta`. Distinguish (never collapse):
- `paid_at` — **canonical accounting-input timestamp** (application clock, when
  TU recorded settlement).
- `provider settled_at` — provider clock (`PaymentAttempt.settled_at`); used for
  reconciliation, not accounting date.
- `created_at` — record creation.

**Canonical accounting date input = `paid_at`** (for POS cash = order
`created_at`). Finance determines the accounting **period** — TU does not
assign periods. All timestamps emitted as ISO-8601 with `+07:00` offset.

## 15. Snapshot vs source-of-truth classification
| Field | Classification |
|---|---|
| `student_uuid` | identity |
| `name`, `nis`, `nisn` | snapshot / descriptive |
| `amount` | source financial fact |
| `payment_method`, `payment_status` | source payment fact |
| `provider`, `provider_reference` | reconciliation reference |
| `paid_at`, `occurred_at` | source timestamp |
| `description` | descriptive |

## 16. Source record lifecycle (forward-only)
States: created → paid → (cancelled / expired / soft-deleted possible on the
UNPAID side). **No supported reversal/refund event exists.** Limitation stated
explicitly: Finance must NOT assume absence of a newer event means an old
transaction should be deleted. Once `PAID`, the event is final; there is no
"unpaid/corrected" event today (see §22).

## 17. Replay / duplicate / out-of-order
| Case | Expected behavior |
|---|---|
| delivered once | process |
| delivered twice / many | idempotent via `idempotency_key` → `DUPLICATE_EVENT` |
| older event after newer | events are independent facts; no ordering dependency in forward-only model |
| same source record, multiple legit events | N/A today (each fact settles once); future multi-event facts need versioned keys |
| invalid event | reject with error (§18) |
| unknown event version | reject `UNSUPPORTED_EVENT_VERSION` |

## 18. Error semantics
Machine-readable, minimal taxonomy:
`INVALID_EVENT`, `INVALID_IDENTITY`, `INVALID_AMOUNT`, `INVALID_REFERENCE`,
`UNSUPPORTED_EVENT_VERSION`, `DUPLICATE_EVENT`, `SOURCE_NOT_FOUND`,
`STATE_NOT_ELIGIBLE`.
**Validation rejection ≠ duplicate.** `DUPLICATE_EVENT` = already processed
(success, no new posting); the others = reject (no posting).

## 19. Security
Never expose: passwords, API secrets, payment credentials, signatures, raw
auth headers, or sensitive provider payloads. Student data is minimal and
purpose-specific (`student_uuid`, `nis`, `nisn`, `name` only).

## 20. Versioning
`event_version` and `contract_version` are explicit. **Additive** changes may
reuse the version; **breaking** changes require a new version. No silent
payload mutation.

## 21. Reconciliation
Each financial event is traceable to TU. Minimum set: `source_system`,
`source_record_id`, `event_id`, `idempotency_key`, `provider_reference`,
`student_uuid` (only fields meaningful per event). Finance can always answer
"which TU record produced this accounting transaction?"

## 22. Finance mapping boundary
The contract carries **no** hard-coded Finance COA IDs. Bad: `coa_id = 4`.
Good: operational fact (`bill_type=SPP`, `payment_method=CASH`, `amount`).
Finance maps operational type → configured COA. POS categories are likewise
not hard-coded to Finance accounts.

## 23. Open questions
1. Does Finance need POS **item-level** integration or is a summarized event
   sufficient? (No Finance repo present locally to confirm.)
2. How should `PAKET` / `LAINNYA` map? (Finance decision — TU only reports type.)
3. Does Finance require cashier identity (`pos_orders.user_id`)?
4. Should `source_student_id` (TU internal id) be exposed, or is
   `student_uuid` sufficient?
5. Is `paid_at` acceptable as the accounting date input?
6. Does Finance require branch/outlet? (Not present in TU.)
7. How should future refunds/reversals be represented? (None exist today.)
8. Does integration need a **durable outbox**, or push/pull?
9. Transport/auth mechanism (push webhook vs pull; HMAC vs token)?

## 24. Known unsupported scenarios
- Refunds, reversals, voids — **no events exist**.
- Partial payments — `PARTIAL` status exists but no partial accounting; not
  published.
- Multi-currency — IDR only.
- Item-level POS accounting — pending §23.1.

## 25. Examples
See `docs/contracts/examples/` for SPP payment and POS cash sale
payment examples. All fields are values TU can authoritatively supply today;
unavailable fields are marked, never fabricated.
