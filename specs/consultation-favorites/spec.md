# SPEC-025 — Consultation Favorites

**Status:** verified
**Owner:** unassigned
**Last updated:** 2026-08-21

## Problem

Feature 4 of the plan's next batch asks for bookmarking/favorites on hexagrams and consultations.
Consultations are the more immediately useful half: a user re-reading their own history
(SPEC-022's timeline) has no way to mark a particular reading as one worth coming back to,
independent of tags (which describe *what* a reading was about, not *how much it mattered*).

## Purpose

Let a consultation be marked favorite (a plain boolean, toggleable at any time via the existing
`PATCH` endpoint) and let the history page filter down to favorites only.

## Scope

- `consultations` gains `is_favorite INTEGER NOT NULL DEFAULT 0` (SQLite boolean-as-integer,
  hydrated to/from PHP `bool`).
- `Consultation` gains `public bool $favorite = false` (threaded through the constructor,
  `create()`, `reconstitute()`, and every existing wither, per the SPEC-019/020/021 checklist
  precedent) and a new `withFavorite(bool $favorite): self`.
- `PATCH /api/consultations/{id}` accepts an optional `favorite` boolean — present sets it to
  exactly that value; absent leaves it unchanged. Added to the endpoint's existing "at least one
  field to update" check.
- `GET`/`POST`/`PATCH` responses on `/api/consultations*` all gain `favorite: boolean` (default
  `false` for a newly created consultation — `POST` does not accept `favorite` at creation time,
  since marking something a favorite is naturally a decision made after seeing it, not while
  casting it).
- `ConsultationPage.vue`: a toggle button ("☆ Add to Favorites" / "★ Favorited") that PATCHes
  `favorite`.
- `ConsultationHistoryPage.vue`: a "★ Favorites only" toggle button, alongside SPEC-022's tag
  chips, that filters the already-loaded (client-side) grouped list down to `favorite === true`
  consultations — combines with an active tag filter (AND), not a separate mode.

## Out of scope

- **Hexagram favorites.** `HexagramController` currently has zero database access by design (its
  own comment: "this controller has no database access to configure" — SPEC-003). Adding
  favorites there means introducing a new table and wiring the controller to `Database::connect()`
  for the first time, a materially different, separable change from the consultation half of this
  feature. Flagged here as the natural next spec, not silently folded in or dropped.
- **Setting `favorite` at consultation creation time (`POST`).** Not a real workflow — nothing to
  favorite yet at the moment of casting.
- **Sorting favorites to the top of the history list.** This spec only filters (matching
  SPEC-022's tag-filter precedent exactly); reordering is a distinct, unrequested behavior change
  to the list's otherwise-always-newest-first ordering.
- **A dedicated `/consultations/favorites` route.** Reuses the existing history page with a
  toggle, the same choice SPEC-022 made for tags rather than a separate URL per filter.

## User behavior

```
PATCH /api/consultations/{id}
{"favorite": true}
  -> 200, "favorite": true

PATCH /api/consultations/{id}
{"favorite": false}
  -> 200, "favorite": false

/consultations/{id}
  -> "☆ Add to Favorites" button -> click -> "★ Favorited" (persisted via PATCH)

/consultations
  -> "★ Favorites only" toggle (off by default, full list shown)
  -> click -> list narrows to favorite consultations only, respecting any active tag filter too
  -> click again -> full list returns
```

## Functional requirements

- **REQ-FAVORITE-001** — `PATCH /api/consultations/{id}` MUST accept an optional `favorite`
  boolean; present sets the consultation's favorite flag to that exact value, absent leaves it
  unchanged, and `favorite` counts toward the endpoint's existing "at least one field" check.
- **REQ-FAVORITE-002** — A non-boolean, present `favorite` value MUST return `422`.
- **REQ-FAVORITE-003** — `GET`/`POST`/`PATCH` responses on `/api/consultations*` MUST include
  `favorite: boolean`.
- **REQ-FAVORITE-004** — A newly created consultation MUST have `favorite: false`; `POST` MUST
  NOT accept a `favorite` field in its request body's effect (silently ignored if present, since
  no other create-time field in this API errors on an unrecognized extra key either).
- **REQ-FAVORITE-005** — Every existing `Consultation` wither (`withAddedNote`, `withAddedTag`,
  `withUpdatedContext`, `withUpdatedOutcome`, `withFollowUpTo`) MUST preserve an already-set
  `favorite` value unchanged.
- **REQ-FAVORITE-006** — `ConsultationPage` MUST render a toggle button reflecting and updating
  the consultation's favorite state.
- **REQ-FAVORITE-007** — `ConsultationHistoryPage` MUST render a "Favorites only" toggle that,
  when active, narrows the (already date-grouped, tag-filterable per SPEC-022) list to favorite
  consultations, combinable with an active tag filter.
- **REQ-FAVORITE-008** — Existing (pre-SPEC-025) consultations MUST continue to load with
  `favorite: false`, no error.

## Non-functional requirements

- **REQ-FAVORITE-009** — Favorites filtering on the history page happens entirely client-side
  over the single already-fetched list, matching SPEC-022's tag-filter precedent — no new query
  parameter on `GET /api/consultations`.
- **REQ-FAVORITE-010** — No component outside `entities/consultation` may call `apiPatch`
  directly for this.

## Data requirements

`consultations` gains one `NOT NULL` `INTEGER` column, `is_favorite`, default `0`. Additive,
backward-compatible with every existing row.

## API requirements

`PATCH /api/consultations/{id}` request body gains `favorite`. `GET`/`POST`/`PATCH` responses
gain `favorite`. No endpoint URL, method, or status code changes.

## Edge cases

- Toggling `favorite` on a consultation that also has notes/tags/context/outcome/follow-up links
  set → none of those are disturbed (matches REQ-FAVORITE-005 and the same pattern every prior
  wither-adding spec verified).
- `"favorite": null` in a `PATCH` body → `422` (booleans have no meaningful "clear" state the way
  the optional string context fields do — `null` is simply not a valid value for this field,
  unlike SPEC-019's explicit-null-clears semantics for text fields).
- Both a tag filter and "Favorites only" active with nothing matching both → the same
  "No consultations match the selected tags" empty state SPEC-022 already shows (favorites is an
  additional narrowing condition on the same filtered result, not a separate empty-state message).

## Acceptance criteria

- [x] `PATCH` sets `favorite` to `true`/`false`; a non-boolean value returns `422`.
- [x] `GET`/`POST`/`PATCH` responses all include `favorite`, defaulting to `false` on creation.
- [x] Every existing wither preserves an already-set `favorite` value.
- [x] An existing (pre-migration) consultation loads with `favorite: false`.
- [x] `ConsultationPage` toggles favorite state via a working button.
- [x] `ConsultationHistoryPage`'s "Favorites only" toggle narrows the list correctly, combining
      with an active tag filter.
- [x] `npm run verify` passes end to end.
- [x] Manually verified against the real running API/UI.
