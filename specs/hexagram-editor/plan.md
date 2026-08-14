# Plan — Visual Hexagram Editor (SPEC-016)

**Depends on spec status:** `approved`

## Technical approach

- `HexagramController::fromLines(Request $request): Response`:
  - Read `lines` query param (`$request->query->get('lines')`), split on `,`.
  - Reject (422) if not exactly 6 entries, or any entry isn't `yin`/`yang` — reuses the same
    validation shape as `ConsultationController::parseManualLines()`, but as its own private
    method (`parseLinesFromQuery`) rather than a shared cross-controller helper — matches the
    established precedent (SPEC-003's `trigramToJson()` decision) of not introducing a
    controller-to-controller dependency for a small, module-local parsing routine.
  - Build `list<Line>` (`new Line($index + 1, $polarity, false)` — `changing` always `false`,
    irrelevant here) and call `Hexagram::fromLines($lines)`.
  - Return via the *existing* `toJson()` (same method `index()`/`show()` already use) — so
    `relationships` comes along automatically, zero new serialization code.
- Route: `GET /api/hexagrams/from-lines`, registered before `GET /api/hexagrams/{id}` in
  `routes.php` for readability (FastRoute's `GroupCountBased` dispatcher checks static routes via
  an exact-match table before falling through to `{id}`'s dynamic regex, so registration order
  doesn't actually affect matching — confirmed by reading `vendor/nikic/fast-route`'s dispatcher —
  but grouping it next to the other two hexagram routes keeps the file readable).
- `entities/hexagram/api.ts`: `computeHexagramFromLines(polarities: LinePolarity[]):
  Promise<Hexagram>` — builds the query string and calls `apiGet`.
- `HexagramEditorPage.vue`: `ref<LinePolarity[]>` (6 entries, bottom to top, default all
  `'yang'`), a `computed`-derived hexagram-fetch state machine (`loading`/`error`/`loaded`,
  mirroring every other page's established pattern), a `watch()` over the polarities array
  (`{ immediate: true, deep: true }`) driving the fetch — same reactive-fetch shape SPEC-015 just
  established for `HexagramDetailPage`.

## Architecture decisions

- **Query string, not a POST body.** This is a pure, idempotent, side-effect-free computation —
  a `GET` with a query param matches the rest of this API's read-endpoint conventions
  (`GET /api/hexagrams/{id}`) better than a `POST` with no persistence semantics.
- **No `changing` flag in the request/response.** Per spec's "Out of scope" — this tool is about
  hexagram *identity*, not casting; changing lines stay a casting-flow-only concept.
- **No debouncing on the frontend.** Six discrete toggle inputs, each triggering one cheap
  (no-I/O-besides-HTTP) request; no rapid-fire text input involved, so debouncing would add
  complexity without a real problem to solve.

## Affected areas

- `apps/api/src/Hexagrams/HexagramController.php`
- `apps/api/config/routes.php`
- `apps/api/tests/Hexagrams/HexagramControllerTest.php`
- `apps/web/src/entities/hexagram/api.ts`
- `apps/web/src/entities/hexagram/api.spec.ts`
- `apps/web/src/pages/hexagrams/HexagramEditorPage.vue` (new)
- `apps/web/src/pages/hexagrams/HexagramEditorPage.spec.ts` (new)
- `apps/web/src/pages/hexagrams/HexagramListPage.vue` (add a link)
- `apps/web/src/router` (new route)

## Data / schema changes

None.

## Risks / open questions

- None currently open.
