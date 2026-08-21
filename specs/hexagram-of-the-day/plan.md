# Plan — Hexagram of the Day (SPEC-032)

**Depends on spec status:** `approved`

## Technical approach

- `apps/web/src/entities/hexagram/hexagramOfTheDay.ts` (new): `hexagramOfTheDayNumber(date: Date
  = new Date()): number` — `Date.UTC(date.getFullYear(), date.getMonth(), date.getDate()) /
  86_400_000` (days-since-epoch, computed from the date's *local* year/month/day so the boundary
  is local midnight, not UTC midnight) → `Math.floor(...) % 64 + 1`.
- `HomePage.vue`: a `State` union (`loading`/`error`/`loaded`, this app's standard shape),
  `onMounted()` calls `fetchHexagram(hexagramOfTheDayNumber())`, renders a card with
  `HexagramLines`, the number, Chinese name, and pinyin, linking to `/hexagrams/{number}` — the
  existing "Cast a new consultation" / "View history" links stay exactly where they are, the new
  card is additive.

## Architecture decisions

- **A small standalone function file, not a method tacked onto `entities/hexagram/api.ts`.**
  `hexagramOfTheDayNumber()` does no I/O — bundling a pure function into the file that owns every
  network call would blur "what actually talks to the server" in that file at a glance.
- **Local calendar day via `getFullYear()`/`getMonth()`/`getDate()`, not `date.getTime()`
  directly.** Matches SPEC-022/030's existing local-day reasoning; using the raw timestamp would
  make the "day" boundary fall at UTC midnight regardless of the visitor's own timezone.

## Affected areas

- `apps/web/src/entities/hexagram/hexagramOfTheDay.ts` (new)
- `apps/web/src/entities/hexagram/hexagramOfTheDay.spec.ts` (new)
- `apps/web/src/pages/home/HomePage.vue`
- `apps/web/src/pages/home/HomePage.spec.ts` (extended — a single "renders the project title"
  test already existed)

## Data / schema changes

None.

## Risks / open questions

- None currently open.
