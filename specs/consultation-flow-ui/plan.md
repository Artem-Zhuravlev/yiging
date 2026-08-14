# Plan — Consultation Flow UI (SPEC-009)

**Depends on spec status:** `approved`

## Technical approach

```
apps/web/src/
├── entities/consultation/
│   ├── model.ts                       Consultation, ConsultationNote, CastingMethod types
│   └── api.ts                         createConsultation(), fetchConsultations(),
│                                       fetchConsultation(id)
├── pages/consultations/
│   ├── NewConsultationPage.vue         /consultations/new
│   ├── ConsultationPage.vue            /consultations/:id
│   └── ConsultationHistoryPage.vue     /consultations
├── App.vue                              + nav links
├── pages/home/HomePage.vue              + 2 links
└── router/index.ts                      + 3 routes
```

- `entities/consultation/model.ts`: `CastingMethod = 'three_coins' | 'manual'` (deliberately
  excludes `'random'` at the type level, not just the UI, per REQ-CONSUI-010 — a caller
  literally cannot construct a request for it through this module). A separate `ManualLine`
  type (`{ polarity: 'yin' | 'yang'; changing: boolean }`) for the request payload.
- `entities/consultation/api.ts`: `createConsultation(payload: NewConsultationRequest):
  Promise<Consultation>` wraps a `POST` (no existing `apiPost` helper in `shared/api` — added
  here since this is its first use; `apiGet` already established the pattern to follow: same
  base-URL resolution, same `ApiError` on non-2xx).
- `NewConsultationPage.vue`: local `ref` for question/method/lines (6-element array, seeded
  with yang/stable defaults so REQ-CONSUI-002's "no way to submit fewer/more" holds by
  construction — the array length never changes, only its entries' values). On submit, builds
  the right payload shape per method, calls `createConsultation()`, and on success
  `router.push(`/consultations/${result.id}`)`; on `ApiError`, stores the message in a local
  `error` ref rendered inline, leaving all inputs as they were (no reset).
- `ConsultationPage.vue` / `ConsultationHistoryPage.vue`: same discriminated-state pattern as
  `HexagramDetailPage.vue`/`HexagramListPage.vue` from SPEC-007
  (`loading | not-found | error | loaded`, list version drops `not-found`), for consistency
  across the codebase rather than inventing a second convention.
- **Diagram data assembly (`ConsultationPage` only):** the consultation response only embeds a
  hexagram *summary* (`kingWenNumber`/`chineseName`/`pinyin` —
  `ConsultationController::hexagramToJson()`, SPEC-006), not lines. After
  `fetchConsultation(id)` resolves, the page calls `entities/hexagram`'s
  `fetchHexagram(primaryHexagram.kingWenNumber)` and `fetchHexagram(resultingHexagram.
  kingWenNumber)` (SPEC-007's existing endpoint, no backend change) to get each hexagram's 6
  lines, then overlays `changing: true` onto the primary hexagram's lines at the positions
  listed in `consultation.changingLinePositions` before handing the array to `HexagramLines`.
  The resulting hexagram's lines are passed through with no overlay (never changing, per the
  domain model). `ConsultationHistoryPage` skips this entirely — text summary only, to avoid an
  N-consultation fan-out of extra requests on one page.
- `entities/hexagram/model.ts`'s `HexagramLine` gains an optional `changing?: boolean`;
  `HexagramLines.vue` renders a small marker (e.g. a dot) on lines where it's `true`. Additive —
  SPEC-007's existing callers never set it, so their rendering is unchanged.

## Architecture decisions

- **`shared/api` gets `apiPost<T>()` alongside `apiGet<T>()`**, not a one-off `fetch` call
  inside `entities/consultation`. Keeps the "only `shared/api` calls `fetch`" rule (established
  in SPEC-007, REQ-HEXUI-007's non-UI-specific twin) intact now that there's a second HTTP verb
  in play.
- **`random` excluded at the type level (`CastingMethod` type), not just hidden in the UI.**
  Making the exclusion structural (not just "the dropdown doesn't list it") means no future
  page can accidentally wire it up through `entities/consultation` without a deliberate type
  change — the same "make the invalid state unrepresentable" instinct SPEC-004's `Coin`/`match`
  exhaustiveness already uses on the backend.
- **No notes/tags editing UI.** Matches SPEC-006's explicit deferral. Displaying empty
  notes/tags now (they're always empty right after creation) costs nothing; building edit UI
  for an endpoint that doesn't exist would.
- **Reusing `HexagramLines.vue` as-is**, passing `hexagram.lines` straight through — no new
  "compact" variant for the history cards. If the full-size diagram turns out too large for a
  card once this is visually reviewed, that's a follow-up CSS-sizing tweak, not a new component.

## Affected areas

- `apps/web/src/shared/api/http.ts` (+ `apiPost`, + test)
- `apps/web/src/entities/hexagram/model.ts` (`HexagramLine.changing?: boolean`)
- `apps/web/src/entities/hexagram/ui/HexagramLines.vue` (+ test for the changing marker)
- `apps/web/src/entities/consultation/model.ts`
- `apps/web/src/entities/consultation/api.ts` (+ test)
- `apps/web/src/pages/consultations/NewConsultationPage.vue` (+ test)
- `apps/web/src/pages/consultations/ConsultationPage.vue` (+ test)
- `apps/web/src/pages/consultations/ConsultationHistoryPage.vue` (+ test)
- `apps/web/src/App.vue` (nav links)
- `apps/web/src/pages/home/HomePage.vue` (2 links)
- `apps/web/src/router/index.ts` (3 routes)

## Data / schema changes

None.

## Risks / open questions

- None currently open. Double-submit guarding and notes/tags editing are both named explicitly
  as deferred (see spec.md "Out of scope"/"Edge cases"), not silently dropped.
