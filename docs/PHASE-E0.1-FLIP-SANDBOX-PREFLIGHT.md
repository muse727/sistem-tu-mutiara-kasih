# PHASE E.0.1 — FLIP SANDBOX PREFLIGHT & OFFICIAL API VERIFICATION

**Read-only preflight. No application/schema/payment changes. No payment
created. No production Flip call. No secret printed/committed. The sandbox
secret was read from `.env` in-process only and never echoed; all reported
payloads are field names / categorical values, with IDs and amounts redacted.**

## 1. Executive summary
The historical candidate sandbox base URL **`https://bigflip.id/big_sandbox_api`
is VALID and ACTIVE**, and the configured sandbox key **authenticates
successfully**. The configured base URL in `.env` matches it. The **V2 Accept
Payment (Create Bill) flow is live on this sandbox**; **V3 returns 404** on this
host. A read-only preflight (list bills / list payments / balance / status-inquiry
probe) succeeded with **no mutation**. The Create Bill V2 request/response is
now evidenced; the **callback/webhook authentication mechanism remains the one
UNVERIFIED item** and must be confirmed from official docs before E.1.

## 2. Official Flip sources
- Primary: `https://docs.flip.id/docs/api/accept-payment/` — **JS SPA (Docusaurus);
  not machine-readable in this environment** (content rendered client-side; reader
  proxies return empty shells). [OFFICIAL — but unreadable here]
- Official changelog (extracted from the official docs bundle): **Bill `link_id` /
  `bill_link_id` change to exactly 19 digits, effective ~April 10, 2026**, across
  all V2/V3 endpoints and callbacks; store as String/BigInteger, never a JS
  Number. [OFFICIAL]
- Product navigation confirmed in official docs: Accept Payment → **Checkout**
  (popup, redirection) and **Direct API** (CC, e-wallet, QRIS, retail, VA,
  static-VA). [OFFICIAL]
- Official Postman Collection: referenced by the docs but the collection file
  itself could not be fetched (lazy-loaded asset, not directly addressable). [UNVERIFIED]

## 3. Historical project reference
`FLIP_API_URL=https://bigflip.id/big_sandbox_api` (from a prior user project).
[HISTORICAL PROJECT REFERENCE] — used only as a lead; **independently verified
below**, not trusted blindly.

## 4. Current sandbox URL
- **Base URL:** `https://bigflip.id/big_sandbox_api` — matches `.env` `FLIP_API_URL`. [VERIFIED SANDBOX]
- **Reachable:** YES. `GET /big_sandbox_api` → 301 → `https://bigflip.id:8443/big_sandbox_api/` (host serves API on port 8443; curl follows). [VERIFIED SANDBOX]
- **Sandbox confirmed:** YES — authenticated read-only endpoints return live data. [VERIFIED SANDBOX]
- **V3 availability:** `GET /v3/pwf/bill` → **HTTP 404** ("Page not found"). V3 not exposed on this sandbox host. [VERIFIED SANDBOX]
- **V2 availability:** `GET /v2/pwf/bill`, `/v2/pwf/payment`, `/v2/general/balance` → **HTTP 200**. [VERIFIED SANDBOX]

## 5. Authentication
- **Mechanism:** HTTP Basic with the **secret key as the username** (empty password), i.e. `Authorization: Basic base64(secret_key + ':')`. Confirmed: requests without/with wrong shape fail, with correct Basic succeed (200). [VERIFIED SANDBOX]
- **Headers:** `Authorization: Basic <base64(key:)>`; `Accept: application/json`. [VERIFIED SANDBOX]
- **Secret configured:** `.env` → `FLIP_API_URL`, `FLIP_SECRET_KEY` = **PRESENT**. [VERIFIED — presence only]
- **Secret exposed:** **NO.** Value never printed, logged, or committed. `.env` is git-ignored and untracked.

## 6. Create Bill (Accept Payment) — V2 evidenced
- **Product/flow:** Create Bill (Accept Payment) **V2** on this sandbox. [VERIFIED SANDBOX for V2; V3 NOT present]
- **Method/endpoint (create):** `POST /v2/pwf/bill` (list is `GET /v2/pwf/bill`). [VERIFIED SANDBOX — read path confirmed; create not executed by design]
- **Status inquiry:** `GET /v2/pwf/bill/{link_id}` → path exists (probe with a non-existent id returned HTTP 404 "not found", i.e. the route is real). [VERIFIED SANDBOX]
- **Bill response fields (live):** `link_id, link_url, title, type, amount, redirect_url, status, expired_date, created_from, is_address_required, is_phone_number_required, step, customer`. [VERIFIED SANDBOX]
  - `customer` sub-fields: `name, email, phone, address`. [VERIFIED SANDBOX]
  - `type` value seen: `SINGLE`; `step` values seen: `1, 2, 3`. [VERIFIED SANDBOX]
- **Payment URL:** `link_url` / `redirect_url` (hosted payment page / redirect). [VERIFIED SANDBOX]

> Note: exact **request-body** required/optional fields for `POST` were not
> enumerated (no create performed in preflight, and the docs SPA is unreadable).
> [UNVERIFIED — obtain from official docs/Postman in E.1 before coding.]

## 7. Bill ID format
- **Current sandbox:** existing bills show a **short numeric `link_id`** (≤ ~19 digits; observed 6). Stored safely as a **string**. [VERIFIED SANDBOX]
- **Official change:** `link_id` / `bill_link_id` become **exactly 19 digits** (~Apr 10, 2026). [OFFICIAL]
- **Safe storage [DESIGN DECISION]:** store as **VARCHAR/string**, never INT (19 digits > signed BIGINT is borderline and > JS safe integer). **Never pass through a JavaScript `Number`** (precision loss > 2^53). Backend treats it as an opaque string; frontend keeps it a string.

## 8. Payment response
See §6 — bill object carries `link_id`, `link_url`, `redirect_url`, `status`, `amount`, `expired_date`, `step`, `customer`. A created bill yields the hosted payment URL (`link_url`/`redirect_url`) for the portal to open/redirect. [VERIFIED SANDBOX]

## 9. Callback / webhook
- The docs list an Accept Payment **callback/notification** concept, but the
  **exact mechanism (registration, payload, verification) could not be read**
  (docs SPA unreadable) and **no callback fires without a real payment** (none
  created). [UNVERIFIED]
- **E.1 must confirm:** callback HTTP method, registration (dashboard vs per-bill `callback_url`/`redirect_url` param), payload fields (incl. `bill_link_id`), and the **authenticity mechanism** (token/signature/IP). Do NOT guess a signature algorithm.

## 10. Callback authentication
**UNVERIFIED.** No official signature/token mechanism could be extracted. Must be
confirmed from official docs (or Postman) and implemented to be **at least as
strong as the current Midtrans `sha512(order_id.status_code.gross_amount.serverKey)`
check**. If Flip's mechanism is weaker (e.g. static token/IP allowlist only),
compensate with a status-inquiry confirmation (`GET /v2/pwf/bill/{id}`) before
settling — never trust the callback body alone. [DESIGN DECISION]

## 11. Status values
- **Observed on sandbox bills:** `EXPIRED`, `INACTIVE`. [VERIFIED SANDBOX]
- **Success / pending values:** NOT observed (no successful/pending bill existed; none created). Likely `SUCCESSFUL`/`PENDING` per prior knowledge but **[UNVERIFIED]** — confirm exact strings from official docs before mapping.
- **Conceptual mapping [DESIGN DECISION, not implemented]:** provider success → TU `PAID`; pending → TU `UNPAID`/attempt pending; expired/cancelled/failed → attempt cancel/expire. TU canonical `UNPAID`/`PAID` remains source of truth; provider status never becomes business truth.

## 12. Amount semantics
- **Amount JSON type: integer** (whole IDR, no decimals) on the sandbox. [VERIFIED SANDBOX]
- Compare against authoritative `StudentBill.amount` exactly; no floats. Min/max/fee handling [UNVERIFIED — official docs]. No TU schema change this phase.

## 13. Payment channels
- Channels are selected on the **hosted payment page / Direct API**; the bill object itself does not expose the chosen channel in the list response (channel appears after payment, likely via the payment/callback object). [VERIFIED SANDBOX — bill list]
- Exact channel identifiers (QRIS/VA/e-wallet/bank) [UNVERIFIED — confirm from official payment-method docs]. Do not invent values.

## 14. Expiry
- Bill carries `expired_date` (ISO timestamp). [VERIFIED SANDBOX]
- Pending → success/expiry/cancel lifecycle and exact terminal states [UNVERIFIED — official docs]. Do not mark PAID until verified success.

## 15. Idempotency
**UNVERIFIED.** Whether V2 Create Bill supports an explicit idempotency key or a
merchant-controlled unique reference could not be read from the docs. This is
**CRITICAL** for the timeout-after-acceptance case. [E.1 MUST confirm from
official docs/Postman.] If unsupported, TU must enforce **one active PaymentAttempt
per bill** (DB uniqueness + transaction/lock) and use **status inquiry** to
recover from ambiguous create timeouts rather than blind re-POST. [DESIGN DECISION]

## 16. Status inquiry
**YES** — `GET /v2/pwf/bill/{link_id}` (path confirmed; returns bill incl. `status`, `amount`). Also `GET /v2/pwf/payment` for payment records. This is the safe recovery path for ambiguous create/callback situations. [VERIFIED SANDBOX]

## 17. Error contract
- Errors return JSON with fields `name, message, code, status` (observed on the V3 404). [VERIFIED SANDBOX]
- Exact error-code catalogue (validation vs auth vs not-found, retryable vs permanent) [UNVERIFIED — official accept-payment-error-list]. Map conceptually: 4xx → permanent/no-retry; 5xx/timeout → retryable-with-caution. [DESIGN DECISION]

## 18. Sandbox verification (what was actually executed)
- Auth: valid → 200 on read endpoints; V3 → 404. [VERIFIED SANDBOX]
- Read-only endpoints hit: `GET /v2/pwf/bill`, `GET /v2/pwf/payment`, `GET /v2/general/balance`, `GET /v2/pwf/bill/{id}` (probe). **No POST / no payment created.** [VERIFIED SANDBOX]
- Result: **PASS** (read-only preflight succeeded; sandbox + auth + V2 bill contract confirmed).

## 19. TU impact (no changes made)
- `PaymentSiswaController` / `ParentApiController` create Midtrans Snap; `MidtransController`/`PaymentSiswaController::callback` verify `sha512`. Flip will replace Snap creation with Create Bill (`POST /v2/pwf/bill`) and the Snap popup with the hosted `link_url`/`redirect_url`. [DESIGN DECISION]

## 20. Finance impact (no changes made)
- Contract `spp.payment` unchanged. **Provider semantics hard-coded** (`payment_method=MIDTRANS`/`provider=midtrans`) in TU `TuFinanceContractValidator` and Finance `TuFinanceEvent` + `tu_event_coa_mappings`. Accepting `provider=flip` (+ likely `payment_method=ONLINE`/`payment_channel`) requires a **coordinated, versioned contract change (v1.1)** on both sides in E.1. Historical Midtrans rows remain valid.

## 21. Frontend impact (no changes made)
- `resources/views/siswa/*` load Midtrans Snap JS + client key and call `snap.pay`. Replace Snap popup with a **redirect to Flip `link_url`/`redirect_url`** (or hosted page). Keep layout/amount/bill identity/success UX. **Never** pass the 19-digit `link_id` through a JS Number.

## 22. PaymentAttempt impact (no changes made)
- Model is ~80% generic. Generalize: `order_id` → provider reference (store Flip `link_id` as string), replace `snap_token` with `payment_url`/redirect field, add `provider` (`flip`/`midtrans`), keep lifecycle (`status`, `initiated_at`, `settled_at`, `expired_at`, `gross_amount`, `source`). Minimum migration in E.1. Historical Midtrans attempts untouched.

## 23. Historical Midtrans policy
Existing `provider=MIDTRANS` records (StudentBills, PaymentAttempts, webhook refs, Finance transactions) remain as-is for audit/reconciliation. Not rewritten as Flip; not deleted. New payments use `provider=FLIP`.

## 24. E.1 implementation checklist (prerequisites)
1. **Read the official Create Bill V2 request-body spec + callback spec** (docs/Postman) — the one part not yet captured.
2. **Confirm callback authenticity mechanism** (token/signature/IP) and design it ≥ Midtrans strength; add status-inquiry confirmation before settle.
3. **Confirm idempotency support** on Create Bill; otherwise enforce one-active-attempt + status-inquiry recovery.
4. **Confirm exact status strings** (success/pending/terminal) for the mapping.
5. Approve the **coordinated contract change (v1.1)** for `provider=flip` (+ `ONLINE`/`payment_channel`) on TU + Finance.
6. Generalize `PaymentAttempt`/provider fields (string `link_id`).
7. Decide sandbox key handling: the previously-shown key is **potentially compromised — rotate before real create tests**.

## 25. Unverified items
Create Bill **request-body** field list; callback registration/payload/auth; exact
status enum; exact channel identifiers; idempotency support; error-code catalogue;
min/max/fee amounts. (All require official docs/Postman, which were not
machine-readable here; none block the sandbox-URL/auth verdict.)

## 26. Risks
- **Callback auth unknown** → security risk if assumed; must confirm before E.1.
- **Idempotency unknown** → duplicate-payment risk on create-timeout; must confirm.
- **Contract coupling** (provider semantics hard-coded both sides) → coordinated v1.1 required.
- **19-digit ID** → JS precision / DB INT overflow if mishandled → store/pass as string.
- **Potentially compromised sandbox key** (shown in an image) → rotate before create tests.
- **Docs SPA unreadable** → need official Postman collection or a readable export.

---

**Code changes: NONE · Database changes: NONE · Production: NOT TOUCHED · Flip
production: NOT TOUCHED · Legacy `~/sistem-tu`: NOT TOUCHED · Finance: NOT
MODIFIED · Secrets: NOT EXPOSED (`.env` git-ignored/untracked; value never
printed) · Sandbox payment created: NONE (read-only preflight only).**
