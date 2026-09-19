# TU-STUDENT-READ-CONTRACT

Machine-to-machine **READ-ONLY** student contract exposed by TU for the
Finance (Tabungan) backend. TU remains the authoritative student master;
Finance is a consumer. There is **no Finance → TU writeback** — the surface
has no write methods by design.

---

## 1. Purpose

Finance Tabungan needs to look up students (to search, select, and display
them in savings workflows) without owning student master data. This contract
is the smallest authenticated read surface that satisfies that need.

## 2. Endpoint(s)

Base path (API routes): `/api/integration/finance/students`

| Method | Path | Purpose |
| :-- | :-- | :-- |
| `GET` | `/api/integration/finance/students` | Bounded, paginated search |
| `GET` | `/api/integration/finance/students/{student_uuid}` | Single-student lookup by the sole identity |

There are NO write endpoints.

## 3. Authentication

Reuses the already-certified TU → Finance HMAC spec
(`docs/TU-FINANCE-HMAC-SPEC.md`) — no new authentication protocol.

Headers:
- `X-TU-Key-Id`
- `X-TU-Timestamp` (Unix seconds; accepted within **±300 s**)
- `X-TU-Signature` = `base64(HMAC-SHA256(secret, canonical))`

Canonical string (identical to the certified scheme):

    {timestamp}\n{METHOD}\n{path}\n{sha256_hex(raw_body)}

For GET the body is empty, so `sha256_hex('')` is the well-known empty-string
digest. `{path}` is the request path only (no query string). The raw body is
never parsed before hashing.

Components:
- Verifier: `app/Services/Integration/HmacRequestVerifier.php`
- Middleware: `app/Http/Middleware/FinanceHmacAuthentication.php` (alias
  `finance.hmac`, registered in `bootstrap/app.php`)
- Keys: `config('integration.finance_hmac_keys')` — map of key-id → secret
  (env-only; defaults to the same `TU_HMAC_KEY_ID`/`TU_HMAC_SECRET` used for
  outbound, with `TU_HMAC_INBOUND_KEY_ID`/`TU_HMAC_INBOUND_SECRET` overrides
  allowing a future key split).

**Replay protection** (same model as the certified scheme): the timestamp is
inside the signed canonical string and only ±300 s is accepted, bounding any
replay window. Because every endpoint behind this verifier is READ-ONLY, an
in-window replay can only re-read data — it has no second business effect.

This is a **service-to-service** surface: it is not a browser/session route,
is not behind Sanctum, and the shared secret lives only server-side on both
systems. A browser can never legitimately possess or use it.

## 4. Request contract

### List (search) — `GET /api/integration/finance/students`

Query parameters (validation enforced):

| Param | Rule | Behavior |
| :-- | :-- | :-- |
| `student_uuid` | optional, `uuid` | exact match |
| `nis` | optional, string ≤ 30 | exact match |
| `nisn` | optional, string ≤ 20 | exact match |
| `name` | optional, string 2–100 | partial (`LIKE %term%`) — controlled display search only |
| `status` | optional, one of the 7 known statuses | exact match |
| `page` | optional, integer ≥ 1 | pagination page |
| `per_page` | optional, integer 1–50 | page size (default 15, hard cap 50) |

**At least one search filter is required.** A request with no filter returns
`422` — the endpoint refuses to run an unbounded scan of the student table.

### Show — `GET /api/integration/finance/students/{student_uuid}`

| Param | Rule | Behavior |
| :-- | :-- | :-- |
| `include_trashed` | optional boolean | when truthy, enables explicit historical lookup of a soft-deleted student |

## 5. Response contract

Every record contains ONLY the approved minimal field set:

```json
{
  "student_uuid": "…",   // SOLE cross-system identity
  "nis": "…",            // display/lookup metadata only
  "nisn": "…",           // display/lookup metadata only
  "name": "…",
  "kelas": "6A (Unggulan)",   // display label (null if unassigned)
  "rombel": "…",              // display label (null if unassigned)
  "status": "active",
  "is_active": true,
  "deleted_at": null
}
```

List response (`200`):

```json
{
  "status": "success",
  "data": {
    "records": [ /* approved fields per student */ ],
    "pagination": { "current_page": 1, "last_page": 3, "per_page": 15, "total": 42 }
  }
}
```

Show response (`200`): `{ "status": "success", "data": { /* approved fields */ } }`

**Never exposed:** passwords, parent credentials, portal/FCM tokens, payment
data, internal audit data, or any unnecessary personal fields (gender,
birth_date, address, email, parent_phone, …).

## 6. student_uuid identity rule

`student_uuid` is the **sole stable cross-system identity** (immutable,
unique, generated once at student creation). `nis`, `nisn`, `name`, `kelas`,
and `rombel` are display/lookup metadata **only** — they may change and must
never be used for cross-system matching. A `nis`/`nisn`/`name` search is a
lookup convenience that still returns `student_uuid` as the identity.

## 7. Search behavior

- All filtering is **server-side**.
- `student_uuid`, `nis`, `nisn`, `status` are exact matches; `name` is a
  partial match for controlled display search (justified by the existing
  Tabungan "Cari nama siswa / NIS…" UX).
- Multiple filters combine (AND).
- Ordering: `name`, then `id` (deterministic pagination).

## 8. Pagination / bounds

- Results are always paginated (`records` + `pagination` metadata).
- `per_page` is hard-capped at **50** (default 15).
- The list endpoint requires ≥1 filter — there is **no unbounded student
  dump**.

## 9. Lifecycle behavior

- **Soft-deleted** students are **excluded** from list search and from show
  by default (`404`).
- Show supports explicit historical lookup via `include_trashed=1`; the
  response then carries a non-null `deleted_at` so Finance can recognize and
  explain a previously-linked student that has since been removed.
- The contract intentionally does NOT auto-reactivate or interpret statuses;
  it reports the raw `status` plus a convenience `is_active` flag.

**Limitation (documented):** Finance Tabungan currently needs only active
students for new activity, plus occasional historical explanation — which is
exactly the implemented behavior. If a broader historical-list requirement
emerges, the contract can be extended (e.g. a trashed list filter) without
breaking existing consumers.

## 10. Security controls

- HMAC machine-to-machine auth (§3); `401` with deterministic codes
  (`INVALID_SIGNATURE`, `INVALID_REQUEST_TIMESTAMP`, `UNKNOWN_KEY_ID`) on any
  auth failure — never reaching the controller.
- Throttling: `throttle:60,1` (60 req/min) on the route group — consistent
  with the Finance outbound client conventions.
- Bounded results + required filter (no unbounded dump).
- Minimal field set only; sensitive data structurally excluded.
- Secrets are env-only, never committed, never logged.
- Read-only surface; no writes possible.

## 11. Example request/response

Request (signed):

    GET /api/integration/finance/students?nis=20260021
    X-TU-Key-Id: tu-local
    X-TU-Timestamp: 1789618439
    X-TU-Signature: base64( HMAC-SHA256(secret, "1789618439\nGET\n/api/integration/finance/students\n"+sha256_hex("")) )

Response `200`:

```json
{
  "status": "success",
  "data": {
    "records": [
      {
        "student_uuid": "7fd38b9a-25ca-4f1e-9a2b-…",
        "nis": "20260021",
        "nisn": "0098765432",
        "name": "Ahmad Fauzan",
        "kelas": "6A (Unggulan)",
        "rombel": "6A",
        "status": "active",
        "is_active": true,
        "deleted_at": null
      }
    ],
    "pagination": { "current_page": 1, "last_page": 1, "per_page": 15, "total": 1 }
  }
}
```

Single lookup `GET …/students/{student_uuid}` → `200` with `data` = the
approved record; unknown/malformed UUID → `404`.

## 12. Tests

`tests/Feature/FinanceStudentReadContractTest.php` (30 tests, Pest) covers:
- AUTH: valid HMAC accepted; invalid signature rejected; expired/far-future/
  non-numeric timestamp rejected; unknown key rejected; unconfigured secret
  rejected; in-window replay behavior (read-only); wrong-path signature
  rejected.
- IDENTITY: lookup by `student_uuid`; nonexistent/malformed UUID → `404`;
  UUID returned exactly; `nis`/`nisn` search returns UUID identity.
- SEARCH: ≥1 filter required (no unbounded dump); partial name search;
  status filter; invalid status → `422`; pagination metadata; `per_page`
  over the cap → `422`.
- DATA: only approved fields returned; sensitive fields never present;
  kelas/rombel labels; `is_active` flag.
- LIFECYCLE: soft-deleted excluded from list; show `404` by default;
  `include_trashed=1` historical lookup marked with `deleted_at`.
- SECURITY: session/browser request without HMAC rejected; read-only
  surface performs no writes.

Full suite after this task: **481 passed, 1 skipped** (was 451 passed,
1 skipped before; +30 new tests). No existing tests were weakened.

## 13. Known limitations

- List search excludes soft-deleted students; historical lookup is only via
  show + `include_trashed=1` (see §9).
- `kelas`/`rombel` are display labels only (class data is relational in TU);
  they are not identity and may be null.
- Inbound keys default to the same shared secret as the outbound transport;
  a dedicated inbound key split is available via `TU_HMAC_INBOUND_*` env vars
  but not required.
- No caching/sync layer is introduced (by design — Finance resolves and
  caches its own local reference at selection time).
