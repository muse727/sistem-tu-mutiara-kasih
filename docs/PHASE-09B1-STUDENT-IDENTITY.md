# PHASE 09B.1 — Student Identity & Import Hardening

## Scope
Introduce a stable, immutable external student identity (`student_uuid`) to the
TU system — the authoritative student master — ahead of any Finance
integration. **No** Finance integration, Finance tables, Finance student
records, or data sent to Finance. **No** Flip/payment-gateway work.

The legacy repository `~/sistem-tu` was **not** touched.

---

## 1. Existing identity architecture (audit)

- **Primary key:** `students.id` (bigint auto-increment) — internal.
- **`nis`** varchar(20), **nullable**, `UNIQUE`. Can be `NULL` for
  `calon_siswa`; is assigned/changed during activation; is used as the parent
  portal and siswa-portal login identifier and is exposed in the API.
- **`nisn`** varchar(20), **nullable**, `UNIQUE`.
- **No UUID/external identifier existed.** No `HasUuids` / ramsey-uuid
  dependency; the project uses Laravel's `Str::uuid()` (Laravel 12.44).
- **Soft deletes:** `students.deleted_at` (SoftDeletes).
- **Importer:** `StudentController::importCsv` — the only bulk-create path.
  Matches/rejects by `nis` (and `nisn`), `withTrashed()`, then `Student::create`.
  No `updateOrCreate` / `firstOrCreate` anywhere; **no name-based matching.**
- **API:** `ParentApiController` builds explicit payloads (id, nis, name,
  class_name) — no blind model serialization.

---

## 2. New stable identifier

- **Name:** `student_uuid`
- **Type:** `char(36)` UUID v4, `UNIQUE` (`students_student_uuid_unique`).
- **Generation:** in the `Student` model `creating()` hook via
  `Str::uuid()`, **only when not already set**.

  *Rationale for the model:* every creation path — web `store`, CSV import,
  seeders, factories, and any future API — goes through the model, so the
  identifier is produced consistently everywhere and a re-save/update can never
  regenerate it. A service or controller would risk missing a path; a DB
  default would not be portable. The DB **UNIQUE constraint is the final
  authority** against duplicates under concurrency.

- **Immutable / unique / opaque:** generated once, never regenerated, never
  derived from name or NIS, never reused, never reassigned after creation.

## 3. Why name is not an identifier
Two students can legitimately share a name, similar birth data, and the same
family/parent name. Names change. Matching by name (exact, fuzzy, LIKE,
similarity, or "closest match") would risk merging or overwriting the wrong
student. Name is **never** used for identity resolution.

## 4. Why NIS is not the cross-system identity
NIS is **nullable** (calon_siswa), is **assigned and can change** over the
student lifecycle, and functions as a **login credential** for the portals.
A value that can be null, can change, and is treated as a secret cannot be a
permanent external identity. NIS remains valid for *intra-school* operations
and as an import matching key (it is DB-unique), but it is **not** the
cross-system identifier.

## 5. `students.id` role
Internal auto-increment primary key. Foreign keys (`student_bills`,
`pos_orders`, …) reference it. It is **not** a stable external identity and is
not relied upon cross-system.

## 6. `student_uuid` role
The permanent, opaque, immutable external identity. Survives name changes, NIS
changes, class changes, academic-year changes, import/update, and soft
delete/restore. **This is the identifier future integrations (including
Finance) must use.**

---

## 7. Import matching algorithm (current, proven deterministic)

The CSV importer resolves identity in this order:
1. **`nis` (authoritative, DB-unique).** If a row's `nis` already exists
   (including on a soft-deleted student) the row is **rejected** as a
   duplicate — never merged, never overwriting the existing student.
2. **`nisn`** is likewise checked for duplicates and rejected if present.
3. A row with a **missing `nis`** is rejected as invalid (deterministic).
4. Otherwise the row is treated as a **genuinely new student** and created with
   a fresh `student_uuid`.

`student_uuid` in the import payload is **ignored** (the model generates its
own), so an import cannot hijack an existing student's identity.

## 8. Ambiguous import behavior
There is **no fuzzy/name matching**, so ambiguity-by-name cannot occur.
Identity conflicts (duplicate NIS/NISN, missing NIS, DB unique violations) are
**rejected and surfaced to the operator** as per-row errors — never auto-chosen,
never silently merged.

## 9. Duplicate prevention
- App-level duplicate NIS/NISN rejection (checked `withTrashed()`).
- DB **UNIQUE** constraints on `nis`, `nisn`, and `student_uuid` are the final
  authority under concurrency.
- A row-level DB rejection is recorded and skipped — it does **not** abort the
  whole batch and never merges records.

### Import bug fixed
Empty `nisn` was previously stored as `''`, so a second empty-NISN row violated
`UNIQUE(nisn)` and crashed the import mid-batch. The importer now normalises
empty `nisn` → `NULL` and isolates each row in its own transaction with a
row-level `QueryException` guard, so valid rows still import and bad rows are
reported.

## 10. NIS policy
**Decision: preserve the existing `UNIQUE(nis) NULLABLE` constraint.**
- MariaDB permits multiple `NULL`s under a UNIQUE index, so `calon_siswa`
  (null NIS) rows coexist safely.
- Because the importer rejects duplicate NIS `withTrashed()`, a soft-deleted
  student's NIS cannot be reused — preventing identity confusion without
  needing a partial/conditional unique index.
- No global re-constraint was added (no data proves NIS is reused across
  distinct active students today; the lifecycle already assigns NIS once at
  activation). The smallest safe change was chosen.
- `student_uuid` is the cross-system identity **regardless** of NIS policy.

## 11. API exposure
`ParentApiController` login + dashboard payloads now include `student_uuid`
**in addition to** the existing `id`, `nis`, `name`, `class_name` fields.
Nothing was removed or renamed — **backward compatible**.

## 12. Security considerations
- `Student::$guarded = ['id', 'student_uuid']` — `student_uuid` is **not**
  mass-assignable on create or update.
- The `creating()` hook only fills a **blank** uuid; it never overwrites.
- Import and update payloads cannot inject or change `student_uuid`.
- A client sending another student's UUID cannot reassign identity.

## 13. Future Finance integration guidance
- **TU is the authoritative student master.**
- **`student_uuid` is the stable external identity.**
- **Finance must use `student_uuid` to reference students.**
- **Finance must NEVER identify students by name.**
- **Finance must NOT become the student master** and must not create/edit
  authoritative student identity records.
- NIS may be displayed but must not be the linkage key (it is nullable and can
  change).

---

## Test summary (this phase)
- `tests/Feature/StudentIdentityTest.php` — 11 tests (generation, uniqueness,
  immutability across name/NIS/class changes, re-save, soft delete/restore,
  mass-assignment protection, DB duplicate rejection).
- `tests/Feature/StudentImportProtectionTest.php` — 7 tests (duplicate import,
  no name-based match, duplicate NISN, missing NIS, soft-deleted NIS reuse,
  uuid-injection ignored, distinct uuids).
- `tests/Feature/StudentIdentityApiTest.php` — 1 test (API exposes
  `student_uuid`, backward compatible).

## Full suite result
```
0 failed / 1 skipped / 232 passed (741 assertions)
```
(The 1 skipped test is the pre-existing intentional skip
`ParentApiPaymentTest.php:213`.)
