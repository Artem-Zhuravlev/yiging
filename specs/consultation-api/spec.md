# SPEC-006 — Consultation API

**Status:** verified
**Owner:** unassigned
**Last updated:** 2026-08-14

> Numbering note: `specs/README.md`'s current-specs table and SPEC-002's "Out of scope" section
> previously forward-referenced "SPEC-006" as a future *Journal* spec. That need is already
> satisfied — `Consultation.notes`/`.tags` (SPEC-005) absorbed what a separate journal spec
> would have covered. SPEC-006 is reused here for the Consultation HTTP API instead, so the
> numbering stays sequential rather than leaving a permanent gap.

## Problem

SPEC-004 (Casting) and SPEC-005 (Readings) are only reachable from PHP code today — nothing
outside `apps/api` can cast a hexagram, ask a question, or read past consultations. The plan's
own flow (`Vue → PHP API → Application → Domain → ... → Vue`) requires an HTTP boundary before
any frontend work can start; this spec is that boundary for consultations specifically
(hexagram/trigram browsing is SPEC-003, not touched here).

## Purpose

Expose `POST /api/consultations`, `GET /api/consultations`, and `GET /api/consultations/{id}`
so a caller can cast a hexagram (via any SPEC-004 method) and get back a persisted, retrievable
`Consultation` (SPEC-005) — the "Application" orchestration (cast → build aggregate → persist)
and the "API" routing/controller/DTO work happen together in this one spec, since orchestration
with no HTTP surface has no way to be exercised end-to-end.

## Scope

- `ConsultationController` with 3 actions (`create`, `index`, `show`), following the existing
  `Kernel`/`HealthController` conventions (constructor takes only `Config`; FastRoute + PDO
  wired the same way `HealthController`/`Database::connect()` already do it).
- Request parsing + validation for all 3 `CastingMethodName` values (`three_coins`, `manual`,
  `random`), including the six-line payload `ManualMethod` needs.
- JSON response shape for a `Consultation` (same shape for list items and the single-resource
  view — no separate summary/detail DTO, since nothing yet needs one).
- Wiring new routes into `apps/api/config/routes.php`.
- HTTP-level error handling: malformed/invalid requests → `422`; unknown id → `404`.

## Out of scope

- `GET /api/hexagrams`, `/api/trigrams`, `/api/texts/{hexagramId}` — SPEC-003 (Hexagram
  Explorer), a separate concern from consultations.
- `PATCH /api/consultations/{id}` (adding notes/tags after creation) — `Consultation` already
  supports `withAddedNote()`/`withAddedTag()` (SPEC-005); wiring them to HTTP is a follow-up
  once there's a concrete UI need, not invented speculatively here.
- `POST /api/interpretations/{consultationId}` (AI) — SPEC-008.
- Auth/rate-limiting — no auth exists anywhere in this API yet; out of scope until a spec
  introduces it project-wide, not per-endpoint.
- Pagination/filtering/sorting on `GET /api/consultations` — plan section 24, deferred until
  there's a UI that needs it (currently returns the full `findAll()` result, newest-first,
  which SPEC-005 already guarantees).
- Frontend consumption of this API — SPEC-00x, later.

## User behavior

```
POST /api/consultations
{"question": "Should I take the offer?", "method": "three_coins"}
  -> 201, body: the created Consultation (id assigned, primary+resulting hexagram,
     changing line positions, empty notes/tags, createdAt)

POST /api/consultations
{"question": "...", "method": "manual",
 "lines": [{"polarity": "yang", "changing": false}, ... exactly 6 ...]}
  -> 201, same shape, built from the given lines via SPEC-004's ManualMethod

POST /api/consultations
{"question": "", "method": "three_coins"}
  -> 422, {"error": "..."} (empty question rejected by Consultation::create())

POST /api/consultations
{"question": "...", "method": "not-a-real-method"}
  -> 422, {"error": "..."} (unknown method)

GET /api/consultations
  -> 200, JSON array of all consultations, newest first

GET /api/consultations/{id}
  -> 200, the Consultation, or 404 {"error": "Not Found"} if no such id
```

## Functional requirements

- **REQ-CAPI-001** — `POST /api/consultations` MUST accept `question` (string) and `method`
  (one of `three_coins`/`manual`/`random`) and, for `method: manual`, `lines` (exactly 6
  objects, each `{polarity: "yin"|"yang", changing: bool}`, position implied by array order).
- **REQ-CAPI-002** — On success, `POST /api/consultations` MUST: select the `DivinationMethod`
  matching `method` (`ThreeCoinsMethod`/`ManualMethod`/`RandomMethod`, each with a
  `RandomIntCoinTosser` where randomness is needed), call `cast()`, build a `Consultation` via
  `Consultation::create()` (id from `ConsultationIdGenerator`, `createdAt` from `Clock`),
  persist it via `ConsultationRepository::save()`, and respond `201` with the created
  consultation's JSON representation.
- **REQ-CAPI-003** — An invalid `method` value, a missing/empty `question`, or a malformed
  `lines` payload for `method: manual` MUST respond `422` with a JSON error body, never a `500`
  or an uncaught exception.
- **REQ-CAPI-004** — `GET /api/consultations` MUST respond `200` with a JSON array of all
  consultations, newest-first (delegates to `ConsultationRepository::findAll()`, already
  ordered per SPEC-005 REQ-READ-009).
- **REQ-CAPI-005** — `GET /api/consultations/{id}` MUST respond `200` with that consultation's
  JSON representation, or `404` with `{"error": "Not Found"}` if no consultation with that id
  exists (matches the existing `Kernel::handle()` not-found convention).
- **REQ-CAPI-006** — The JSON representation of a `Consultation` MUST include: id, question,
  method, primary hexagram (King Wen number, Chinese name, pinyin), changing line positions,
  resulting hexagram (King Wen number, Chinese name, pinyin), createdAt (ISO 8601), notes
  (label, text, createdAt each), and tags.

## Non-functional requirements

- **REQ-CAPI-007** — `ConsultationController` MUST contain no business logic — it parses the
  request, delegates to `App\Casting`/`App\Readings` domain code, and maps the result to JSON.
  Casting, validation of domain invariants (empty question, tag dedup, etc.), and persistence
  logic all already live in SPEC-004/SPEC-005 and MUST NOT be duplicated in the controller.
- **REQ-CAPI-008** — All persistence access goes through `ConsultationRepository` — the
  controller MUST NOT construct SQL or touch `PDO` directly.

## Data requirements

None beyond what SPEC-005 already defined.

## API requirements

See "User behavior" and "Functional requirements" above — this section *is* the API spec for
this feature.

## Edge cases

- `method: manual` with `lines` present but not exactly 6 entries → `422` (surfaces
  `ManualMethod`'s existing `\InvalidArgumentException`, caught and mapped, not duplicated
  validation logic in the controller).
- `method: manual` with an invalid `polarity` value (not `"yin"`/`"yang"`) → `422`.
- `method: three_coins` or `random` with a `lines` field present in the body → ignored (only
  `manual` reads it); no error, since extra ignored fields are harmless and forward-compatible.
- Zero changing lines from a cast → `changingLinePositions` is `[]` in the response, resulting
  hexagram equals primary (falls out of SPEC-002/004/005 with no special-casing here).
- Requesting `GET /api/consultations/{id}` with an id that's syntactically odd (e.g. not a
  UUID) → still just a `findById()` miss → `404`, no separate format validation needed since
  `id` is an opaque string everywhere in this system.

## Acceptance criteria

- [x] `POST /api/consultations` creates and persists a consultation for all 3 methods
      (`three_coins`, `manual`, `random`), verified via a feature test per method.
- [x] `POST /api/consultations` returns `422` (not `500`) for: empty question, invalid method,
      malformed manual `lines`.
- [x] `GET /api/consultations` returns all consultations, newest-first.
- [x] `GET /api/consultations/{id}` returns the correct consultation, or `404` for a missing
      one.
- [x] A full round trip (`POST` then `GET /{id}`) reflects the same hexagram, changing lines,
      and question back.
- [x] `ConsultationController` has no direct `PDO`/SQL usage (only via `ConsultationRepository`).
- [x] All new tests run against the existing `Kernel`/routing stack, the same way
      `HealthEndpointTest` does (no new test infrastructure invented).

`apps/api/src/Readings/ConsultationController.php` implements all 3 actions, wired into
`apps/api/config/routes.php`. 11 new feature tests (41 total in `apps/api`, 208 assertions).
`npm run verify` passes end to end; also manually smoke-tested against the real `php -S` dev
server via curl (create/show/index/404/422), not just the `Kernel`-level test harness.

**Bug found and fixed along the way:** `SqliteConsultationRepository::findAll()` (SPEC-005)
ordered ties on `created_at` by `id DESC` — but `id` is a UUID with no relationship to
insertion order, so two consultations created within the same second could come back in the
wrong order. `testIndexReturnsAllConsultationsNewestFirst` caught this (it creates two
consultations back-to-back, fast enough to land in the same second). Fixed by tie-breaking on
SQLite's implicit `rowid` instead, which does track insertion order.
