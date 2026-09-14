# PHASE E.0.2 — FLIP ACCEPT PAYMENT V2 CONTRACT (EMPIRICALLY VERIFIED)

**Scope:** sandbox-only verification performed during Phase E.1. This document
records the **actually observed** Flip Accept Payment V2 contract, correcting
and completing the read-only preflight (`PHASE-E0.1-FLIP-SANDBOX-PREFLIGHT.md`).

**No production Flip call was made. The secret was read from `.env` in-process
only and never printed, logged, or committed. No secret value appears here.**

---

## 1. Environment

| Item | Value |
|---|---|
| Base URL | `https://bigflip.id/big_sandbox_api` (VERIFIED) |
| Auth | HTTP Basic, `base64(secret_key + ':')` — secret = username, empty password |
| Accept | `application/json` |
| Content-Type (create) | `application/x-www-form-urlencoded` |

Auth confirmed: requests authenticate (no 401); validation/auth failures return
structured JSON, not HTML.

---

## 2. Create Bill — `POST /v2/pwf/bill`

**Request:** form-urlencoded.

| Field | Required | Notes |
|---|---|---|
| `title` | yes | bill title |
| `type` | yes | `SINGLE` observed |
| `amount` | yes | integer IDR, no decimals |
| `step` | yes | `1` = input step; `2` = payment-method step |
| `sender_name` | **required when `step=2`** | code 1070 if absent |
| `sender_email` | **required when `step=2`** | code 1072 if absent |
| `redirect_url` | optional | return URL after payment |
| `expired_date` | optional | ISO timestamp |

**Verified error probes (no guesswork):**
- `step=2` without `sender_name` → HTTP 422 `{"code":"VALIDATION_ERROR","errors":[{"attribute":"sender_name","code":1070,...}]}`
- `step=2` with `sender_name` but no `sender_email` → HTTP 422 `code 1072`
- `step=1` with no sender fields → **HTTP 200 (success)**

**Response (200):**

```json
{
  "link_id": 356056,
  "link_url": "flip.id/pwf-sandbox/$merchant/#slug",
  "title": "…",
  "type": "SINGLE",
  "amount": 15000,
  "redirect_url": "",
  "expired_date": null,
  "created_from": "API",
  "status": "ACTIVE",
  "is_address_required": 0,
  "is_phone_number_required": 0,
  "step": 2,
  "customer": { "name": "…", "email": "…", "phone": null, "address": null }
}
```

**Key findings (differ from E0.1 assumptions):**
- The reference field is **`link_id`** (not `bill_link_id`) on this sandbox.
- Creation status is **`ACTIVE`** (awaiting payment), not `PENDING`.
- `link_id` is returned as a JSON **number**; it is stored and compared as a
  **STRING** in TU (19-digit IDs must never pass through a JS/float Number).
- The payment page is **`link_url`** (hosted Flip payment page).

---

## 3. Status Inquiry — `GET /v2/pwf/{link_id}/bill`

**Verified:** returns the single bill object (HTTP 200) including `status`,
`amount`, `link_id`.

**Paths that do NOT work (verified 404):**
- `GET /v2/pwf/bill/{link_id}` → 404 (this was E0.1's untested assumption)
- `GET /v2/pwf/bills/{link_id}` → 404
- `GET /v2/pwf/bill/{link_id}/status` → 404

**Also present:** `GET /v2/pwf/bill` → the full bill **list** (200). Not used for
recovery (unbounded); the single-bill path is used instead.

---

## 4. Status Enum

| Provider status | Meaning | TU canonical | Settles bill? |
|---|---|---|---|
| `ACTIVE` | created / awaiting payment | `pending` | no |
| `PENDING` | awaiting (defensive) | `pending` | no |
| `SUCCESSFUL` | settled | `success` | **yes** |
| `FAILED` | failed | `failed` | no |
| `CANCELLED` | cancelled | `failed` | no |
| `EXPIRED` | expired | `failed` | no |

**Only `SUCCESSFUL` may mark a bill PAID.** `ACTIVE` (creation) must never settle.

---

## 5. Callback / Webhook

- **Method:** `POST`, `application/x-www-form-urlencoded`.
- **Fields:** `data` (JSON **string**, same shape as the create response incl.
  `link_id`, `status`, `amount`) and `token` (Flip dashboard validation token).
- **Authenticity:** the `token` is compared **constant-time** against the
  configured callback token (env `FLIP_CALLBACK_TOKEN`). No HMAC is used.
- **Retry contract:** Flip expects HTTP 200 within ~30s and retries otherwise.
  TU therefore always returns **HTTP 200** and does only fast, source-side work
  (bill PAID + attempt + outbox); Finance delivery is asynchronous via outbox.

> Callback payload could not be fired end-to-end in the sandbox (no real payment
> completed). The **field names above are per the verified V2 create shape**;
> the handler accepts both `link_id` and `bill_link_id` defensively.

---

## 6. Idempotency

Flip V2 exposes **no verified provider-level idempotency key**. TU enforces
**exactly one open Flip `PaymentAttempt` per StudentBill** via a DB row lock +
transaction (`FlipPaymentController`), and recovers ambiguous create timeouts via
**status inquiry** (`GET /v2/pwf/{link_id}/bill`) rather than blind re-POST.

---

## 7. Amount semantics

- Amount is **integer IDR** (whole rupiah, no decimals).
- `StudentBill.amount` is authoritative; the callback amount is compared as an
  exact integer (`(int) round(...)`), never with float equality.
- A mismatch rejects the callback (no PAID, no outbox).

---

## 8. Corrections to E0.1

| E0.1 statement | E0.2 verified |
|---|---|
| Status inquiry = `GET /v2/pwf/bill/{link_id}` (probed with a *nonexistent* id) | Correct path is **`GET /v2/pwf/{link_id}/bill`**; the E0.1 path 404s with a *real* id |
| Success/pending strings "likely `SUCCESSFUL`/`PENDING`" | Creation = **`ACTIVE`**; success = `SUCCESSFUL` |
| Reference field assumed `bill_link_id` | Sandbox returns **`link_id`** |
| Create request-body unknown | `title/type/amount/step` (+ `sender_name`+`sender_email` for step 2) verified |

---

## 9. Verified vs unverified

**Verified (live sandbox):** base URL, Basic auth, create endpoint + required
fields, create response shape, `link_id`/`link_url`, creation status `ACTIVE`,
single-bill status path, list path, amount integer type, error shape.

**Unverified:** exact callback payload as fired by Flip; the full error-code
catalogue; fee/min/max rules; channel identifiers post-payment. The handler is
written defensively for the callback payload and never trusts it as sole proof
(the bill amount and local attempt reference are re-checked).

---

**Code changes (E.1):** adapter + callback + creation controller + provider
fields. **Production: NOT TOUCHED. Legacy `~/sistem-tu`: NOT TOUCHED. Secrets:
NOT EXPOSED.**
