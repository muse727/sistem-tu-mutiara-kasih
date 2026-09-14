# PHASE 09B — Baseline Cleanup / TU System

## Scope
Baseline cleanup of `~/sistem-tu-latest` so the automated test suite reflects
the **current** TU architecture. No Finance integration, no `student_uuid`,
no accounting/journal work — those are later phases and were **not** started.

The legacy repository `~/sistem-tu` was **not** touched.

---

## 1. Initial baseline

```
36 failed / 1 skipped / 193 passed
```

Failure categories identified at baseline:
1. Stale PPDB module tests — 22
2. `BillController::destroy` missing `AuditLog` import — 1
3. Midtrans `payment_method` casing mismatch — 6
4. Login tests blocked by Turnstile — 6
5. `staf` receives 403 on `/students/create` — 1

---

## 2. PPDB decision

**PPDB is permanently out of scope for the current TU architecture.**

The PPDB module was intentionally removed earlier (PPDBController, `/ppdb/*`
routes, PPDB views/services). It was **NOT** restored. Tests that depend
exclusively on the removed module are obsolete and were cleaned up.

---

## 3. PPDB tests — removed / rewritten / preserved

### Removed (obsolete — depend only on the removed PPDB module)

`tests/Feature/Phase12RegistrasiSiswaTest.php` — **deleted**. All 21 tests in
it hit the removed `/ppdb/*` routes (`/ppdb/daftar`, `/ppdb/{id}/seleksi`,
`/ppdb/konversi`) which return 404 today:

- A1 store creates calon siswa with correct status
- A2 store creates status log
- A3 store requires name / gender / birth date
- A4 store nullable catatan does not crash
- B1 activation fails without nis
- C1 activation fails without kelas
- C2 activation fails with inactive kelas
- D1 activation sets status active and assigns nis kelas
- D2 activation is atomic via transaction
- E1 activation blocked if nis already exists
- F1 status log created on activation
- G1 bundle prompt shown after activation
- J1 activation does not partially update if kelas invalid
- K1–K5 bulk activation scenarios

`tests/Feature/StudentDeletionProtectionTest.php` — **2 tests removed**:
- "PPDB destroy rejects calon_siswa that already has a bill"
- "PPDB destroy allows calon_siswa without bills"

### Rewritten / moved (behavior still belongs to current architecture)

- **Bundle bill generation** (`pos.bundles.generateBills`) — test `L1` was
  preserved against the current route and moved to the new file
  `tests/Feature/PosBundleGenerateBillsTest.php`.
- **Student deletion protection** — already covered against the CURRENT
  `StudentController::destroy()` route (`/students/{id}`) by the remaining
  tests in `StudentDeletionProtectionTest.php` (calon/active/keluar/graduated/
  alumni deletion rejection, FK RESTRICT, soft delete, financial history
  preservation). These were **kept** unchanged.
- **Portal login gating by status** (former `H1`/`I1`) — covered by the new
  `tests/Feature/Auth/TurnstileTest.php` siswa-login tests.

### Useful student-security coverage preserved (not deleted)

All of `StudentDeletionProtectionTest.php` (13 tests retained), siswa portal
login security, receipt IDOR tests, and the full role-authorization matrix were
**not** removed — they test current, valid behavior.

---

## 4. BillController::destroy fix

`app/Http/Controllers/BillController.php` referenced `AuditLog::SOURCE_WEB`
inside `destroy()` without importing the model, producing
`Class "App\Http\Controllers\AuditLog" not found` (HTTP 500).

**Fix:** added `use App\Models\AuditLog;`. Audit logging, authorization, and
deletion protections were left intact.

**Regression coverage:** `FinancialAuditTrailTest` →
"bill deletion writes a BILL_DELETED audit log with old_values snapshot" now
passes (was failing with HTTP 500).

---

## 5. MIDTRANS canonicalization

`PaymentSiswaController` (siswa portal webhook) stored a human-readable label
(e.g. `"Midtrans"`, `"VA BCA"`) into `student_bills.payment_method`, while
`BillingPaymentService` and `ParentApiController` store the canonical gateway
value `'MIDTRANS'`.

**Decision:** canonical value = **`MIDTRANS`**. No new alias introduced.

**Fix (behavior going forward):** both the single-bill and multi-bill callback
paths in `PaymentSiswaController` now write `'MIDTRANS'` to
`student_bills.payment_method`. The specific payment channel (VA BCA, GoPay,
QRIS, …) continues to be preserved on `payment_attempts.payment_method` /
`payment_attempts.bank`, and `resolvePaymentMethodLabel()` is retained for
those attempt-level records.

**Historical data:** no bulk rewrite performed. Existing rows with legacy
descriptive values are preserved (read sites special-case `'CASH'` and display
the raw value otherwise, so they remain readable). This was a code-path fix,
not a destructive migration.

**Regression coverage:** the previously failing assertions now pass:
`MidtransWebhookTest`, `PaymentAttemptLifecycleTest`, `PaymentTortureTest`,
`PaymentWritePathTest`, `FinancialTimestampTest`, `FinancialAuditTrailTest`
(76 payment tests total, all green).

---

## 6. Turnstile testability solution

`LoginRequest` (admin) and `AuthSiswaController` (siswa) both performed the
Cloudflare Turnstile siteverify HTTP call inline, so any login test attempted a
**real** network call to Cloudflare and was rejected.

**Solution:** introduced a single injectable boundary
`app/Services/Security/TurnstileVerifier.php`. Both login paths now resolve it
via the container and call `verify($token, $ip)`.

- **Production verification remains enabled** — the verifier always performs
  the real HTTP call; there is **no** environment flag or hidden bypass.
- **Tests never call Cloudflare** — they fake the `Http` facade for the
  `challenges.cloudflare.com/turnstile/*` endpoint only, via the helpers
  `fakeTurnstileSuccess()` / `fakeTurnstileFailure()` added to `tests/Pest.php`.
- **Invalid / missing verification still rejects**, valid verification still
  permits.

**Regression coverage** — `tests/Feature/Auth/TurnstileTest.php`:
1. valid turnstile verification permits admin login
2. invalid turnstile verification rejects admin login
3. missing turnstile token rejects admin login
4. invalid turnstile verification rejects siswa login
5. valid turnstile verification permits siswa login

Existing login tests (`AuthenticationTest`, the four seeder-login tests in
`RoleAuthorizationCleanupTest`) were updated to fake Turnstile and submit the
`cf-turnstile-response` token. `Auth` suite: 23/23 green.

---

## 7. staf `/students/create` authorization — decision

**Decision: the 403 is intentional and correct (Option A).**

`routes/web.php` explicitly scopes student write operations to `admin,tu`:

```
// Write operations — admin, tu only (Staf POS should not edit students)
Route::middleware('role:admin,tu')->group(function () {
    Route::get('/create', ...)->name('create');
    Route::post('/', ...)->name('store');
    ...
});
```

This was a deliberate change in commit `21358dc` ("Refactor(Auth): Strict role
segregation for staf/cashier"). `staf` is the POS/cashier role and must not
create or edit students. No production change was made; access was **not**
broadened.

**Test update:** the stale test "staf can create a student" was replaced with
two correct-policy tests in `RoleAuthorizationCleanupTest.php`:
- "staf cannot access student create form and gets 403"
- "staf cannot store a new student and gets 403"

---

## 8. Final test result

```
0 failed / 1 skipped / 213 passed (697 assertions)
```

The 1 skipped test is a **pre-existing intentional skip**:
`ParentApiPaymentTest.php:213` → `->skip('Requires Midtrans facade mock —
integration test only')`. It was skipped at baseline too and is unrelated to
this cleanup.

---

## 9. Remaining failures

**None.** All 36 baseline failures are resolved (22 obsolete-PPDB removed/
preserved, 14 fixed in code or corrected tests).

---

## 10. Remaining blockers before SOURCE-SYSTEM HARDENING

- `package-lock.json` shows a benign `name` change (`sistem-tu` →
  `sistem-tu-latest`) produced by `npm install`; no dependency change.
- Local PHP is 8.3 while `composer.lock` targets PHP 8.4 + `ext-intl`;
  `composer install` required `--ignore-platform-req` flags. Standardise the
  local PHP toolchain before hardening phases.
- The one intentionally-skipped integration test (`ParentApiPaymentTest.php:213`)
  still needs a Midtrans facade mock if full coverage is desired later.

---

## Final verification

- `~/sistem-tu` touched: **NO** (no files modified; HEAD unchanged).
- Full backend suite run: **213 passed / 1 skipped / 0 failed**.
- No commits pushed; local development only.
