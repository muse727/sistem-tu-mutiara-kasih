# TU-DAILY-CLOSE-UI — Verification

A thin browser-based Daily Close entry point over the **certified** SPP
Settlement Batch lifecycle. It reuses the exact same business logic as the
`integration:spp-close-batches` CLI command. No batch architecture, contract,
HMAC, reconciliation, outbox, accounting, or fee behavior was changed. No
commit, no push. Work confined to `~/sistem-tu-latest` (old `~/sistem-tu`
untouched).

## 1. Audit findings

- **RBAC:** role-based (`users.role`: `admin | tu | staf | kepala_sekolah`) via
  `App\Http\Middleware\EnsureRole` and `User` role helpers (`isAdmin()`, `isTu()`,
  `canManageFinance()`). **No Spatie permission package.** The smallest correct
  gate is therefore a role check, not a new permission (see §4).
- **Panel:** Filament v3, single `admin` panel (`AdminPanelProvider`), nav groups
  `Kesiswaan / Keuangan / Point of Sale / Pengaturan`. No `Pages/` dir existed;
  resources are discovered under `app/Filament/Resources`.
- **Filament env gate (pre-existing):** vendor `Filament\Http\Middleware\Authenticate`
  aborts **403 for every authenticated role** when `app.env !== 'local'` and the
  User does not implement `Filament\Models\Contracts\FilamentUser`. This project
  has no `canAccessPanel`/`FilamentUser` implementation, so in the `testing` env
  the whole `/admin` panel returns 403 regardless of role. This is unrelated to
  the new page and is why the codebase has zero existing Filament HTTP tests.
- **CLI close flow:** `routes/console.php` `integration:spp-close-batches`
  queries `SppSettlementBatch` where `status IN (DRAFT, READY_TO_CLOSE)`, calls
  `SppSettlementBatchService::close($batch)`, and when the result is `CLOSED`
  calls `buildOutboxEvent($batch)`. All locking / closed-batch / overflow /
  idempotency / outbox rules live inside the service.

## 2. Existing CLI close flow (reused, not duplicated)

1. **Eligible batch:** rows with `status IN (DRAFT, READY_TO_CLOSE)`.
2. **Close:** `SppSettlementBatchService::close()` — `lockForUpdate`, returns
   early if already closed (no reopen/rebatch), recomputes the snapshot, keeps an
   empty batch DRAFT, otherwise sets `CLOSED` + `closed_at`.
3. **Outbox:** on `CLOSED`, `buildOutboxEvent()` creates the deterministic v1.0
   `spp.settlement_batch` event via `firstOrCreate(batch_id)`.
4. **Guards:** model `booted()` immutability (throws `LogicException` on mutating
   a closed batch's identity/accounting fields or deleting it), overflow routing
   for late settlements, deterministic idempotency keys.

The browser UI invokes this identical sequence — `close()` then `buildOutboxEvent()`
— through the same service; it never mutates the model directly.

## 3. New Filament page / location

- **Class:** `app/Filament/Pages/SppDailyClose.php` (`App\Filament\Pages\SppDailyClose`).
- **View:** `resources/views/filament/pages/spp-daily-close.blade.php`.
- **Route:** `GET /admin/spp-daily-close`, name `filament.admin.pages.spp-daily-close`.
- **Navigation:** group **Keuangan**, label **"Daily Close SPP"**, icon
  `heroicon-o-lock-closed`, sort 2. Auto-discovered by `discoverPages`.

The page shows the currently eligible batch: period kind/start/end, batch
reference, status, total amount, payment count, bill count, and the existing
payment-method breakdown. It does **not** fabricate SENT/ACKNOWLEDGED or invent
fields; all figures come from the service's `recompute()` + the batch model.

## 4. Authorization mechanism

Uses the **existing role-based RBAC** (no second system, no new permission):

- `shouldRegisterNavigation()` + `canAccess()`: roles `admin`, `tu`, `staf` —
  others (e.g. `kepala_sekolah`) get **403** and no nav item.
- `canClose()` (action gate): roles `admin`, `tu` only — **staf may view but not
  close**, mirroring the finance-write pattern (`canManageFinance`).
- `mount()` calls `abort_unless(canAccess(), 403)` as defense-in-depth.

**Decision documented:** the codebase uses roles, not Spatie permissions, so the
smallest correct gate is a role check consistent with `EnsureRole`/`User` helpers.
Introducing a Spatie permission would create a parallel RBAC — explicitly avoided.

## 5. Service reused

`App\Services\Integration\SppSettlementBatchService`:
- `recompute()` — live figures for operator review (safe on DRAFT/READY_TO_CLOSE).
- `close()` — the certified close (identical to CLI).
- `buildOutboxEvent()` — the certified v1.0 outbox emission.

No close rules are re-implemented in the page.

## 6. Confirmation behavior

The close button opens an explicit `<x-filament::modal id="confirm-daily-close">`
showing **period, total, and payment/bill counts** before execution. Only the
"Ya, Tutup Batch" button triggers `wire:click="closeBatch"`. Single confirmation,
no unnecessary multi-step workflow. Uses existing Filament modal conventions.

## 7. Empty / error states

- **Empty:** no DRAFT/READY_TO_CLOSE batch → "Tidak ada batch yang memenuhi
  syarat" empty section.
- **Already/none on action:** `closeBatch()` re-checks and warns when the batch
  vanished or none is eligible.
- **Empty batch:** service leaves it DRAFT → "Tidak ada yang ditutup" warning
  (matches CLI semantics).
- **Failure:** exceptions are caught, logged (`Log::error` with batch/user
  context, **no stack trace to the operator**), and surfaced via a generic danger
  `Notification`. Exceptions are never swallowed.
- **Post-close:** state is reloaded (`loadEligibleBatch()`), success notification
  shows CLOSED + total/counts, and the page returns to the empty state.

## 8. Concurrency / security behavior

- **No client-side locking.** Concurrency is enforced entirely by the service's
  `lockForUpdate` + the `isClosed()` early-return. Two concurrent close attempts
  both enter `close()`; the row lock serializes them and the second sees the
  already-closed state and returns without mutation or rebatch.
- **Idempotency:** outbox `firstOrCreate` + deterministic keys — repeated close
  produces no duplicate batch/event.
- **Closed-batch immutability:** model guard throws on mutation/deletion.
- **Overflow:** late settlements route to a new overflow batch; the frozen closed
  batch is untouched.
- **Authorization:** role gate at navigation, route (`canAccess`), `mount()`, and
  action (`canClose`).

## 9. Tests

`tests/Feature/SppDailyClosePageTest.php` — **12 tests, 45 assertions**, all pass.
Coverage:
- **Auth:** tu/admin access; staf view-only (canAccess true, canClose false);
  `kepala_sekolah` denied (403, no nav); guest redirected to login.
- **Eligibility:** empty state; eligible batch renders with live figures/status.
- **Close:** confirmed close → CLOSED; outbox event built once; **v1.0 payload
  contains `total_amount/payment_count/bill_count/breakdown` and NO fee keys**.
- **Safety:** staf close attempt → 403 + batch unchanged; repeated close
  idempotent; late settlement → overflow; closed batch immutable (`LogicException`).
- **Regression/convergence:** CLI command still closes; empty batch stays DRAFT.

Tests exercise the page as a Livewire component (the panel-boundary-independent
surface) plus `canAccess()`/`shouldRegisterNavigation()`, because the
pre-existing Filament env gate 403s all HTTP `/admin` access in the `testing`
env (see §1). No new test framework was added (existing Pest + Livewire used).

## 10. CLI regression result

- Focused batch/close suites: **71 passed** (248 assertions).
- Full TU suite (`composer test`): **446 passed, 1 skipped** (1,404 assertions) —
  up from the certified baseline of 434 passed, i.e. **+12 new tests, zero
  regressions**. The 1 skip is the pre-existing Midtrans facade-mock test.
- The CLI command `integration:spp-close-batches` remains intact and green.

## 11. Any gaps

1. **Filament panel is unreachable over HTTP in non-local envs (pre-existing, LOW).**
   The vendor `Authenticate` middleware 403s all roles when `app.env !== 'local'`
   because `User` does not implement `FilamentUser`. In the real **local**
   deployment the panel (and this page) is reachable normally; the gate only
   affects automated HTTP tests. If HTTP-level panel tests (or non-local panel
   hosting) are ever required, implementing `FilamentUser::canAccessPanel()` on
   `User` is the correct, separate scope — it is intentionally **not** added here
   to avoid touching the shared auth surface.

## Final status: VERIFIED

Authorized TU operators (admin/tu) can open **Daily Close SPP** at
`/admin/spp-daily-close`, review the live eligible batch, explicitly confirm, and
close via the certified `SppSettlementBatchService::close()` path — producing a
CLOSED batch and a v1.0 outbox event, with immutability, overflow, idempotency,
and authorization all preserved. CLI and browser converge on the same business
logic; the full TU suite passes; no Finance/contract/fee changes occurred.
