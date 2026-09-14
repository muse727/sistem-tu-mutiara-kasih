# PHASE E.0 — FLIP PAYMENT GATEWAY & MIDTRANS REMOVAL AUDIT

**READ-ONLY audit + migration plan. No code, schema, payment-behavior, Finance,
or contract changes were made. No Flip or Midtrans API calls. No production
access.**

**Target:** `Student → SPP → FLIP → Verified PAID → TU Outbox → Finance`.
The Finance-side event contract **stays `spp.payment`**; the payment provider
remains an implementation detail of the TU payment flow.

> **Research limitation (honest):** outbound web access was unavailable during
> this audit, so current official Flip documentation could not be fetched live.
> All Flip-specific statements below are marked **UNVERIFIED** and are based on
> Flip's publicly-documented "Flip for Business" v2 API model from prior
> knowledge. **They MUST be confirmed against the live official docs
> (https://docs.flip.id) in E.1 before any implementation.** Where a statement
> is a TU fact or an architectural decision, it is labelled as such.

---

## 1. Current payment architecture
TU is the authoritative operational source. Two SPP settlement paths:
- **Cash:** `BillingPaymentService::payCash` → StudentBill PAID → outbox
  `spp.payment` (provider null) → Finance.
- **Online (Midtrans):** student/parent requests a Snap token → `PaymentAttempt`
  (pending) → Midtrans → settlement webhook → `BillingPaymentService::
  handleMidtransSuccess` → StudentBill PAID → outbox `spp.payment`
  (provider=midtrans) → Finance.

## 2. Midtrans dependency inventory (by class)
| Reference | Where | Class |
|---|---|---|
| `midtrans/midtrans-php ^2.6` | composer.json/lock | A (provider impl) |
| `Midtrans\Config` bootstrap | `MidtransServiceProvider` | A |
| `Snap::getSnapToken` | `PaymentSiswaController`, `ParentApiController` | A |
| webhook signature `sha512(order_id+status_code+gross_amount+serverKey)` | `MidtransController::callback`, `PaymentSiswaController::callback` | A |
| statuses `capture/settlement/expire/cancel/deny` | `MidtransController`, `PaymentSiswaController` | A |
| `student_bills.midtrans_order_id` | schema + model + controllers | B (provider identity/reference) |
| `PaymentAttempt.order_id/snap_token/transaction_id/bank/va_number` | schema + model | B |
| `PaymentAttempt.gross_amount/payment_method/status/settled_at/expired_at` | schema + model | C (mostly generic lifecycle, provider-named) |
| `handleMidtransSuccess(orderId)` | `BillingPaymentService` | C (business settle keyed by provider ref) |
| `config/services.php` midtrans block + `MIDTRANS_*` env | config | A |
| routes `/payment/callback` (web), `/midtrans-callback` (api) | routes | A |
| Snap JS / client key | `resources/views/siswa/*` | F (frontend) |
| provider literals `MIDTRANS`/`midtrans` in contract validator + Finance `TuFinanceEvent` + `tu_event_coa_mappings` schema | TU + Finance | **E (API contract) / J (Finance dependency)** |
| Midtrans tests | `MidtransWebhookTest`, `SppMultiCallbackTest`, `PaymentAttemptLifecycleTest`, etc. | G |

## 3. Provider/business boundary
**Generic payment business logic (keep):** create/reuse PaymentAttempt, verify
authoritative `StudentBill.amount`, settle bill, mark PAID, set `paid_at`,
idempotency guard, outbox event, audit.
**Provider-specific (isolate behind an adapter):** Snap token creation, webhook
signature verification, status parsing, provider reference (`midtrans_order_id`),
Midtrans channels (bank/VA/GoPay/QRIS), Midtrans config.

## 4. Database field audit
| Field | Verdict |
|---|---|
| `student_bills.midtrans_order_id` | Provider-named. **GENERALIZE** (rename to a generic `provider_reference` or read via the settled PaymentAttempt) **in E.1+**, but keep historical values. NOT the business identity (bill id is). |
| `student_bills.payment_method` (`CASH`/`MIDTRANS`) | Business fact. See §5 — semantics decision required. |
| `student_bills.paid_at` | Generic. Keep. |
| `payment_attempts.order_id` | Provider reference (Midtrans order id). Generalize concept to `provider_reference`; keep history. |
| `payment_attempts.snap_token` | Midtrans-specific. Provider metadata; Flip uses a different artefact (redirect link / payment id). |
| `payment_attempts.transaction_id` | Provider transaction id. Generic-able. |
| `payment_attempts.status`, `initiated_at`, `settled_at`, `expired_at`, `source` | Generic lifecycle. Keep. |
| `payment_attempts.payment_method`, `bank`, `va_number` | Channel metadata. Keep generic; Flip channels differ. |
| `payment_attempts.gross_amount` | Generic-able name (`amount`). Authoritative check stays against `StudentBill.amount`. |
**No migration run in E.0.** Historical payment records must remain traceable.

## 5. Payment method semantics (DECISION REQUIRED)
Current canonical `payment_method` is `CASH` | `MIDTRANS` — i.e. it currently
conflates *mechanism* with *provider*. After Flip migration, do **not** blindly
map `MIDTRANS→FLIP`. Recommended canonical model (DECISION, E.1):
- `payment_method = ONLINE` (mechanism; cash stays `CASH`)
- `provider = flip` (new) — historical rows keep `midtrans`
- `payment_channel = QRIS | VA | BANK_TRANSFER | EWALLET` (provider channel)
This matches §17 of the contract's provider-agnostic intent. **Impact:** the v1
contract currently hard-codes `payment_method=MIDTRANS`/`provider=midtrans`
(see §12) — a coordinated contract change is required.

## 6. PaymentAttempt analysis
`PaymentAttempt` is ~80% provider-agnostic already (student_bill_id, status
lifecycle, gross_amount, initiated/settled/expired_at, source). Provider-specific
bits: `snap_token`, and the assumption that `order_id` is a Midtrans order id.
**Target (E.1, do not force now):** keep `PaymentAttempt`; generalize
`order_id → provider_reference`, add `provider` column, replace `snap_token`
with a generic `provider_payload`/redirect-link field. Reuse the same model for
Flip.

## 7. Webhook analysis
**Current (TU fact):** Midtrans POSTs to `/payment/callback` (web) and
`/midtrans-callback` (api); signature = `sha512(order_id.status_code.gross_amount.serverKey)`;
`capture|settlement` → settle, `expire|cancel|deny` → cancel attempt.
**Target (DECISION):** Flip callback → verify → resolve PaymentAttempt →
`BillingPaymentService` settle (same business service). Only the provider
verification/parsing changes; the settle path is reused. Finance stays
downstream of verified TU state (never `Flip callback → Finance` directly).

## 8. Flip product/API findings (UNVERIFIED — confirm in E.1)
- **Product:** Flip for Business "Accept Payment" (Bill Payment / Payment Link).
  Evaluate whether it supports a hosted checkout/payment link with QRIS/VA/
  e-wallet, or whether a Payment Link product is the right fit for SPP.
- **Payment creation (UNVERIFIED):** POST create bill/transaction with amount,
  reference/title, expiry, redirect; response returns a payment id + link/QRIS.
- **Callback (UNVERIFIED):** asynchronous notification to a configured URL with
  a transaction id + status; authenticity verification mechanism (token/
  signature/IP allowlist) must be confirmed.
- **Status query (UNVERIFIED):** server-side "get transaction by id" endpoint
  for reconciliation when a callback is missed.
- **Authentication (UNVERIFIED):** secret-key based (HTTP Basic with secret key
  historically); exact scheme must be confirmed.
- **Amount semantics (UNVERIFIED):** integer IDR (no decimals) historically —
  must confirm; TU must continue verifying against authoritative
  `StudentBill.amount`, no floats.
- **Idempotency (UNVERIFIED):** whether Flip supports an idempotency key on
  creation; duplicate-callback behavior must be handled defensively regardless.

## 9. Provider status mapping (UNVERIFIED — confirm in E.1)
Conceptual mapping target (Flip status → TU canonical): success/`SUCCESSFUL`/
settled → PAID; pending → UNPAID/attempt pending; failed/cancelled/expired →
attempt cancel/expire. **TU canonical state (`UNPAID`/`PAID`) remains the source
of truth; provider status never becomes business truth.** Exact Flip status
strings must come from official docs.

## 10. Amount behavior
Flip-reported amount must equal authoritative `StudentBill.amount` exactly
(integer IDR, no floats). Existing SPP amount-integrity rules are unchanged
unless Flip's real API requires a safe adaptation (confirm integer-vs-decimal).

## 11. Idempotency (CRITICAL)
Flip provider references **MUST NOT** become the TU Finance event identity.
Event identity stays `TU:spp.payment:{student_bills.id}`. Provider idempotency
is separate: duplicate Flip callbacks must not create a second PaymentAttempt,
settlement, outbox event, or Finance effect. Existing TU guards
(`handleMidtransSuccess` already-PAID check, unique PaymentAttempt, outbox
firstOrCreate) carry over; the Flip adapter must preserve them.

## 12. Finance compatibility (IMPORTANT FINDING)
The v1 contract **hard-codes** provider semantics in three places:
- TU `TuFinanceContractValidator`: `payment_method` enum `CASH|MIDTRANS`,
  `provider` must be `midtrans` for MIDTRANS.
- Finance `TuFinanceEvent`: `CASH` → provider null; `MIDTRANS` → provider
  `midtrans` + non-empty reference; anything else → `INVALID_EVENT`.
- `tu_event_coa_mappings` schema comments reference `midtrans`.
**Consequence:** a Flip-settled SPP event (e.g. `payment_method=ONLINE,
provider=flip`) would currently be **rejected** by both validators. Migrating
to Flip therefore requires a **coordinated, versioned contract change** (add
`ONLINE`/`flip` to the allowed provider semantics on BOTH sides) — this is the
single cross-system touchpoint. It is **NOT done in E.0** and is flagged as
**REQUIRED IN E.1** (with Finance kept in lockstep, despite §32's "no Finance
changes in E.0" — the change happens in a later phase, jointly).

## 13. Historical data policy
Historical Midtrans records (StudentBills, PaymentAttempts, webhook refs,
Finance `provider=midtrans` transactions) **remain as historical Midtrans
data**. No rewrite of history. A compatibility read layer is preferred over
backfill. Finance historical accounting stays traceable.

## 14. Test impact
- **Provider-specific (replace with Flip tests):** `MidtransWebhookTest`,
  `SppMultiCallbackTest` (Midtrans callback), Midtrans Snap-path tests in
  `PaymentAttemptLifecycleTest`, `ParentApiPaymentTest`.
- **Generic business (keep):** `BillingIntegrityTest`, `PaymentWritePathTest`,
  `FinancialAuditTrailTest`, cash-path tests.
- **Finance integration (provider-agnostic except provider-metadata cases):**
  keep; provider-metadata assertions updated only when the contract adds
  `flip`/`ONLINE`.
- **Obsolete:** none deleted without replacement.

## 15. Frontend impact
`resources/views/siswa/*` load Midtrans Snap JS and use the client key +
`snap.pay`. **Required migration (E.4):** replace Snap popup with Flip's hosted
payment link/redirect (or QRIS display), remove Snap JS + client key. Not
implemented now.

## 16. Configuration comparison
Current: `MIDTRANS_SERVER_KEY`, `MIDTRANS_CLIENT_KEY`, `MIDTRANS_IS_PRODUCTION`,
`MIDTRANS_IS_SANITIZED`, `MIDTRANS_IS_3DS`. **Future (UNVERIFIED names —
confirm):** `FLIP_API_URL`, `FLIP_SECRET_KEY`, plus whatever the official docs
require (webhook/callback URL, environment toggle). **No Flip secrets created
in E.0.**

## 17. Removal policy
- **REMOVE (forward):** MidtransServiceProvider, Snap token creation, Midtrans
  signature/status handling, `midtrans/midtrans-php` package, Snap JS, Midtrans
  config block — only after Flip passes sandbox E2E.
- **RETAIN FOR HISTORY:** historical `provider=midtrans` values, historical
  PaymentAttempts/webhook refs, Finance historical provider references,
  `student_bills.midtrans_order_id` historical values.
- **GENERALIZE:** provider-named business fields (`midtrans_order_id`,
  `gross_amount`, `payment_method` semantics) to generic provider/
  reference/channel naming.

## 18. Migration strategy (sequence; not implemented in E.0)
- **E.1** Provider-boundary cleanup: introduce a provider-agnostic adapter
  interface; generalize PaymentAttempt/provider fields; **coordinated contract
  change** to accept `ONLINE`/`flip` (TU + Finance in lockstep).
- **E.2** Flip sandbox adapter (create payment, status query) against official
  docs.
- **E.3** Flip callback/webhook verification → reuse `BillingPaymentService`.
- **E.4** SPP + Flip E2E (sandbox), frontend migration off Snap.
- **E.5** Disable/remove Midtrans (per §17); keep historical data.

## 19. Cutover strategy
- Add a provider activation flag (e.g. `PAYMENT_PROVIDER=midtrans|flip`).
- Flip passes sandbox → set `PAYMENT_PROVIDER=flip` → new SPP payments go to
  Flip only.
- **Old unpaid Midtrans attempts:** expire/cancel; do not honour new Midtrans
  settlements after cutover (or keep the Midtrans callback temporarily for
  in-flight attempts, time-boxed).
- **Old paid Midtrans bills:** untouched; historical.
- **Not** a permanent dual-provider state — final state is FLIP active,
  Midtrans inactive/removed, history retained.

## 20. Rollback strategy
- Before activation: restoring old code/config reverts cleanly.
- After activation: source payment state (StudentBill) stays authoritative;
  rollback must not create duplicate PaymentAttempts/outbox events/Finance
  effects. The outbox + already-PAID guards prevent duplicates on either
  provider. Document a migration checkpoint (provider flag + pending-attempt
  state) before cutover.

## 21. Security comparison (UNVERIFIED for Flip — confirm)
Current Midtrans: server-key signature (`sha512(...)`) on callback; config via
server/client keys. Flip (confirm): callback authenticity mechanism (token/
signature/IP allowlist), secret-key API auth, replay/timestamp support, secret
rotation. **Do not weaken current security** — Flip webhook verification must
match or exceed the current signature check. Never log secrets/signatures/
auth headers/raw sensitive payloads.

## 22. Local development requirements
- Flip sandbox credentials (NOT created in E.0).
- Local callback strategy: fake-HTTP tests first; a tunnel (e.g. ngrok) only if
  a live sandbox webhook test is needed later.
- Test fixtures for Flip create/callback/status payloads (from official docs).
- No external API calls in E.0.

## 23. Exact implementation sequence (summary)
E.1 boundary+contract → E.2 sandbox adapter → E.3 webhook → E.4 E2E+frontend →
E.5 remove Midtrans. Each gated on tests + official-docs confirmation.

## 24. Open questions
1. Which exact Flip product fits SPP (Accept Payment vs Payment Link)?
2. Flip callback authenticity mechanism (signature/token/IP allowlist)?
3. Flip status strings + terminal-state set?
4. Flip amount representation (integer IDR vs decimal)?
5. Flip idempotency-key support on payment creation?
6. Flip sandbox base URL + auth scheme + credential requirements?
7. Does the contract adopt `payment_method=ONLINE, provider=flip,
   payment_channel=…` (recommended) — and who approves the v1.1 contract bump?
8. Time-box for honouring in-flight Midtrans attempts post-cutover?

## 25. Risks
- **Contract coupling (highest):** provider semantics are hard-coded in the v1
  contract on both sides → requires a coordinated, versioned change; a naive
  TU-only change breaks Finance validation.
- **Unverified Flip API:** all Flip facts need official-docs confirmation.
- **Amount format mismatch** (integer vs decimal) could break amount integrity.
- **Webhook authenticity** unknown → security regression risk if assumed.
- **Pending Midtrans attempts** at cutover need explicit handling.
- **Frontend Snap removal** is user-facing and needs care.

---

### Verification
`~/sistem-tu` untouched · Finance untouched · no Flip API calls · no Midtrans
API calls · no production DB access · no schema changes · no payment-behavior
changes.

**Code changes: NONE · Database changes: NONE · Flip API calls: NONE · Midtrans
API calls: NONE · Finance modified: NO · Legacy `~/sistem-tu`: NOT TOUCHED.**
