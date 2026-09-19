# PAYMENT-FEE-CLEANUP-DB-01 — Orphan Fee Column Removal

Database-only cleanup that drops the six orphan fee columns left physically in
the LOCAL development database after the PAYMENT-FEE-SETTLEMENT revert (see
`docs/PAYMENT-FEE-CLEANUP-IMPLEMENTATION.md`). No fee functionality was
restored; no application behavior changed.

## 1. Objective

Remove the six orphan fee columns from `payment_attempts`:

- `fee_amount`
- `fee_amount_state`
- `fee_payer`
- `fee_payer_state`
- `customer_surcharge_amount`
- `net_amount`

The application code has **zero** active references to these fields (verified in
the prior cleanup task and re-verified below). This migration only aligns the
physical schema with the application.

## 2. Local DB Verification

Confirmed before running anything that the target is **only** the local
development database — not shared, not production:

| Check | Value |
| :-- | :-- |
| `APP_ENV` | `local` |
| `DB_CONNECTION` | `mariadb` |
| `DB_HOST` | `127.0.0.1` |
| `DB_PORT` | `3306` |
| `DB_DATABASE` | `sistem_tu` |
| `DB_USERNAME` | `sistem_tu` |
| Runtime `database.default` | `mariadb` @ `127.0.0.1` / `sistem_tu` |

Pre-drop schema inspection (`SHOW COLUMNS FROM payment_attempts`) confirmed all
six columns existed with the exact original definitions:

| Column | Type | Null | Default |
| :-- | :-- | :-- | :-- |
| `fee_payer` | `varchar(20)` | NO | `'NONE'` |
| `fee_payer_state` | `varchar(20)` | NO | `'UNKNOWN'` |
| `fee_amount` | `decimal(12,2)` | NO | `'0.00'` |
| `customer_surcharge_amount` | `decimal(12,2)` | NO | `'0.00'` |
| `net_amount` | `decimal(12,2)` | YES | `NULL` |
| `fee_amount_state` | `varchar(20)` | NO | `'UNKNOWN'` |

## 3. Columns Removed

All six listed in §1. No other column or table was touched.

## 4. Migration

**Filename:**
`database/migrations/2026_11_05_000001_drop_fee_fields_from_payment_attempts_table.php`

Behavior:
- Drops **only** the six orphan columns from `payment_attempts`.
- Idempotent: each column drop is guarded by `Schema::hasColumn('payment_attempts', …)`.
  On a database that never received the original fee migrations (e.g. a fresh
  test DB), the migration is a **no-op**. This is required because the original
  fee migrations were removed — without the guard, `migrate:fresh` would attempt
  to drop non-existent columns and fail (observed before the guard was added).
- Touches no other column/table; adds nothing back; creates no aliases.

## 5. Rollback

`down()` restores **only** the six columns using the **exact** definitions taken
from the original (now-removed) fee migrations — nothing invented:

```php
$table->string('fee_payer', 20)->default('NONE')->after('gross_amount');
$table->string('fee_payer_state', 20)->default('UNKNOWN')->after('fee_payer');
$table->decimal('fee_amount', 12, 2)->default('0.00')->after('fee_payer_state');
$table->decimal('customer_surcharge_amount', 12, 2)->default('0.00')->after('fee_amount');
$table->decimal('net_amount', 12, 2)->nullable()->after('customer_surcharge_amount');
$table->string('fee_amount_state', 20)->default('UNKNOWN')->after('net_amount');
```

`down()` is also guarded (`hasColumn('fee_payer')`) so it will not duplicate
columns if already present.

**Rollback was exercised and confirmed reversible:**
`php artisan migrate:rollback --step=1` restored the six columns, then
`php artisan migrate` re-dropped them. Final state = clean (21 columns, no fee
fields).

> Restoring the columns does **not** restore any application fee behavior —
> the code has no references to them. `down()` is a pure schema-undo escape
> hatch only.

## 6. Safety

- Target verified local-only (see §2) before any schema change.
- Dependency inspection found **no** objects depending on the columns:
  - fee-related indexes: `0`
  - triggers on `payment_attempts`: `0`
  - views referencing `fee`: `0`
  - foreign keys: none on these columns
- Data inspection (all 9 existing rows) showed only default zero-fee backfill:
  `fee_payer='NONE'`, `fee_amount=0`, `customer_surcharge_amount=0`,
  `net_amount=gross_amount`, both states `UNKNOWN` → **no substantive data loss**.
- All 9 existing `payment_attempts` rows preserved; unrelated column values
  untouched. Row count unchanged after the drop.
- Historical `migrations`-table rows (batches 4–5, the original fee migrations)
  were **not** altered — they correctly record that those migrations once ran.

## 7. Regression Tests

All run after the migration against the cleaned schema:

| Suite | Result |
| :-- | :-- |
| Payment/webhook (MidtransWebhook, FlipCallback, SppMultiCallback, PaymentAttemptCallback) | **29 passed** (127 assertions) |
| Batch v1 + reconciliation + outbox (SppSettlementBatch, BatchCommand, Reconciliation, ReconciliationTransport, MidtransSingleBillOutbox, SppMultiOutbox, DashboardPaymentDispatch) | **58 passed** (203 assertions) |
| Integration/HMAC + token/idempotency (OutboxHmac, TuFinanceHmac, OutboxRetryRecovery, FlipPaymentCreation, FlipPaymentProvider, MidtransToken, SppMultiToken, ParentApiPayment, Idempotency) | **34 passed, 1 skipped** (115 assertions) |
| **Full TU suite** | **434 passed, 1 skipped** (1,359 assertions) |

The single skip is pre-existing and unrelated (`ParentApiPaymentTest` requires a
Midtrans facade mock — integration-only).

Behavior confirmations:
- `PaymentAttempt` creation works (smoke test: row created, `gross_amount`
  persisted).
- Midtrans flow unchanged; Flip flow unchanged; MULTI unchanged.
- Batch v1 unchanged; Finance integration unchanged (Finance not modified).

## 8. Final Schema Check

`SHOW COLUMNS FROM payment_attempts` (post-drop, dev DB) — **21 columns, none of
the six fee fields remain:**

```
id, student_bill_id, provider, provider_reference, order_id, snap_token,
payment_url, transaction_id, status, provider_status, payment_method,
payment_channel, bank, va_number, gross_amount, initiated_at, settled_at,
expired_at, source, created_at, updated_at
```

`migrate:status` shows
`2026_11_05_000001_drop_fee_fields_from_payment_attempts_table` **Ran** (batch 6);
no pending migrations.

Repository-wide search (active code/config/route/factory/test) for
`fee_amount`, `fee_amount_state`, `fee_payer`, `fee_payer_state`,
`customer_surcharge_amount`, `net_amount`, `FeePayerResolver`,
`feeAttributesForCreation`, `gateway_fee_state`, `spp_batch_v2_enabled`,
`buildV2Payload`: **no active references.** (Only this new migration and the
cleanup docs mention the names, as required for the drop/rollback and records.)

## 9. Remaining Limitations

- The drop was applied to the **local development database only**. Any other
  environment that previously ran the original fee migrations must run this same
  migration there (it is idempotent and existence-checked, so it is safe to run
  everywhere; it no-ops where the columns are absent).
- The historical `migrations`-table rows for the removed fee migrations remain
  (by design). They are inert and do not affect `migrate` / `migrate:fresh`.
- `down()` restores column definitions but **not** any former data (all values
  were defaults/backfill, so nothing of substance is lost) and does **not**
  restore application fee behavior (the code references were removed in the
  prior cleanup task).
- Finance was not modified; no frontend, payment logic, batch behavior,
  reconciliation, or KANTIN changes were made.
