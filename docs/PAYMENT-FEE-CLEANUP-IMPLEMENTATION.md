# PAYMENT-FEE-CLEANUP — Implementation Record

Reverts the premature/abandoned **PAYMENT-FEE-SETTLEMENT** fee work in the TU
system. This is a **controlled removal** (not a revert of working behavior):
the pre-fee baseline is exactly the current live zero-fee behavior, so removing
the fee machinery restores the true baseline with **no change to any payment,
settlement, amount, callback, or existing integration behavior**.

Source decision: `~/keuangan-mutiara-kasih/docs/PAYMENT-FEE-CLEANUP-AUDIT.md`
(§16 Next Task). Rationale for the cleanup itself (premature, config-driven,
unused, and risks reading a *derived* net as an actual provider receipt) lives
in that audit and is **not** repeated here.

## 1. Scope confirmed against the audit

Every audit-listed surface was located and removed. No other code depended on
the fee machinery.

| Audit item | Status |
| :-- | :-- |
| Fee subledger migrations (03 / 08D) | **removed** |
| `FeePayerResolver` service | **removed** |
| `fee_payer` config in `config/services.php` | **removed** |
| `spp_batch_v2_enabled` config in `config/integration.php` | **removed** |
| `AppServiceProvider::validateFeePayerConfig()` + `boot()` call | **removed** |
| `PaymentAttempt` fee attributes/casts/constants/`feeAttributesForCreation` | **removed** |
| `feeAttributesForCreation` merges in creation paths (Midtrans single, Midtrans MULTI, Flip, Parent API) | **removed** |
| `SppSettlementBatchService` v2 path (`buildV2Payload`, `bucketPayerProvenance`, `FeePayerResolver` import) | **removed** |
| `PaymentAttemptFactory` fee fields + `schoolFee`/`customerSurcharge` states | **removed** |
| Fee-only tests (4 files) | **removed** |

## 2. Batch contract: v2 removed, v1 kept

`SppSettlementBatchService::buildOutboxEvent()` now **always** emits the v1.0
batch contract. The v2.0 fee-aware branch is deleted entirely (per the audit's
simplification directive):

- **Removed:** `buildV2Payload()`, `bucketPayerProvenance()`, the
  `spp_batch_v2_enabled` flag and its `buildOutboxEvent` branch, and the
  `FeePayerResolver` import.
- **Kept (v1):** `total_amount` (= gross SPP), `payment_count`, `bill_count`,
  and `breakdown[]` buckets with `payment_method`, `provider`, `amount`,
  `payment_count`, `bill_count`. `event_version`/`contract_version` are now
  unconditionally `'1.0'`.
- The batch **lifecycle** (DRAFT → CLOSED → outbox → dispatch) is unchanged.

`SppSettlementBatchReconciliationSnapshot` /
`SppSettlementBatchReconciliationTransportService` already operate on v1
(`total_amount`) and required **no change** — verified, they have no fee-field
dependency.

### Finance-side compatibility (read-only verification)

`~/keuangan-mutiara-kasih/backend` consumes the TU batch with
`TuFinanceEvent::CONTRACT_VERSION = '1.0'`, reads only `total_amount` +
`breakdown[].amount`, and has **zero** references to `fee_amount`,
`gateway_fee_amount`, `net_settlement_amount`, `spp_gross_amount`, `fee_payer`,
or any v2 handling. Restoring the v1.0 contract is therefore **fully
compatible**; no Finance changes were made.

## 3. Files removed

Migrations:
- `database/migrations/2026_11_03_000001_add_fee_fields_to_payment_attempts_table.php`
- `database/migrations/2026_11_04_000001_add_fee_payer_state_to_payment_attempts_table.php`

Service:
- `app/Services/Payments/FeePayerResolver.php`

Fee-only tests (validated as strictly fee-scoped — no shared/core coverage):
- `tests/Feature/FeePayerProvenanceTest.php`
- `tests/Feature/PaymentFeeSubledgerTest.php`
- `tests/Feature/PaymentFeeWiringTest.php`
- `tests/Feature/SppSettlementBatchV2Test.php`

## 4. Database / migration handling — orphan columns dropped (DB-01)

The two fee migrations **were already applied** to the local dev database
(`127.0.0.1`, `APP_ENV=local`, batches 4–5), leaving the six fee columns
physically present but orphaned (nothing read/wrote them).

**PAYMENT-FEE-CLEANUP-DB-01 has since removed those columns** from the local
dev database via an explicit reviewed migration:

- **Migration filename:**
  `database/migrations/2026_11_05_000001_drop_fee_fields_from_payment_attempts_table.php`
- **Columns removed:** `fee_payer`, `fee_payer_state`, `fee_amount`,
  `customer_surcharge_amount`, `net_amount`, `fee_amount_state`
- **Local DB verification:** connection confirmed `mariadb` @ `127.0.0.1` /
  database `sistem_tu` / `APP_ENV=local` (local-only, not shared/production).
  Post-drop `SHOW COLUMNS FROM payment_attempts` returns **21 columns with none
  of the six fee fields**; all 9 pre-existing rows preserved.
- **Rollback definition:** `down()` restores ONLY the six columns with the exact
  definitions from the original fee migrations (see §below / DB-01 doc). No
  definitions invented. Rollback was exercised (`migrate:rollback` then
  re-`migrate`) and confirmed reversible.
- **Idempotent:** drops are existence-checked (`Schema::hasColumn`), so the
  migration is a no-op on databases that never received the original fee
  migrations (e.g. a fresh test database). This is required because the
  original fee migrations were removed, so `migrate:fresh` on the test DB would
  otherwise attempt to drop non-existent columns.
- **Test results:** full TU suite **434 passed, 1 skipped** (1,359 assertions);
  payment / Midtrans / Flip / MULTI / batch v1 / reconciliation / HMAC
  integration suites all green. No application behavior changed.
- **No other schema objects changed:** verified no indexes, triggers, foreign
  keys, or views depended on the dropped columns; no other column or table was
  touched.

Full details (safety checks, rollback behavior, regression runs) are in
`docs/PAYMENT-FEE-CLEANUP-DB-01.md`.

> Note: dropping the columns does **not** rewrite the historical `migrations`
> table rows (batches 4–5), which is correct — those record that the fee
> migrations once ran. No `migrations`-table surgery was performed.

## 5. Verification

- `php -l` on every edited file: **pass**.
- Full suite: **434 passed, 1 skipped** (1,359 assertions). The single skip is
  pre-existing and unrelated (`ParentApiPaymentTest` requires a Midtrans facade
  mock — integration-only).
- Payment-attempt creation smoke test (dev DB): row created, `gross_amount`
  persisted, fee fields `NULL`/inert.
- Route registration resolves cleanly; caches cleared.
- Grep confirms **zero** remaining references in `app/`, `config/`,
  `routes/`, `database/factories/` to: `fee_payer`, `fee_amount`,
  `net_amount`, `customer_surcharge_amount`, `FeePayerResolver`,
  `feeAttributesForCreation`, `buildV2Payload`, `bucketPayerProvenance`,
  `spp_batch_v2_enabled`, `validateFeePayerConfig`.

## 6. Out of scope / not touched

- Payment-attempt callback paths (`MidtransWebhook`, `FlipCallback`,
  `SppMultiCallback`), idempotency, signature handling, amount guards —
  untouched; their tests still pass unmodified.
- Student-facing payment UX, CASH settlement, existing spp.payment (per-bill)
  integration, batch lifecycle, reconciliation — unchanged.
- Finance repo — read-only inspection only; no edits.
