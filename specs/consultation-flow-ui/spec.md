# SPEC-009 — Consultation Flow UI

**Status:** verified
**Owner:** unassigned
**Last updated:** 2026-08-14

## Problem

SPEC-006 exposed casting + persistence over HTTP; SPEC-007 proved the frontend can consume that
API on a simple read-only page. But there is still no way for a person to actually ask a
question, cast a hexagram, and see or revisit the result — the entire point of the
application. This is the largest remaining piece of the plan's Phase 7 (Frontend).

## Purpose

Build the three pages the plan's flow requires: `/consultations/new` (ask + cast), `/consultations/:id`
(view one), `/consultations` (history) — completing the loop from question to a saved,
revisitable reading.

## Scope

- `entities/consultation`: types matching SPEC-006's JSON shape, plus `createConsultation()`,
  `fetchConsultations()`, `fetchConsultation(id)`.
- `pages/consultations/NewConsultationPage.vue` (`/consultations/new`): question input, method
  choice (**Three Coins** or **Manual** only — see "Out of scope" for why `random` is excluded),
  a 6-line editor when Manual is chosen, submit → `POST /api/consultations` → on success,
  navigate to the new consultation's detail page.
- `pages/consultations/ConsultationPage.vue` (`/consultations/:id`): question, method,
  primary hexagram (full line diagram, reusing SPEC-007's `HexagramLines` — see "Data
  requirements" for how the diagram data is actually assembled, since the consultation
  response itself only carries a hexagram *summary*), changing line positions, resulting
  hexagram (same), createdAt, notes, tags. Distinct not-found/error states, matching
  `HexagramDetailPage`'s established pattern.
- `pages/consultations/ConsultationHistoryPage.vue` (`/consultations`): all consultations,
  newest-first (as the API already orders them), each a compact text card (number + Chinese
  name for primary → resulting, no line diagram — see "Data requirements") linking to its
  detail page; a distinct empty state ("no consultations yet") when there are none.
- Nav + router entries for all three; two new links on the home page ("Cast a new consultation"
  / "View history") — not a full analytics dashboard, just enough that the home page isn't a
  dead end.

## Out of scope

- **`random` as a selectable method.** SPEC-004 explicitly documents `RandomMethod` as
  "non-traditional... never present this method's output as a doctrinally accurate casting" —
  a real UI offering it to end users would do exactly that. It stays reachable only via the API
  directly (dev/test), same as today.
- **Editing notes/tags after creation.** SPEC-006 has no `PATCH /api/consultations/{id}` yet
  (deliberately deferred "until there's a concrete UI need"). This spec displays notes/tags
  (always empty right after creation today) but adds no editing UI — that's the next backend
  spec's job once this UI makes the need concrete, not this one's.
- **AI interpretation** — SPEC-008, unstarted, unrelated to this spec.
- **A real Dashboard at `/`** — plan section 22 calls it "Dashboard," but there's no
  analytics/summary data worth dashboarding yet (that's plan Phase 9). Two links is enough for
  now; building more would be speculative.
- **Search/filter/pagination on history** — plan section 24, deferred until the list is large
  enough to need it (mirrors SPEC-003's own "not needed yet" call on the API side).
- **Yarrow-stalk or other casting methods** — none exist yet in `App\Casting` beyond the three
  SPEC-004 already built; nothing to expose.

## User behavior

```
Visit /consultations/new
  -> enters a question, picks "Three Coins" (default) or "Manual"
  -> if Manual: sets each of 6 lines' polarity (yin/yang) and changing flag
  -> submits -> POST /api/consultations -> on 201, navigates to /consultations/{new id}
  -> on 422 (e.g. empty question): shows the API's error message inline, form stays filled in

Visit /consultations/{id}
  -> shows question, method, primary hexagram (with line diagram), which lines were changing,
     resulting hexagram, when it was cast, any notes/tags
  -> if no lines were changing: shows "No changing lines" instead of a confusing second
     identical-looking diagram
  -> unknown id -> "Consultation not found"

Visit /consultations
  -> lists every saved consultation, newest first, each linking to its detail page
  -> zero consultations -> "No consultations yet" with a link to cast the first one
```

## Functional requirements

- **REQ-CONSUI-001** — `NewConsultationPage` MUST let the user enter a question and choose
  between exactly two casting methods: Three Coins (default) and Manual.
- **REQ-CONSUI-002** — When Manual is selected, the page MUST present exactly 6 line editors
  (position 1, bottom, through position 6, top), each independently set to yin/yang and
  changing/stable, defaulting to yang/stable. The 6-line structure MUST be fixed by the UI
  itself (no way to submit fewer/more), not merely validated after the fact.
- **REQ-CONSUI-003** — On submit, the page MUST `POST /api/consultations` with `{question,
  method}` (Three Coins) or `{question, method, lines}` (Manual, 6 entries), and on a `201`
  response MUST navigate to `/consultations/{id}` using the id from the response body.
- **REQ-CONSUI-004** — On a `422` response, the page MUST show the API's `error` message
  inline and MUST preserve the user's entered question/method/lines so they can correct and
  resubmit without starting over.
- **REQ-CONSUI-005** — `ConsultationPage` MUST render: question, method, primary hexagram
  (number, name, line diagram with changing lines marked), which line positions were changing,
  resulting hexagram (same detail, no lines marked changing), createdAt, notes (label + text
  each), and tags — and MUST show a distinct message when there are no changing lines, rather
  than rendering two identical-looking diagrams with no explanation. Fetching the two
  diagrams' line data is a secondary step after the consultation itself loads (see "Data
  requirements") — the page's loading state covers both, not just the initial fetch.
- **REQ-CONSUI-006** — `ConsultationPage` MUST show a distinct "not found" state for a `404` on
  the consultation fetch and a distinct generic error state for any other failure (including a
  failure fetching either hexagram's line data), matching `HexagramDetailPage`'s established
  pattern (SPEC-007) — never an infinite loading state.
- **REQ-CONSUI-007** — `ConsultationHistoryPage` MUST render every consultation from
  `fetchConsultations()` (already newest-first per SPEC-005 REQ-READ-009) as a text summary
  (King Wen number + Chinese name for primary and resulting hexagram — no line diagram, see
  "Data requirements"), each linking to its detail page, and MUST show a distinct empty state
  when the list is empty.

## Non-functional requirements

- **REQ-CONSUI-008** — No component outside `entities/consultation` may call `apiGet`/`fetch`
  directly for consultation data (mirrors REQ-HEXUI-007 from SPEC-007).
- **REQ-CONSUI-009** — Wherever this spec renders a hexagram line diagram (`ConsultationPage`'s
  primary/resulting hexagrams), it MUST reuse SPEC-007's `entities/hexagram/ui/HexagramLines.vue`
  — no second implementation of line rendering.
- **REQ-CONSUI-010** — The method selector MUST NOT offer `random` as a choice (see "Out of
  scope").

## Data requirements

None — no new persistence; consumes SPEC-006's existing API as-is.

**How hexagram diagrams get their line data:** SPEC-006's `Consultation` JSON only embeds a
*hexagram summary* (`kingWenNumber`, `chineseName`, `pinyin` — see
`ConsultationController::hexagramToJson()`), not the 6-line structure `HexagramLines.vue`
needs. `ConsultationPage` gets that by calling SPEC-007's `entities/hexagram`'s
`fetchHexagram(kingWenNumber)` for both the primary and resulting hexagram (2 extra requests,
reusing the existing `GET /api/hexagrams/{number}` endpoint — no backend change), then marks
the primary hexagram's lines at `changingLinePositions` as changing before rendering.
`entities/hexagram/ui/HexagramLines.vue` gains an optional `changing` field on its line type to
render that (a small visual marker) — additive, doesn't break SPEC-007's existing usage where
`changing` is always absent. `ConsultationHistoryPage`'s cards deliberately skip this (text
summary only) to avoid an N-consultation fan-out of extra hexagram-detail requests on one page.

## API requirements

Consumes SPEC-006's `POST /api/consultations`, `GET /api/consultations`,
`GET /api/consultations/{id}` as-is; no backend changes.

## Edge cases

- Manual method with all 6 lines left at their yang/stable defaults → still a valid submission
  (Hexagram 1, no changes) — the UI must not require the user to "touch" every line.
- A consultation with all 6 lines changing → resulting hexagram is the complement of the
  primary (falls out of the domain model, SPEC-002/004/005); the UI renders it the same way as
  any other resulting hexagram, no special-casing needed here either.
- Very long questions → input is a plain text field with no artificial length cap in the UI
  (the API/domain layer doesn't impose one either); wrapping is a CSS concern, not a validation
  one.
- Rapid double-submit of the New Consultation form → out of scope to prevent here (would need a
  submit-disable-while-pending guard); noted as a natural follow-up, not silently ignored.

## Acceptance criteria

- [x] Casting via Three Coins from `/consultations/new` creates a consultation and navigates to
      its detail page (manually verified against the real running API).
- [x] Casting via Manual with a chosen 6-line pattern creates the exact expected hexagram
      (manually verified: all-yang + line 1 changing → Hexagram 1 → Hexagram 44, matching the
      domain model exactly).
- [x] Submitting an empty question shows the API's validation error inline without navigating
      away or losing the form's other values.
- [x] `ConsultationPage` correctly renders a real consultation's full detail, including a
      distinct "no changing lines" state when applicable.
- [x] `ConsultationPage` shows "not found" for an unknown id.
- [x] `ConsultationHistoryPage` lists real consultations newest-first and shows an empty state
      when there are none.
- [x] Component tests (fetch mocked) cover: successful creation + navigation, `422` validation
      display, detail render, not-found, history render, and history empty state.
- [x] `npm run verify` passes end to end (web + api + yijing-core).
- [x] No component outside `entities/consultation` imports `shared/api` directly for
      consultation data.

Implemented: `apiPost<T>()` on `shared/api/http.ts`, `entities/consultation` (model/api),
`entities/hexagram`'s `HexagramLine`/`HexagramLines.vue` extended with an optional `changing`
marker, and all 3 pages (`NewConsultationPage`, `ConsultationPage`, `ConsultationHistoryPage`),
plus nav/home-page links and 3 new routes. 11 new frontend tests (30 total in `apps/web`);
`npm run verify` passes end to end. Manually verified against the real running API in the
browser preview: Three Coins casting (question → cast → correct navigation to
`/consultations/{uuid}`), Manual casting (all-yang + line 1 changing → Hexagram 1 → 44, exactly
as the domain model predicts), History (newest-first, including a consultation from an earlier
session), `/consultations/does-not-exist` (graceful not-found), and an empty/whitespace
question (inline `422` — "A consultation question must not be empty.", the exact message from
`Consultation::create()`, SPEC-005 — with no navigation and the form preserved).

**Found and fixed along the way:** two test files reused a `vi.mock()`-provided function across
multiple `it()` blocks without clearing call counts between them, causing
`NewConsultationPage.spec.ts`'s manual-lines test to see calls from the prior test. Fixed with
`vi.mocked(createConsultation).mockClear()` in `beforeEach`.
