# SPEC-026 — Full-Text Search

**Status:** verified
**Owner:** unassigned
**Last updated:** 2026-08-21

## Problem

Feature 5 of the plan's next batch asks for full-text search over consultation questions/notes and
hexagram Judgment/Image text. Both `GET /api/consultations` (SPEC-022) and `GET /api/hexagrams`
(SPEC-018) already return their full text content in one response each — the gap is purely that
neither page lets a user search what's already on screen.

## Purpose

Add a search box to `ConsultationHistoryPage` (matching against question text and note text) and
to `HexagramListPage` (matching against Chinese name, pinyin, Judgment, and Image text) — both
simple case-insensitive substring filters over data already fetched, no new API surface.

## Scope

- `ConsultationHistoryPage.vue`: a text input filtering the already-loaded, already tag-/favorite-
  filtered (SPEC-022/025) list further — a consultation matches if its `question` OR any of its
  `notes[].text` contains the query, case-insensitive. Composes with the existing filters (AND —
  a fourth stage in the same filter chain).
- `HexagramListPage.vue`: a text input filtering the already-loaded 64 hexagrams — a hexagram
  matches if its `chineseName`, `pinyin`, `judgment`, or `image` contains the query, case-
  insensitive (`judgment`/`image` can be `null`; a `null` field simply never matches).
- Both inputs debounce nothing (no network call to debounce — filtering is synchronous over
  in-memory data) and update the visible list on every keystroke.
- Empty query (default) shows the full list, unchanged from today.

## Out of scope

- **Server-side search or a new query parameter on either `GET` endpoint.** Both endpoints
  already return everything; searching client-side over data already in hand matches the
  precedent SPEC-022's tag filter and SPEC-025's favorites toggle both established.
- **Fuzzy matching, tokenization, ranking, or highlighting matched text.** Plain case-insensitive
  substring matching only — the plan asks for "full-text search," not a ranked search engine; a
  simple substring filter is what "search across the text I already have on this page" means at
  this app's scale.
- **Searching consultation context fields** (`context`, `whatHappenedBefore`,
  `whatUserWantsToUnderstand`, `backgroundInformation`, `initialInterpretation`) or outcome
  fields. The plan names "questions and notes" specifically; the five optional context fields and
  outcome are a distinct, larger body of free text that a future spec can add deliberately rather
  than silently bundling in here.
- **Searching hexagram line statements.** The plan names "Judgment/Image text" specifically;
  `lineStatements` (six per hexagram) is additional text this spec doesn't search, to keep the
  match surface exactly what was asked for.
- **A unified search bar across both consultations and hexagrams at once.** Two independent, page-
  local searches, matching how tag filtering (SPEC-022) and favorites (SPEC-025) are also page-
  local rather than global.

## User behavior

```
/consultations, search "offer"
  -> list narrows to consultations whose question or any note contains "offer" (case-insensitive),
     still respecting an active tag filter and/or "Favorites only"
  -> clear the search box -> full (still tag-/favorite-filtered) list returns

/hexagrams, search "heaven"
  -> list narrows to hexagrams whose Chinese name, pinyin, Judgment, or Image text contains
     "heaven" (case-insensitive)
  -> clear the search box -> full 64-hexagram list returns
```

## Functional requirements

- **REQ-SEARCH-001** — `ConsultationHistoryPage` MUST render a text input that filters the
  visible list to consultations whose `question` or any `notes[].text` contains the query
  (case-insensitive substring match).
- **REQ-SEARCH-002** — The consultation search MUST compose (AND) with an active tag filter
  and/or "Favorites only" toggle, not replace them.
- **REQ-SEARCH-003** — `HexagramListPage` MUST render a text input that filters the visible grid
  to hexagrams whose `chineseName`, `pinyin`, `judgment`, or `image` contains the query
  (case-insensitive substring match); a `null` `judgment`/`image` never matches on that field.
- **REQ-SEARCH-004** — An empty search query on either page MUST show the full (otherwise-
  filtered) list, identical to today's behavior.
- **REQ-SEARCH-005** — Both searches MUST re-filter synchronously as the user types, with no
  network request.

## Non-functional requirements

- **REQ-SEARCH-006** — Matching is plain, case-insensitive substring search
  (`String.prototype.toLowerCase().includes()` or equivalent) — no external search library, no
  new dependency.
- **REQ-SEARCH-007** — No component outside `pages/consultations` or `pages/hexagrams`
  respectively needs new state for this; each search query is page-local `ref` state.

## Data requirements

None — no schema or API response shape change.

## API requirements

None — `GET /api/consultations` and `GET /api/hexagrams` are unchanged.

## Edge cases

- A consultation with zero notes and a non-matching question → excluded, as expected (nothing to
  fall back to).
- Query matching a hexagram's `pinyin` but not its `chineseName` (or vice versa) → still matches
  (any one of the four fields matching is sufficient).
- Whitespace-only query (e.g. accidentally pressing space) → trimmed to empty, treated the same
  as no query at all (full list shown), not a literal-space substring search — matches user intent
  better than requiring fields to contain a literal space character.

## Acceptance criteria

- [x] Consultation search matches on question text.
- [x] Consultation search matches on note text.
- [x] Consultation search composes correctly with an active tag filter and "Favorites only".
- [x] Hexagram search matches on Chinese name, pinyin, Judgment, and Image text independently.
- [x] Clearing either search box restores the previous (otherwise-filtered) list.
- [x] `npm run verify` passes end to end.
- [x] Manually verified against the real running API/UI.
