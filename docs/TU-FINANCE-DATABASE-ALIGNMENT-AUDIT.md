# TU ↔ FINANCE — DATABASE & ENVIRONMENT ALIGNMENT AUDIT

**Phase 09C.1 — READ-ONLY audit.** No code, schema, or data was modified in any
repository. No migrations run against any non-test database. No integration,
sync, or Flip implemented.

---

## 1. Executive summary
TU and Finance are **separate Laravel applications with separate databases on
different engines** (TU: MariaDB-driver/MySQL; Finance: PostgreSQL). They must
**stay separate** — integration is event/contract-based (Phase 09C), not
database merging. Overall the two are **compatible enough to proceed to the
integration phase**, with three concrete gaps to close **in Finance** (not TU):

1. **Finance `transactions`/`journals` have no source/event/idempotency/provider
   columns** — needed for traceability + idempotent consumption of TU events.
2. **Engine & ORM differences** (Postgres vs MySQL/MariaDB) mean the integration
   must exchange **values, not SQL/schema** — confirm the contract's portable
   representations (decimal strings, ISO-8601, UUID strings).
3. **Finance dev (Postgres) vs test (SQLite) engine mismatch** — idempotency/
   uniqueness semantics (e.g. partial/conditional uniqueness, case-sensitivity,
   transaction isolation) must be validated on the **real** engine before
   relying on them in production.

Nothing in TU needs to change for alignment.

---

## 2. TU environment
- **Laravel:** `^12.0` (framework)
- **PHP (require):** `^8.2`; **runtime CLI:** `8.3.6`
- **DB driver (config):** `mariadb`
- **DB server:** MySQL **8.0.46** (via MariaDB driver); charset `utf8mb4`,
  collation `utf8mb4_unicode_ci`
- **Timezone:** `Asia/Jakarta`
- **Dev DB:** `sistem_tu`; **Test DB:** `sistem_tu_test` (both MariaDB/MySQL)
- **Migrations:** 53; latest `2026_11_01_000001_add_staf_role_to_users`
- **UUID:** `students.student_uuid` = `char(36)` UUID v4 (string), UNIQUE
- **Money:** SPP `decimal(12,2)`; POS integer IDR (`total_amount` int,
  `payment_amount`/`change_amount` decimal holding whole rupiah)
- **Timestamps:** `created_at`/`updated_at` timestamps; `paid_at` (app clock);
  `PaymentAttempt.settled_at` (provider clock)

## 3. Finance environment
- **Repo layout:** `backend/` (Laravel) + `frontend/`; audit covers `backend/`
- **Laravel:** `^13.17`; **PHP (require):** `^8.3`
- **DB driver (dev `.env`):** `pgsql`, host `127.0.0.1`, port `5433`,
  database `db_keuangan_mutiara` (PostgreSQL; exact server version not safely
  reachable during audit — engine confirmed from config only)
- **DB driver (test `phpunit.xml`):** `sqlite` `:memory:`
  (`.env.example` also `sqlite`) — **dev/test engine mismatch**
- **Timezone:** Laravel default (no explicit `APP_TIMEZONE` override found in
  `.env`; `config/app.php` default applies) — **needs decision vs Asia/Jakarta**
- **Migrations:** 24; latest `2026_10_06_090000_add_account_lifecycle_to_users`
- **UUID:** native UUID PKs throughout (`uuid('id')->primary()`)
- **Money:** `decimal(15,2)` on `transactions.amount`, `journal_details.debit/credit`
- **Seeders:** CoaSeeder (semantic codes `1-101`, `4-101` — **not** fixed
  numeric IDs), AccountingPeriodSeeder (2026, 12 periods), BankAccount, Role/User,
  RolePermission. No student seeder.

## 4. Database engine comparison
| Aspect | TU | Finance | Compatible? |
|---|---|---|---|
| Engine (dev) | MySQL 8.0.46 (MariaDB driver) | PostgreSQL (pgsql) | N/A — separate DBs |
| Engine (test) | MySQL 8.0.46 | SQLite `:memory:` | **Concern** (Finance dev≠test) |
| UUID | `char(36)` string, app-generated | native `uuid` PK | **Compatible** (string UUID maps to pg uuid) |
| Money | decimal(12,2) / int IDR | decimal(15,2) | **Compatible** (Finance wider) |
| Nullable UNIQUE | multiple NULLs allowed | multiple NULLs allowed | **Compatible** |
| JSON | json | jsonb | N/A |
| Charset | utf8mb4 | UTF-8 (pg) | **Compatible** |

Databases are **not** and must **not** be identical — they exchange values via
the integration contract, not SQL.

## 5. Schema ownership map
**A. TU-owned:** `students`, `student_bills`, `payment_attempts`, `pos_orders`,
`pos_order_items`, `bill_items`, TU `audit_logs`, `kelas`, etc.
**B. Finance-owned:** `transactions`, `journals`, `journal_details`, `coas`,
`bank_accounts`, `accounting_periods`, `idempotency_keys`, `number_sequences`,
Finance `students` (savings scope), `student_savings_*`, Finance `audit_logs`.
**C. Integration reference only:** `students.student_uuid` (TU) ↔ Finance
`students.external_student_id` + `source_system`; TU event idempotency_key ↔
Finance `idempotency_keys`.

## 6. Student identity compatibility
- TU provides `student_uuid` (stable external identity). **Confirmed.**
- Finance has a `students` table (UUID PK, `nis`, `name`) **scoped to savings**,
  plus `source_system` + `external_student_id` (UNIQUE pair) — a ready-made
  place to reference an external student.
- **Finance must NOT identify students by name/NIS/NISN.** Cross-system identity
  = `student_uuid` only.
- Design options (NOT chosen — needs decision):
  - **A.** store `student_uuid` as reference on the Finance transaction/event
    (no Finance student row) — minimal, no duplicate master.
  - **B.** Finance student reference row keyed by
    `(source_system='TU', external_student_id=student_uuid)` — reuses existing
    unique pair; risks Finance becoming a partial student master.
  - **C.** no Finance student table; retain source reference only.
  - **Pros/cons:** A is lightest and avoids duplicating the master; B already
    has schema support but must be kept as a *reference*, not a master; C is
    equivalent to A. **Open decision (§19).**

## 7. Transaction traceability (Finance side)
- `transactions` columns: `id` (uuid), `transaction_no` (unique), `transaction_date`,
  `type`, `amount`, `description`, `created_by`, `accounting_period_id`,
  soft-deletes. **No** `source_system`, `source_record_id`, `event_id`,
  `idempotency_key`, or `provider_reference`.
- `journals`/`journal_details`: no source/event columns.
- **GAP:** Finance has **no dedicated place** on `transactions`/`journals` to
  store `source_system`, `source_record_id`, `event_id`, `idempotency_key`, or
  `provider_reference`. (Could be carried temporarily via `description`/
  `audit_logs.metadata`, but that is not a reliable unique/traceable mechanism.)
- Finance **does** already have `idempotency_keys(scope,key)` and
  `audit_logs(subject_type, subject_id, jsonb metadata)` — usable infrastructure,
  but the **transaction-level external reference is missing**.

## 8. Money compatibility
| Domain | TU type | Finance type | Compatible? | Concern |
|---|---|---|---|---|
| SPP | decimal(12,2) | decimal(15,2) | **YES** | Finance wider — no precision loss |
| POS | integer IDR | decimal(15,2) | **YES** | emit int → stored as `NNN.00` |
| Currency | IDR (implicit) | IDR (implicit, no currency col) | **YES** | both IDR-only |
| Rounding | none (exact) | decimal(15,2) | **YES** | no silent rounding |

## 9. Date/time compatibility
- TU `Asia/Jakarta`, ISO-8601 with `+07:00` per the contract.
- Finance `transaction_date`/`journal_date` are **DATE** columns; period
  resolution uses `start_date`/`end_date`. Timestamps elsewhere are `timestamp`.
- **Compatible** if TU sends a date (or timestamp Finance can cast) in a clear
  offset. **Decision needed:** confirm Finance app timezone handling so the
  accounting **date** derived from a `+07:00` timestamp is unambiguous. TU does
  not assign periods.

## 10. Accounting period compatibility
- Finance `accounting_periods`: `(year, month)` UNIQUE, `start_date`, `end_date`,
  `status` open/closed, `closed_at`/`closed_by`. Periods seeded for 2026.
- **Finance is the sole authority** for period assignment. TU must supply only a
  **transaction date** (from `paid_at`/`occurred_at`); Finance resolves the
  period and enforces open/closed + posting rules.
- **GAP (informational):** TU must know to send a `transaction_date`-grade value;
  rejection behavior when the target period is **closed** must be defined in the
  integration phase (`STATE_NOT_ELIGIBLE`).

## 11. Idempotency compatibility
- TU contract: `idempotency_key = {source_system}:{event_type}:{source_record_id}`.
- Finance `idempotency_keys(scope, key, request_hash, resource_type, resource_id,
  response_status, response_body jsonb, expires_at)`, UNIQUE `(scope, key)`.
- **Compatible:** the TU key can map to Finance `(scope='tu-integration', key=<idempotency_key>)`.
- **Caveat:** validate UNIQUE/NULL/case behavior on **PostgreSQL** (Finance test
  runs SQLite — semantics can differ). Not yet wired to transactions (see §7 gap).

## 12. Audit compatibility
- TU `audit_logs` (financial/operational events) exist.
- Finance `audit_logs(action, subject_type, subject_id, jsonb metadata, actor_id,
  created_at)` exist.
- **Traceability chain** TU → event_id → Finance transaction → journal →
  journal_details **can be represented** via `audit_logs.subject_*`/`metadata`,
  **but** the durable link from `transactions` back to `event_id` is the §7 gap.

## 13. Test database isolation
- TU tests → `sistem_tu_test` (MariaDB/MySQL) only.
- Finance tests → SQLite `:memory:` only.
- **No shared database. No cross-contamination risk.** Each project's suite is
  isolated. (Finance dev vs test engine mismatch noted — §4.)

## 14. Migration baseline
- **TU:** 53 migrations, latest 2026_11_01. No pending-run performed.
- **Finance:** 24 migrations, latest 2026_10_06. No pending-run performed.
- Neither baseline was altered.

## 15. Seed/reference data concerns
- Finance COAs use **semantic codes** (`1-101`, `4-101`) — not fixed numeric
  IDs; integration must reference COA **by code or Finance-side mapping**, never
  hard-coded IDs (matches Phase 09C boundary).
- Accounting periods seeded for **2026 only** — future dates need period seeding.
- No Finance student seeder (no fixed student IDs to collide with TU).

## 16. Gaps
1. **Finance `transactions`/`journals` lack** `source_system`, `source_record_id`,
   `event_id`, `idempotency_key`, `provider_reference` columns. (Finance-side
   change — a later integration phase.)
2. **Student reference design undecided** (store `student_uuid` on transaction
   vs Finance student reference row via existing `(source_system, external_student_id)`).
3. **Finance dev (Postgres) vs test (SQLite) engine mismatch** — idempotency/
   uniqueness semantics must be verified on Postgres.
4. **Finance app timezone not explicitly pinned** — confirm accounting-date
   derivation from `+07:00` timestamps.
5. **Closed-period rejection behavior** (`STATE_NOT_ELIGIBLE`) must be defined
   for the integration.

## 17. Recommended remediation order (all Finance-side; none in TU)
1. Decide the **student reference model** (§6) and the **transaction external
   reference columns** (§7).
2. Add (in a Finance integration phase) `source_system`, `source_record_id`,
   `event_id`, `idempotency_key`, `provider_reference` to `transactions` with
   appropriate UNIQUE on `(source_system, idempotency_key)`.
3. Wire `idempotency_keys` consumption to the TU `idempotency_key` semantic and
   **verify on PostgreSQL**; pin/confirm Finance timezone for date derivation;
   define closed-period rejection.

## 18. What MUST NOT be merged between databases
- The two databases themselves (keep separate engines/instances).
- Student masters (TU stays the student master; Finance keeps a *reference* only).
- Migrations/schema (no cross-project schema sync).
- COA/journal/GL (Finance-owned, never pushed from TU).
- Test databases (already isolated — keep that way).

## 19. Open decisions
1. Student reference model A / B / C (§6).
2. Exact Finance transaction columns + uniqueness for TU traceability (§7).
3. Finance accounting-date derivation from `+07:00` timestamps (§9) and Finance
   app timezone pinning.
4. Whether Finance integration tests should run against Postgres (not only
   SQLite) to validate idempotency/uniqueness semantics.
5. Closed-period handling contract (`STATE_NOT_ELIGIBLE`).

---

**Code changes:** NONE. **Database changes:** NONE. **Finance code modified:** NO.
**Legacy `~/sistem-tu` touched:** NO.
