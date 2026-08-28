# SPEC-045 — Home Dashboard

**Status:** implemented
**Owner:** unassigned
**Last updated:** 2026-08-28

## Problem

The home page is a splash screen: the site name, a tagline, two buttons, and the
Hexagram of the Day. For a returning practitioner it's a dead end — nothing about *their*
practice. To get back to a recent reading they have to go to History and scroll; to see how
much they've cast they have to open Statistics.

## Purpose

Turn the home page into a light dashboard: keep the Hexagram of the Day, and add a short list
of recent consultations and a one-line practice summary, each loading independently so a slow
or failed section never blocks the rest.

## Scope

- Keep the existing header (title, tagline), the two primary buttons ("Cast a new
  consultation" / "View history"), and the Hexagram of the Day card exactly as they are.
- **Recent consultations**: `fetchConsultations({ limit: 4 })` → a compact list, each row a
  `router-link` to `/consultations/{id}` showing the question, the primary→resulting hexagram
  pair (numbers + Chinese names), and the local date. A "View all" link to `/consultations`
  below. Rendered only when there is at least one; on an empty history the section is omitted
  (the "Cast a new consultation" button already covers that case).
- **At a glance**: `fetchStatistics()` → a single line, "{n} consultations cast", linking to
  `/statistics`. Shown only when `totalConsultations > 0`.
- Each of the three data sections (Hexagram of the Day, Recent, At a glance) has its own
  independent load state; a rejected fetch for one shows nothing for that section (or, for the
  Hexagram of the Day, the existing inline error) and leaves the others untouched.
- The page stays centered and calm — the new sections sit below the buttons/HOTD in a single
  narrow column, not a multi-column grid.
- Localised (en + uk): `home.recent`, `home.viewAll`, `home.consultationsCast` (with a
  `{count}` param), plus any row labels. Reuse `consultation.*` / existing keys where they fit.
- The `useStatusAnnouncer` wiring from SPEC-039 stays; it tracks the Hexagram of the Day load
  as today (the added sections are secondary and need not be announced).

## Out of scope

- **A favorites row, journal stats, streaks, activity heatmap, "resume where you left off".**
  Each is a reasonable later addition; this spec is recent + count only.
- **A new API endpoint or any change to `Statistics` / the consultations list shape.**
- **Configurable / reorderable dashboard sections.**
- **Changing the Hexagram of the Day logic** (SPEC-032) or the two primary buttons.
- **Server-side aggregation of a "recent" list** — the paginated list endpoint with `limit: 4`
  already is exactly that.

## Functional requirements

- **REQ-HOME-001** — The home page shows up to 4 most-recent consultations as links to their
  detail pages, with question + hexagram pair + date, plus a "View all" link — only when the
  history is non-empty.
- **REQ-HOME-002** — The home page shows a "{n} consultations cast" line linking to
  `/statistics`, only when `n > 0`.
- **REQ-HOME-003** — The Hexagram of the Day, Recent, and At-a-glance sections load
  independently; one failing or pending does not block the others or the static content.
- **REQ-HOME-004** — On a brand-new install (no consultations) the page looks like it does
  today apart from the Hexagram of the Day: title, tagline, two buttons, HOTD, and neither new
  section.

## Non-functional requirements

- **REQ-HOME-020** — New strings localised (en + uk).
- **REQ-HOME-021** — No layout regression: single narrow centered column, works at mobile
  width, light and dark.
- **REQ-HOME-022** — `npm run verify` passes; `HomePage.spec.ts` updated for the new sections
  (the existing HOTD / error tests still pass, with the two new fetches mocked).

## Data requirements

None new. Consumes `GET /api/consultations?limit=4` and `GET /api/statistics`.

## API requirements

None new.

## Edge cases

- Empty history → Recent and At-a-glance both omitted; page equals today's minus nothing.
- `fetchConsultations` rejects but `fetchStatistics` resolves → Recent omitted, At-a-glance
  shown, HOTD unaffected.
- `fetchStatistics` rejects → At-a-glance omitted silently (it's a nicety, not worth an error
  box on the landing page).
- Exactly 1 consultation → Recent shows the one row; At-a-glance says "1 consultations cast"
  (the existing `statistics.consultationsCount` copy already handles the number; a dedicated
  `home.consultationsCast` string may pluralise or not — keep it simple, "{count} consultations
  cast", matching the app's existing non-pluralised style elsewhere).
- A very long question in a Recent row → truncates / wraps within the column, no overflow.

## Acceptance criteria

- [x] With history, the home page lists the 4 newest consultations (linked) and a "{n}
      consultations cast" line to Statistics — verified live ("Останні" list of 4 →
      `/consultations/{id}`, "Переглянути всі" → `/consultations`, "Кинуто консультацій: 17" →
      `/statistics`) + `HomePage.spec.ts`.
- [x] With no history the page is the plain splash apart from the Hexagram of the Day —
      `HomePage.spec.ts` ("is the plain splash …").
- [x] Each section loads independently — `HomePage.spec.ts` ("keeps the count line and hexagram
      of the day when the recent fetch fails"); all three fetches are fired unawaited with their
      own `.catch`.
- [x] Layout is a single narrow centered column; the new sections are `max-width: 24rem`,
      left-aligned within the centered page — checked in the browser (mobile-width pane, dark).
- [x] `npm run verify` passes (web 188 tests, api 312, yijing-core 55).

## Implementation note (2026-08-28)

- `HomePage.vue`: `recent = ref<ConsultationListItem[]>([])` and `totalCast = ref<number | null>(null)`;
  `onMounted` fires `fetchHexagram` / `fetchConsultations({ limit: 4 })` / `fetchStatistics()`
  without awaiting, each with its own handler (`.catch(() => {})` for the two secondary ones so
  a failure just hides that section). `totalCast` is only set when `totalConsultations > 0`.
- Template: a "Recent" `<section v-if="recent.length">` of `router-link` cards (question /
  primary→resulting pair / date) + a "View all" link, and a `<p v-if="totalCast !== null">`
  count line linking to `/statistics`, both below the existing header / buttons / HOTD card.
  i18n: `home.recent`, `home.viewAll`, `home.consultationsCast`.
