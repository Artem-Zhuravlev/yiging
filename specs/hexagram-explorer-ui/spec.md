# SPEC-007 — Hexagram Explorer UI

**Status:** verified
**Owner:** unassigned
**Last updated:** 2026-08-14

## Problem

The backend API surface is complete (SPEC-003/004/005/006) but nothing in `apps/web` calls it —
`apps/web` is still the bootstrap placeholder page from SPEC-001. There is also no established
pattern yet for how the frontend talks to the API at all (base URL, dev-time cross-origin
story, typed responses), which every future page (consultation flow, history) will also need.

## Purpose

Establish the frontend's API-consumption foundation and build the first real page — the
Hexagram Explorer (`/hexagrams`, `/hexagrams/{number}`) — as the vertical slice that proves the
foundation actually works end to end. Chosen first (over the consultation flow) because it's
read-only, has no multi-step UX, and maps directly onto SPEC-003's already-verified API.

## Scope

- API client foundation: a small typed `fetch` wrapper (`shared/api`), a base URL resolved from
  `VITE_API_BASE_URL` (default `/api`, a relative path), and a Vite dev-server proxy so
  `npm run dev` (port 5173) can reach the PHP dev server (port 8000) same-origin without CORS.
- `entities/hexagram`: TypeScript types matching SPEC-003's JSON shape, `fetchHexagrams()` /
  `fetchHexagram(number)`, and a presentational component that renders a hexagram's 6 lines
  (solid bar = yang, broken bar = yin) — reusable wherever a hexagram needs to be drawn, not
  just this page.
- `pages/hexagrams`: `HexagramListPage.vue` (`/hexagrams`, grid of all 64) and
  `HexagramDetailPage.vue` (`/hexagrams/:number`, single hexagram: lines, upper/lower trigram,
  judgment/image/line-statement text — `null` today per SPEC-002, rendered as "not yet
  available" rather than blank).
- A minimal nav shell in `App.vue` (Home / Hexagrams links) — the first piece of persistent
  layout, needed the moment there's a second page to link to.
- Router entries for both new routes.

## Out of scope

- The consultation flow (`/consultations/new`, `/consultations`, `/consultations/:id`) —
  SPEC-00x, a separate, larger effort (multi-step casting UX, notes, tags).
- `/api/trigrams` consumption / a standalone trigrams page — not in the plan's page list
  (only `/hexagrams` and `/hexagrams/:id` are); trigram detail already appears nested inside a
  hexagram's upper/lower trigram display, which is enough for now.
- Cross-origin production deployments (API on a different domain than the frontend) — the
  `VITE_API_BASE_URL` env var already supports overriding to an absolute URL if that's chosen
  later, but the API has no CORS headers yet, so a cross-origin deployment wouldn't work
  out of the box until a future spec adds them. Same-origin (the default, and what
  `docs/deployment.md`'s Nginx/Apache examples assume) works today.
- State management (Pinia store) for hexagram data — each page fetches what it needs directly;
  a shared cache/store is premature until something actually needs to share this data across
  routes (e.g. the consultation flow showing a resulting hexagram).
- Loading skeletons/animations, pagination, search/filter on the list — the list is a fixed 64
  items, small enough to render whole; polish is not this spec's job.

## User behavior

```
Visit /hexagrams
  -> fetches GET /api/hexagrams, renders 64 cards (number, name, symbolic line pattern)
  -> each card links to /hexagrams/{number}

Visit /hexagrams/23
  -> fetches GET /api/hexagrams/23, renders full detail: 6 lines (bottom to top), upper/lower
     trigram (name + symbol), judgment/image ("not yet available" placeholder since null)

Visit /hexagrams/999 (or any id GET /api/hexagrams/{id} 404s for)
  -> page shows a "hexagram not found" state, not a crash or blank screen

API unreachable (dev server down, network error)
  -> page shows an error state, not an infinite spinner or a silent blank page
```

## Functional requirements

- **REQ-HEXUI-001** — `shared/api`'s `apiGet<T>(path)` MUST prefix `path` with the resolved
  base URL (`import.meta.env.VITE_API_BASE_URL`, defaulting to `/api`), parse the JSON
  response, and throw a typed error (including the HTTP status) on a non-2xx response.
- **REQ-HEXUI-002** — `HexagramListPage` MUST render all hexagrams returned by
  `fetchHexagrams()`, each showing at minimum its King Wen number, Chinese name, and line
  pattern, and each linking to its detail route.
- **REQ-HEXUI-003** — `HexagramDetailPage` MUST render the hexagram's 6 lines bottom-to-top
  (position 1 at the bottom, position 6 at the top — matching the domain model's own
  convention, not reversed for "visual convenience"), its upper and lower trigram, and
  judgment/image text — rendering an explicit "not yet available" placeholder when those
  fields are `null`, never an empty string or missing element.
- **REQ-HEXUI-004** — `HexagramDetailPage` MUST show a distinct "not found" state when the API
  responds `404`, and a distinct error state for any other failure (network error, `5xx`) —
  never leave the page in a permanent loading state or throw an unhandled error to the console
  only.
- **REQ-HEXUI-005** — The hexagram line-rendering component MUST visually distinguish yang
  (solid) from yin (broken/gapped) lines and MUST be reusable (not hardcoded to the detail
  page's layout), since the consultation flow will need to draw hexagrams too.

## Non-functional requirements

- **REQ-HEXUI-006** — `npm run dev` MUST be able to reach the API without CORS errors when the
  PHP dev server is running on its documented port (`composer serve`, `127.0.0.1:8000`) — via a
  Vite dev-server proxy, not by adding CORS headers to `apps/api` (out of scope here).
- **REQ-HEXUI-007** — No component may call `fetch`/`apiGet` directly for hexagram data —
  only `entities/hexagram`'s functions do, per the feature-sliced layering rule in
  `docs/coding-rules.md` (`pages → widgets → features → entities → shared`, no reaching past a
  layer).
- **REQ-HEXUI-008** — No business/domain logic (e.g. deciding yin vs. yang, formatting a King
  Wen number) may live in a `.vue` component's `<script>` beyond what's needed to bind fetched
  data to the template — the API already returns fully-resolved values.

## Data requirements

None — no new persistence; this reads the existing SPEC-003 API.

## API requirements

Consumes SPEC-003's `GET /api/hexagrams` and `GET /api/hexagrams/{number}` as-is; no backend
changes.

## Edge cases

- `VITE_API_BASE_URL` unset → defaults to `/api` (works via the dev proxy locally, and via a
  same-origin reverse proxy in the documented production deployments).
- Hexagram detail for a number with all lines the same polarity (1 or 2) → renders correctly,
  no special-casing (mirrors the domain model's own "no special-casing for uniform hexagrams"
  invariant from SPEC-002).
- `judgment`/`image`/`lineStatements` all `null` (true for every hexagram today) → every
  hexagram's detail page shows the same "not yet available" placeholder, not a broken layout.

## Acceptance criteria

- [x] `npm run dev` + `composer serve` together: visiting `/hexagrams` in a browser shows all
      64 hexagrams fetched from the real running API (manually verified, not just unit-tested).
- [x] `HexagramListPage` and `HexagramDetailPage` have component tests covering: successful
      render, 404/not-found state, and a generic error state (fetch mocked in tests — no test
      hits a real server).
- [x] `shared/api`'s `apiGet()` has a unit test covering both success and non-2xx-throws cases.
- [x] Hexagram line rendering visually distinguishes yang/yin and orders lines bottom-to-top.
- [x] `npm run lint`, `npm run typecheck`, `npm run test`, `npm run build` all pass (i.e.
      `npm run verify` passes end to end, web + api + yijing-core).
- [x] No component outside `entities/hexagram` imports `shared/api` directly for hexagram data.

Implemented: `shared/api/http.ts`, `entities/hexagram` (model/api/`HexagramLines.vue`),
`pages/hexagrams` (list + detail), a nav shell in `App.vue`, a Vite dev proxy for `/api`, and
`.env.example`. 13 new frontend tests; `npm run verify` passes end to end (web + api +
yijing-core). Manually verified against the real API in the browser preview: `/hexagrams` (all
64 render, no console errors), `/hexagrams/23` (full detail with correct placeholders for the
still-null classical text), and `/hexagrams/999` (graceful "Hexagram not found" state, not a
crash).

**Found and fixed along the way:** `ApiError`'s constructor originally used TypeScript
parameter-property shorthand (`constructor(public readonly status: number, ...)`), which fails
under this project's `erasableSyntaxOnly` tsconfig flag (already enabled, unrelated to this
spec) — `vue-tsc` caught it immediately. Fixed with an explicit field + assignment.
