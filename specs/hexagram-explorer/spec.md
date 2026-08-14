# SPEC-003 — Hexagram Explorer

**Status:** verified
**Owner:** unassigned
**Last updated:** 2026-08-14

> Numbering note: "SPEC-003 (Hexagram Explorer)" was forward-referenced by name in SPEC-002's
> "Out of scope" section from the start — this spec fills that reserved slot, out of order
> relative to SPEC-004/005/006 only because casting+persistence+the consultation API were built
> first. No renumbering needed here (contrast SPEC-006, which *did* need a numbering note).

## Problem

`packages/yijing-core` has held the full, correct 64-hexagram/8-trigram reference data since
SPEC-002, but nothing outside PHP can read it. A frontend hexagram browser, or any client that
wants to look up "what is hexagram 23" or "what are the 8 trigrams" without also casting or
consulting, has no endpoint to call.

## Purpose

Expose the static I Ching reference data — all 64 hexagrams and all 8 trigrams — over HTTP,
read-only, independent of casting/consultations (SPEC-004/005/006), so browsing and looking up
are possible without creating anything.

## Scope

- `GET /api/hexagrams` — all 64 hexagrams, King Wen order.
- `GET /api/hexagrams/{number}` — one hexagram (1–64), or `404`.
- `GET /api/trigrams` — all 8 trigrams.
- `HexagramController` (`apps/api/src/Hexagrams`) and `TrigramController`
  (`apps/api/src/Trigrams`) — the two placeholder directories from SPEC-001's bootstrap,
  finally used.
- JSON shape includes judgment/image/line-statement fields as `null` (SPEC-002's classical-text
  pass hasn't landed) rather than omitting them, so API consumers can rely on the fields always
  being present in the shape.

## Out of scope

- Consultations, casting, notes, tags — SPEC-004/005/006, unrelated to this read-only lookup
  surface.
- Classical text content itself (judgment/image/line statements) — SPEC-002's own remaining
  work; this spec just passes whatever is there (`null` today) straight through.
- Search/filtering (e.g. "hexagrams containing trigram X") — not asked for yet; `GET
  /api/hexagrams` returning all 64 is small enough that client-side filtering is sufficient for
  now (violates nothing to add server-side filtering later if a real need shows up).
- Nuclear/opposite/complement relationships (`YijingRelations`, SPEC-002) in the response — not
  needed by anything yet; easy to add as extra fields later without a breaking change.
- Frontend consumption — SPEC-00x, later.

## User behavior

```
GET /api/hexagrams
  -> 200, JSON array of 64 hexagrams, King Wen order (1..64)

GET /api/hexagrams/23
  -> 200, hexagram 23's full JSON representation

GET /api/hexagrams/999
  -> 404, {"error": "Not Found"}

GET /api/hexagrams/not-a-number
  -> 404, {"error": "Not Found"} (fails the same way as any other unmatched id — not a special
     400, since FastRoute's route only needs a valid path segment, not a validated integer)

GET /api/trigrams
  -> 200, JSON array of 8 trigrams
```

## Functional requirements

- **REQ-HEXAPI-001** — `GET /api/hexagrams` MUST respond `200` with all 64 hexagrams, ordered
  by King Wen number ascending.
- **REQ-HEXAPI-002** — `GET /api/hexagrams/{number}` MUST respond `200` with that hexagram's
  JSON representation for `number` in 1–64, and `404` with `{"error": "Not Found"}` for any
  other value (out of range, non-numeric, etc.) — never a `500`.
- **REQ-HEXAPI-003** — Each hexagram's JSON representation MUST include: King Wen number,
  Chinese name, pinyin, the six-line pattern (yin/yang per position 1–6), upper trigram, lower
  trigram (each with at least id/name/chineseName/pinyin/symbol), judgment, image, and the six
  line statements (the latter three `null` until SPEC-002's classical-text pass).
- **REQ-HEXAPI-004** — `GET /api/trigrams` MUST respond `200` with all 8 trigrams: id, name,
  Chinese name, pinyin, symbol, element, family member, direction, image — every field
  `Trigram`/`TrigramCatalog` already expose (SPEC-002).

## Non-functional requirements

- **REQ-HEXAPI-005** — `HexagramController`/`TrigramController` MUST contain no business
  logic — hexagram/trigram identity and structure come entirely from `packages/yijing-core`
  (`Hexagram::fromKingWenNumber()`, `Data\HexagramCatalog`, `TrigramId`, `Data\TrigramCatalog`);
  the controllers only map domain objects to JSON.
- **REQ-HEXAPI-006** — This module MUST NOT touch the database — it serves static in-code
  reference data, exactly like `yijing-core` itself has no DB access (SPEC-002 REQ-DM-001).

## Data requirements

None — no new tables, no persistence. Purely a read-through to `yijing-core`'s static data.

## API requirements

See "User behavior" / "Functional requirements" above.

## Edge cases

- `number = 0` or negative → `404` (falls out of `HexagramCatalog::entryFor()` throwing for
  anything outside 1–64, caught and mapped to `404` the same as any other unknown number).
- Trailing/leading whitespace or non-numeric `{number}` segment → FastRoute still matches the
  route (it's a plain string placeholder), the number simply fails to parse/look up → `404`,
  same handling path as an out-of-range integer — no separate "400 Bad Request" case needed.
- Judgment/image/line-statement fields are `null` today — every hexagram, not just some, so
  clients can't mistake "not yet populated" for a hexagram-specific gap.

## Acceptance criteria

- [x] `GET /api/hexagrams` returns exactly 64 hexagrams in King Wen order.
- [x] `GET /api/hexagrams/{number}` returns the correct hexagram for a sample spanning the
      range (1, a middle value, 64) and `404` for 0, 65, and a non-numeric segment.
- [x] `GET /api/trigrams` returns exactly 8 trigrams with all documented fields populated.
- [x] Hexagram JSON includes upper/lower trigram detail, not just a trigram id/name.
- [x] Neither controller imports `PDO`/`Database`/anything from `App\Readings`/`App\Casting`.
- [x] Feature tests run against the real `Kernel`/routing stack, matching
      `HealthEndpointTest`'s/`ConsultationControllerTest`'s existing pattern.

`apps/api/src/Hexagrams/HexagramController.php` and `apps/api/src/Trigrams/TrigramController.php`
implement all 3 endpoints, wired into `apps/api/config/routes.php`. 11 new feature tests (52
total in `apps/api`, 380 assertions). `npm run verify` passes end to end; also manually
smoke-tested against the real `php -S` dev server via curl.

**Found and fixed along the way:** both controllers initially stored an unused `Config`
property (accepted only to match `Kernel::invoke()`'s `new $class($config)` construction
contract). PHPStan level 8 flagged `property.onlyWritten`. Fixed by dropping the constructor
entirely — PHP silently ignores extra constructor arguments when a class declares no
`__construct()` (verified directly), so a controller with nothing to configure just omits it.
