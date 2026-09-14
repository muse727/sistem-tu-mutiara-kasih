# PHASE 09D.2 — TU TRANSACTIONAL OUTBOX & DISPATCHER

TU-side outbox foundation (Phase 09D decision). When an authoritative financial
fact becomes valid in TU, the corresponding TU Finance Contract v1 event is
persisted **atomically in the same DB transaction** as the source-state
mutation. TU-only; no Finance repo/DB/API, no network delivery, no Flip, no
Midtrans change.

---

## 1. Outbox purpose
Guarantee SOURCE STATE COMMIT + OUTBOX EVENT COMMIT succeed together. If the
transaction rolls back, neither the source mutation nor the event exists. If
Finance is unavailable later, the source stays PAID and the event stays
PENDING/retryable. The outbox is derived from TU source truth — never the other
way around.

## 2. Schema — `integration_outbox_events` (MariaDB/MySQL types)
`id` (bigint PK), `event_id` (uuid), `event_type`, `event_version`,
`contract_version`, `source_system`, `source_record_id`, `idempotency_key`,
`occurred_at` (timestamp), `payload` (JSON), `delivery_status`, `attempt_count`,
`last_error`, `delivered_at`, timestamps.

## 3. Event identity
`source_system=TU`; `event_type` ∈ {spp.payment, pos.cash_sale,
pos.kantin_payment}; `event_id` = stable UUID generated once at creation (never
regenerated on retry); `idempotency_key = TU:{event_type}:{source_record_id}`.
Never derived from name/NIS/transaction number/provider reference.

## 4. Idempotency
`UNIQUE(source_system, event_type, source_record_id)` + `UNIQUE(idempotency_key)`
(DB constraints, not app-only). Recorder uses `firstOrCreate` keyed on the
canonical triple — replay of the source operation creates no second event.
Different event types on the same `source_record_id` stay independent.

## 5. SPP event generation
`BillingPaymentService::payCash` and `handleMidtransSuccess` call
`IntegrationOutboxService::recordSppPayment` **inside** their `DB::transaction`,
after the PAID mutation + audit. Payload snapshot: student (uuid/nis/nisn/name),
bill_id, bill_type, amount (2dp string), paid_at (ISO +07:00), provider (null
CASH / 'midtrans'), provider_reference (midtrans_order_id / null),
payment_method, description. `payment_attempt_id` omitted (NOT CURRENTLY
AVAILABLE per contract).

## 6. POS cash event generation
`PosTransactionController::store` records `pos.cash_sale` **inside** its
transaction (before `DB::commit()`) only when `$isCash` (order settled PAID).
Debt (UNPAID) orders emit **no** event. Payload: transaction_code, amount/
payment_amount/change_amount (int IDR), payment_method=CASH, payment_status=PAID,
optional student snapshot, provider/reference NULL, cashier_id.

## 7. KANTIN event generation
KANTIN bills settle through the same `BillingPaymentService` path;
`recordKantinPayment` (dispatched when `type='KANTIN'`) emits
`pos.kantin_payment` with student_bill_id, pos_order_id, transaction_code,
student_uuid, amount, payment_method, paid_at, provider/reference. Emitted on
VERIFIED PAID only — never on order/bill creation or payment initiation.

## 8. Atomicity
Event creation is inside the source transaction in all three paths. Rollback of
the source leaves no event (tested). No post-commit/controller-hook/scheduled
reconstruction.

## 9. Payload snapshot
Payload is a deterministic snapshot captured at settlement (immutable). Old
events are never rebuilt from current mutable student data at dispatch time.

## 10. Delivery states
`PENDING → PROCESSING → DELIVERED | FAILED_RETRYABLE | FAILED_PERMANENT`.
Creating the event is NOT delivery; an event may stay PENDING while the source
is PAID.

## 11. Dispatcher
`OutboxDispatcher` (depends on `IntegrationTransport`) selects eligible events,
claims one at a time, delivers via the transport outside the lock, and records
the outcome. Console: `php artisan integration:dispatch`.

## 12. Claiming / concurrency
`claimNext()` runs in a transaction with `lockForUpdate()` (SELECT ... FOR
UPDATE) — sets PROCESSING + increments attempt_count, then commits the claim.
Delivery happens **outside** the lock so DB locks are never held across a
network call. Two dispatchers cannot process the same event simultaneously
(row lock serializes the claim).

## 13. Retry policy
Retryable (network/timeout/5xx) → FAILED_RETRYABLE, retried up to
`MAX_ATTEMPTS=10`, then FAILED_PERMANENT. Permanent (malformed/unsupported/
invalid) → FAILED_PERMANENT immediately (no endless retry). `attempt_count`,
`last_error`, `delivered_at` reflect real behavior. No secrets logged.

## 14. Immutability
Model `updating` guard rejects changes to event_id/type/version/
contract/source_system/source_record_id/idempotency_key/occurred_at/payload.
Only delivery/claim metadata may change.

## 15. Audit behavior
Outbox lifecycle is observable via the outbox row itself (status, attempts,
last_error, delivered_at). Creating an outbox event does **not** create a second
financial-settlement audit (the existing FinancialAuditLogger entry already
records the settlement). No credentials/HMAC/auth headers/raw provider payloads
stored.

## 16. Failure handling
Source payment commits + outbox commits; source rollback → no event; Finance
unavailable → source PAID + event PENDING/FAILED_RETRYABLE; dispatch failure →
retryable; permanent → failed_permanent; replay → one event; delivered → not
re-delivered; payload stable across retry; source never depends on delivery.

## 17. Transport boundary
`IntegrationTransport` interface + `TransportResult`. This phase binds
`NullIntegrationTransport` (returns retryable 'transport-not-configured'; never
calls a network). The HMAC-HTTPS-to-Finance transport is Phase 09D.3.

## 18. Contract validation
Every generated event is validated against
`docs/contracts/tu-finance-v1.schema.json` via the existing
`TuFinanceContractValidator` (SPP, KANTIN, POS cash events all validate — tested).
No second validator created; no contract mismatch found.

## 19. Security
No secrets/API keys/HMAC/auth headers stored in the outbox. Transport secret
will be external configuration (09D.3).

## 20. Why Finance is not contacted yet
The consume path (09D.1) and the durable event source (this phase) are proven
independently before any wire exists. The Null transport guarantees zero network
egress from TU in this phase.

## 21. Why Flip is not implemented
Provider stays behind the TU payment boundary; provider fields are references
only (`midtrans` today). No Flip client/credentials/webhook; Midtrans untouched.

## 22. Future 09D.3 HMAC transport requirements
Implement `IntegrationTransport` over HTTPS with an HMAC signature (shared
secret + timestamp window), map HTTP outcomes to TransportResult (2xx →
delivered, 5xx/timeout → retryable, 4xx contract rejection → permanent), and
bind it in place of the Null transport. No secret in code/DB.

---

## Tests
- **Added:** `tests/Feature/IntegrationOutboxTest.php` (16 tests).
- **Full TU suite:** 277 passed / 1 skipped.

## Files changed
- `database/migrations/2026_10_09_090000_create_integration_outbox_events_table.php` (new)
- `app/Models/IntegrationOutboxEvent.php` (new)
- `app/Services/Integration/IntegrationOutboxService.php` (new)
- `app/Services/Integration/IntegrationTransport.php` (new)
- `app/Services/Integration/TransportResult.php` (new)
- `app/Services/Integration/NullIntegrationTransport.php` (new)
- `app/Services/Integration/OutboxDispatcher.php` (new)
- `app/Services/BillingPaymentService.php` (atomic outbox hook)
- `app/Http/Controllers/PosTransactionController.php` (atomic POS cash hook)
- `app/Providers/AppServiceProvider.php` (transport binding)
- `routes/console.php` (integration:dispatch command)
- `tests/Feature/IntegrationOutboxTest.php` (new)
- `docs/PHASE-09D2-TU-TRANSACTIONAL-OUTBOX.md` (new)

**Old repo ~/sistem-tu touched:** NO · **Finance repo touched:** NO ·
**Finance API called:** NO · **Finance DB connection:** NO ·
**External network delivery:** NO · **Flip:** NOT IMPLEMENTED.

## Remaining blockers before 09D.3
None on the TU side. 09D.3 must implement the HMAC `IntegrationTransport`
against the Finance ingest endpoint and provide the shared secret via external
configuration.
