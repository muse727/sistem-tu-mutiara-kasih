# PHASE E.2 — FLIP SANDBOX REAL-PAYMENT END-TO-END VERIFICATION

**Date:** 2026-09-14 (Asia/Jakarta, +07:00)
**Scope:** backend/integration E2E only. Student frontend UI was NOT modified.
**Environment:** local TU (`sistem_tu`, MariaDB) + local Finance (`db_keuangan_mutiara`, PostgreSQL :5433) + Flip **SANDBOX** (`https://bigflip.id/big_sandbox_api`). No production system was touched.

---

## 1. Honest headline

| Item | Result |
|---|---|
| Real Flip sandbox **bill creation** (server-to-server) | ✅ VERIFIED against live sandbox |
| Real Flip **sandbox payment** (customer pays the bill) | ⛔ **BLOCKED** — requires manual browser interaction on Flip's React SPA payment page; this agent cannot operate a browser and there is no documented server-side sandbox settlement API |
| **Real Flip-originated callback** received | ⛔ **BLOCKED** — never received (depends on the blocked sandbox payment). NOT claimed as verified. |
| Callback authentication / validation logic | ✅ VERIFIED via simulated delivery through the real HTTP callback endpoint (valid + negative cases) |
| StudentBill settlement | ✅ VERIFIED (simulated settlement) |
| PaymentAttempt settlement | ✅ VERIFIED (exactly once) |
| Outbox `spp.payment` event | ✅ VERIFIED (exactly one, contract v1.0) |
| TU → Finance HMAC delivery | ✅ VERIFIED over real HTTP |
| Finance accounting effect | ✅ VERIFIED (1 Transaction, 1 POSTED Journal, debit=credit) |
| Reconciliation | ✅ **MATCHED** |
| Idempotency / duplicate protection | ✅ VERIFIED (callback + Finance delivery) |
| Finance-down retry | ✅ VERIFIED |

**Bottom line:** the full backend chain (callback → settlement → outbox → HMAC →
Finance journal → reconciliation) is proven working. The ONLY unverified step is
the *real Flip-originated payment + callback*, which is blocked by the sandbox's
browser-only payment simulator (no API). A human completing the sandbox payment
in a browser would exercise the exact same callback path that is verified here.

---

## 2. Disposable test record

- **Student ID:** 2, `student_uuid = deacfa46-77df-45d6-94e6-bb815c8cec93` (nis/nisn `E2E9001`)
- **StudentBill ID:** 2, type `SPP`, name `SPP E2E Flip Disposable`
- **Authoritative amount:** `75000.00` (from `StudentBill.amount`)
- **Initial state:** UNPAID, no pre-existing settled Flip attempt

## 3. Real Flip sandbox bill creation (Step 2) — VERIFIED

Created via `FlipPaymentProvider::createBill` against the **live sandbox**:

| Field | Observed value |
|---|---|
| `link_id` | `356090` (stored as **STRING**, never cast) |
| `link_url` | `flip.id/pwf-sandbox/$mustovasamsulmaarif/#sppe2eflipdisposable-5046` |
| `status` | `ACTIVE` (creation-time, awaiting payment) |
| `amount` | `75000` (echoes authoritative `StudentBill.amount`) |
| `type` | `SINGLE`, `step` `1`, `created_from` `API` |

Status inquiry `GET /v2/pwf/{link_id}/bill` returned the same `ACTIVE` bill
(`amount=75000`). **Note:** a `redirect_url` of `http://localhost:...` is rejected
by Flip (`Param redirect_url Url is invalid`); the bill was created with no
`redirect_url` (step=1) for this E2E.

Local `PaymentAttempt` persisted:
- id=1, provider=`FLIP`, provider_reference=`356090` (string), provider_status=`ACTIVE`
- status=`pending`, payment_method=`ONLINE`, payment_url=(the link_url), gross_amount=`75000.00`

**Repeated creation (Step 2 + 9F):** a second create request **reused** the open
attempt — total attempts stayed at **1**, no duplicate Flip bill was created.

## 4. Real sandbox payment (Step 3) — BLOCKED

The payment page is a React single-page app (`/static/js/main.*.js`, no
server-rendered form). Settlement requires a human clicking through the page in a
browser (choosing a sandbox bank/QRIS and confirming). This agent cannot operate
a browser. An authenticated API probe of candidate settlement endpoints
(`/v2/pwf/{id}/payment`, `/simulate`, `/pay`, `/settle`, `/confirm`, `/checkout`,
`/bill/{id}/payment`, …) all returned `404`. The authenticated
`GET /v2/pwf/payment` endpoint exists but is a read-only *payment-records list*
(returned empty). **No server-side sandbox settlement endpoint was found.** Web
search was unavailable in this session. ⇒ Step 3 (and therefore the real
Flip-originated callback in Step 4) is **BLOCKED**.

## 5. Callback handling (Step 4) — verified via SIMULATED settlement (not a real Flip callback)

Because the real payment is blocked, the settlement callback was **simulated**:
an HTTP POST to the **real** callback endpoint
`POST /siswa/payment/flip-callback` (delivered through a public cloudflared
tunnel, i.e. over the real HTTP route, not a direct controller call) with
Flip's documented callback shape: form fields `token` + `data` (JSON string).
**This is NOT a real Flip-originated callback and is not claimed as one.**

### Real defect found & fixed during E.2

The Flip callback route was **missing from the CSRF exception list** in
`bootstrap/app.php`, so any server-to-server POST (which carries no CSRF token)
was rejected with **HTTP 419**. The Midtrans callback was already exempted; the
Flip route was not. Fix (minimal, additive): added
`'siswa/payment/flip-callback'` to `validateCsrfTokens(except: [...])`.
Without this fix, Flip could never settle a bill. File changed:
`bootstrap/app.php`.

### Verified behavior

- Valid token + valid `data` (`link_id=356090`, `status=SUCCESSFUL`,
  `amount=75000`, `sender_bank=bca`) → HTTP 200, settled exactly once:
  - `StudentBill` UNPAID → **PAID**, `paid_at=2026-09-14 08:37:10`, `payment_method=ONLINE`
  - `PaymentAttempt` id=1 → status=`settlement`, provider_status=`SUCCESSFUL`, payment_channel=`bca`, `settled_at` set
  - exactly **1** outbox `spp.payment` event created in the same transaction
- Non-success statuses never settle (only `SUCCESSFUL` → PAID).
- Callback returns HTTP 200 quickly (no synchronous Finance call, no external API).

## 6. Idempotency (Step 5) — VERIFIED

Repeated identical valid callbacks (×2 more): PAID → PAID, `paid_at` unchanged,
still exactly **1** settled attempt, exactly **1** outbox event. No duplicate
settlement, no duplicate outbox, no duplicate Finance effect.

## 7. Outbox event (Step 6) — VERIFIED

Exactly one `spp.payment` event, canonical idempotency `TU:spp.payment:2`:

- `event_id=820e0599-e81b-4491-9592-83d5a3f9574f`, `event_version=1.0`, `contract_version=1.0`
- `source_system=TU`, `source_record_id=2`, `occurred_at=2026-09-14 08:37:10`
- payload: `student_uuid=deacfa46-…8cec93`, `bill_id=2`, `bill_type=SPP`,
  `amount="75000.00"`, `paid_at=2026-09-14T08:37:10+07:00`,
  `provider="flip"`, `provider_reference="356090"`, `payment_method="ONLINE"`,
  `description="SPP E2E Flip Disposable"`

## 8. TU → Finance (Step 7) — VERIFIED over real HTTP + HMAC

- Transport `integration:dispatch` → `HmacIntegrationTransport` POSTs to Finance
  `POST /api/integration/tu/events` with `X-TU-Key-Id/Timestamp/Signature`.
- HMAC authentication **succeeded** (Finance accepted; an invalid/expired
  signature is rejected — see Finance suite).
- Finance ingest result: `PROCESSED` → outbox `DELIVERED`.
- **Finance accounting effect (verified):**
  - Transaction `KM-202609-000001`, type `kas_masuk`, amount `75000.00`,
    `source_system=TU`, `source_record_id=2`, `idempotency_key=TU:spp.payment:2`,
    posted into the open Sept-2026 accounting period.
  - Journal **POSTED**, balanced: debit `75000` = credit `75000`.
  - COA resolved via **semantic mapping** `tu_event_coa_mappings`
    (`spp.payment`/`ONLINE`/`flip` → debit `1-102 Bank BCA`, credit `4-101
    Pendapatan Sekolah`). **No hardcoded COA id.**
  - Exactly **1** Transaction, exactly **1** Journal.

## 9. Reconciliation (Step 8) — MATCHED

`ReconciliationEngine::reconcileEvent` for this payment → **`MATCHED`**:
exact identity across `source_record_id=2`, `event_id`, `idempotency_key`,
`student_uuid`; `source_amount=finance_amount=75000.00`; `finance_posted=true`.
No auto-repair performed.

## 10. Negative / safety tests (Step 9) — VERIFIED (via real HTTP callback endpoint)

| Test | Result |
|---|---|
| A. Wrong callback token | HTTP 200, **no settlement** |
| B. Wrong amount (valid token) | HTTP 200, **no settlement** |
| C. Wrong `link_id` (valid token) | HTTP 200, **no settlement** |
| D. Repeated valid callback | Idempotent, PAID→PAID, no duplicate |
| E. Finance down after TU settlement | bill stays PAID; outbox → `FAILED_RETRYABLE`; after Finance recovery → `DELIVERED`; **no duplicate** Transaction/Journal |
| F. Repeated payment creation | reuses open attempt; no duplicate attempt, no duplicate Flip bill |

No security control was disabled to make these pass (the CSRF exemption for the
callback route is required for any server-to-server webhook and is matched by
constant-time token authentication in the controller).

## 11. Environment wiring performed (local dev only — not production)

- TU `.env`: added `FLIP_CALLBACK_TOKEN`, `TU_FINANCE_INTEGRATION_ENABLED=true`,
  `TU_FINANCE_INTEGRATION_ACTIVATED_AT`, `TU_FINANCE_URL=http://localhost:8000/api/integration/tu/events`,
  `TU_HMAC_KEY_ID`, `TU_HMAC_SECRET` (values redacted; never logged).
- Finance `.env`: added matching `TU_HMAC_KEY_ID`, `TU_HMAC_SECRET`.
- Finance DB: ran 2 pending migrations (`add_source_traceability_to_transactions_table`,
  `create_tu_event_coa_mappings_table`); seeded 2026 accounting periods and the
  integration system actor; created the `spp.payment/ONLINE/flip` COA mapping.

## 12. Test suites

- **TU (`php artisan test`):** could **not** run in this session — the test
  database `sistem_tu_test` does not exist and MariaDB `root` uses unix_socket
  auth (HTTP 1698 access denied for `root@localhost`). The `sistem_tu` app user
  has no `CREATE DATABASE` privilege. This is an **environment provisioning
  constraint, not a code regression**; E.1 recorded 348 passed / 1 skipped. Not
  hidden, not re-baselined.
- **Finance (`php artisan test`):** **430 passed / 8 skipped** (in-memory SQLite;
  the 8 skips are the PG-gated integration tests).

## 13. Files changed (E.2)

- `bootstrap/app.php` — added `siswa/payment/flip-callback` to CSRF exceptions (real defect fix).
- `.env` (TU) — local integration/Flip wiring (not committed; env-only).
- `docs/PHASE-E2-FLIP-SANDBOX-E2E.md` — this document.

No migration was created by E.2 (the two Finance migrations run were pre-existing
pending migrations, not new ones).

## 14. Remaining limitations / what remains before frontend work

1. **Real Flip-originated payment + callback NOT observed** (sandbox payment is
   browser-only; no settlement API). To fully close E.2, a human must open the
   sandbox `link_url` in a browser, complete the payment, and let Flip POST the
   real callback to a publicly reachable TU callback URL. The handling path that
   callback would hit is fully verified here.
2. **TU test suite unrunnable** in this session until `sistem_tu_test` is
   provisioned (needs a DB user with CREATE privilege or a DBA-created test DB).
3. Frontend (student UI) work is intentionally out of scope for E.2.
