# SPEC-031 — Hexagram Favorites

**Status:** verified
**Owner:** unassigned
**Last updated:** 2026-08-21

## Problem

[SPEC-025](../consultation-favorites/spec.md) deliberately deferred the hexagram half of feature
4 ("favorites on hexagrams and consultations"), because it required giving `HexagramController` —
until now a purely stateless reference-data controller with a comment on its own class
explaining it "has no database access to configure" — its first-ever database dependency. That's
a real, separable architectural step, now taken on its own.

## Purpose

Let a user mark any of the 64 hexagrams as a favorite (independent of any consultation) and see
that reflected on the Hexagram Explorer and detail page, with a filter to browse favorites only.

## Scope

- New table `favorite_hexagrams` (`king_wen_number INTEGER PRIMARY KEY`) — a bare marker table,
  no relation to `consultations` (a hexagram favorite is a property of the hexagram itself, not
  of any particular reading of it).
- `App\Hexagrams\HexagramFavoritesRepository` (interface) / `SqliteHexagramFavoritesRepository`:
  `isFavorite(int): bool`, `add(int): void` (idempotent — `INSERT OR IGNORE`), `remove(int): void`,
  `allFavoriteNumbers(): list<int>` (one query, used to annotate every hexagram in `index()`
  without 64 individual lookups).
- `HexagramController` gains a constructor (`Config $config`, building the repository via
  `Database::connect($config)`) — the comment explaining why it previously had none is removed,
  since that premise no longer holds.
- `GET /api/hexagrams` and `GET /api/hexagrams/{id}` responses gain `favorite: boolean`.
- Two new endpoints, matching the REST shape of toggling a sub-resource rather than mutating the
  hexagram's own (immutable, classical) data: `PUT /api/hexagrams/{number}/favorite` (mark, `204`,
  idempotent) and `DELETE /api/hexagrams/{number}/favorite` (unmark, `204`, idempotent — removing
  a non-favorite is not an error).
- `apps/web`: `entities/hexagram/model.ts`'s `Hexagram` gains `favorite: boolean`;
  `entities/hexagram/api.ts` gains `markHexagramFavorite()`/`unmarkHexagramFavorite()`.
- `HexagramListPage.vue`: a star toggle on each hexagram card (composing with the existing
  SPEC-026 search as an additional filter, same "Favorites only" chip pattern
  [SPEC-025](../consultation-favorites/spec.md) established for consultations).
- `HexagramDetailPage.vue`: a favorite toggle button, same visual pattern as
  `ConsultationPage`'s.

## Out of scope

- **`GET /api/hexagrams/from-lines` and `GET /api/hexagrams/compare` gaining `favorite`.** Both
  return hexagrams computed on the fly (a manually-built line pattern, or an a/b comparison pair)
  that may or may not correspond to a real King Wen number lookup path a user would favorite from;
  neither existing page built on top of them offers a favorite toggle, so there's no consumer for
  the field there. `index()`/`show()` (the two pages that actually list/display hexagrams for
  browsing) are the only two that need it.
- **A `/hexagrams?favorite=true` server-side filter.** `GET /api/hexagrams` already returns all
  64 with `favorite` included; `HexagramListPage` already filters client-side (SPEC-026's search),
  so "favorites only" is one more client-side filter stage, matching that precedent exactly.
- **Ordering favorites first** in the Explorer grid. Filtering only, same choice
  [SPEC-025](../consultation-favorites/spec.md) made for consultations.

## User behavior

```
PUT /api/hexagrams/1/favorite -> 204
GET /api/hexagrams/1 -> "favorite": true
DELETE /api/hexagrams/1/favorite -> 204
GET /api/hexagrams/1 -> "favorite": false

/hexagrams -> star icon per card, toggle -> "Favorites only" chip narrows the grid
/hexagrams/1 -> "☆ Add to Favorites" / "★ Favorited" toggle button
```

## Functional requirements

- **REQ-HEXFAV-001** — `PUT /api/hexagrams/{number}/favorite` MUST mark that hexagram favorite
  and return `204`; a nonexistent King Wen number MUST return `404`.
- **REQ-HEXFAV-002** — `DELETE /api/hexagrams/{number}/favorite` MUST unmark that hexagram and
  return `204`, even if it wasn't favorited (idempotent).
- **REQ-HEXFAV-003** — `GET /api/hexagrams` and `GET /api/hexagrams/{id}` MUST include
  `favorite: boolean`, accurate against `favorite_hexagrams`.
- **REQ-HEXFAV-004** — `HexagramListPage` MUST render a star toggle per hexagram and a
  "Favorites only" filter composing with the existing search (SPEC-026).
- **REQ-HEXFAV-005** — `HexagramDetailPage` MUST render a working favorite toggle button.

## Non-functional requirements

- **REQ-HEXFAV-006** — `index()`'s `favorite` annotation MUST use one bulk lookup
  (`allFavoriteNumbers()`), not 64 individual `isFavorite()` calls.
- **REQ-HEXFAV-007** — No component outside `entities/hexagram` may call `apiGet`/a mutating
  request directly for this data.

## Data requirements

New table `favorite_hexagrams` (`king_wen_number INTEGER PRIMARY KEY`). No change to any existing
table.

## API requirements

`GET /api/hexagrams`/`GET /api/hexagrams/{id}` responses gain `favorite`. Two new endpoints:
`PUT`/`DELETE /api/hexagrams/{number}/favorite`.

## Edge cases

- `PUT .../favorite` on an already-favorited hexagram → still `204`, no error, no duplicate row
  (`INSERT OR IGNORE` against the primary key).
- `DELETE .../favorite` on a hexagram that was never favorited → still `204`.
- `PUT`/`DELETE .../{number}/favorite` for a King Wen number outside 1-64 → `404`, matching
  `GET /api/hexagrams/{id}`'s own existing not-found behavior for the same case.

## Acceptance criteria

- [x] `PUT`/`DELETE .../favorite` toggle correctly, both idempotent, `404` for an invalid number.
- [x] `GET /api/hexagrams`/`{id}` include an accurate `favorite` field.
- [x] `HexagramListPage` toggles favorites and filters to favorites-only, composing with search.
- [x] `HexagramDetailPage` has a working favorite toggle.
- [x] `npm run verify` passes end to end.
- [x] Manually verified against the real running API/UI.
