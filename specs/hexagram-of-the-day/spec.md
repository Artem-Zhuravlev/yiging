# SPEC-032 — Hexagram of the Day

**Status:** verified
**Owner:** unassigned
**Last updated:** 2026-08-21

## Problem

Feature 13 of the plan's next batch asks for a "hexagram of the day" — something to greet a user
on the home page that changes daily, encouraging a moment of reflection even without casting a
full consultation. The 64-hexagram data is already fully available via
`GET /api/hexagrams/{id}`; nothing today picks one to feature.

## Purpose

Show a deterministic "Hexagram of the Day" on the home page — the same hexagram for every visit
on a given local calendar day, a different one the next day, computed purely client-side from
today's date (no persistence, no randomness, no server involvement beyond the existing
`GET /api/hexagrams/{id}`).

## Scope

- `entities/hexagram`: a pure function `hexagramOfTheDayNumber(date: Date = new Date()): number`
  — maps the local calendar date (year/month/day, not the exact timestamp) to a King Wen number
  1-64, via day-count-since-epoch modulo 64. Deterministic: the same calendar date always
  produces the same number, in any timezone the browser happens to be in, and different calendar
  days produce (in aggregate, over the 64-day cycle) evenly distributed different numbers.
- `HomePage.vue`: on mount, computes today's number and fetches it via the existing
  `fetchHexagram()`, then shows its line diagram, symbol, King Wen number, Chinese name, and
  pinyin in a "Hexagram of the Day" card linking to `/hexagrams/{number}`.

## Out of scope

- **Persisting which hexagram was "the" hexagram of the day** (e.g. so a user could look up what
  it was last Tuesday). The computation is a pure function of the date — recomputing it for any
  past date already reproduces the exact same answer, so there's nothing to store.
- **A server endpoint for "today's hexagram."** The mapping needs no server-side state or
  randomness source; computing it in the browser and fetching the resulting hexagram through the
  existing `GET /api/hexagrams/{id}` is sufficient and matches this app's general preference for
  client-side computation over data already fully available (SPEC-022's tag filter, SPEC-026's
  search, and others already established this pattern repeatedly).
- **Any notion of "streak" or "seen today's hexagram" tracking.** No account/session concept
  exists to track that against (matches the reasoning SPEC-029 already gave for why this app adds
  no new access-control state).
- **Changing which hexagram appears mid-session if the browser's clock crosses local midnight
  while the page stays open.** The computation happens once, in `onMounted()`, matching how every
  other "loaded once" page in this app already behaves (nothing here re-polls on a timer).

## User behavior

```
/ (home page), any time on 2026-08-21
  -> "Hexagram of the Day" card: <line diagram> 29. 坎 (Kǎn), linking to /hexagrams/29
  -> reloading the page the same day shows the same hexagram
/ on 2026-08-22
  -> a different hexagram (whichever day-count-mod-64 lands on)
```

## Functional requirements

- **REQ-HOTD-001** — `hexagramOfTheDayNumber(date)` MUST return the same King Wen number
  (1-64) for any two `Date` values that fall on the same local calendar day, and MUST be a pure
  function (no I/O, no randomness).
- **REQ-HOTD-002** — `hexagramOfTheDayNumber(date)` MUST be able to (and, in the common case
  across a 64-day span, does) return a different number for a different calendar day.
- **REQ-HOTD-003** — `HomePage` MUST render the computed hexagram's line diagram, King Wen
  number, Chinese name, and pinyin, linking to its detail page.
- **REQ-HOTD-004** — `HomePage` MUST show a loading state while the fetch is in flight and an
  inline error message if it fails, without breaking the rest of the home page's existing content
  (the "Cast a new consultation" / "View history" links).

## Non-functional requirements

- **REQ-HOTD-005** — No new API endpoint, no new database table, no new dependency.

## Data requirements

None.

## API requirements

None — reuses the existing `GET /api/hexagrams/{id}`.

## Edge cases

- Two different browsers in two different timezones, same UTC instant → may show different
  hexagrams if they're on different local calendar dates at that instant. Expected and accepted:
  "today" is inherently timezone-relative, matching how every other date-grouping feature in this
  app (SPEC-022, SPEC-030) already treats "today."
- The 64-day cycle repeating means the same hexagram recurs roughly every two months — not
  avoided or special-cased; the plan asked for a daily pick, not a non-repeating shuffle.

## Acceptance criteria

- [x] `hexagramOfTheDayNumber()` returns the same number for two `Date`s on the same local day,
      and (checked across a 64-day span) covers a real spread of different numbers.
- [x] `HomePage` shows the computed hexagram's diagram, number, name, and pinyin, linking to its
      detail page.
- [x] Loading and error states render correctly without breaking the rest of the home page.
- [x] `npm run verify` passes end to end.
- [x] Manually verified against the real running API/UI.
