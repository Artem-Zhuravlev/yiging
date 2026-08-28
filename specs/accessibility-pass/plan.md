# Plan — Accessibility Pass (SPEC-039)

## Approach

Pure presentation-layer / semantics change, mirroring how SPEC-037 (PrimeVue) and SPEC-038
(i18n) were sequenced: shared plumbing first, then page-by-page in ascending order of size,
simplest first, `ConsultationPage` last.

## Affected files

### New
- `apps/web/src/shared/ui/LiveRegion.vue` — visually-hidden `aria-live="polite"` element;
  exposes an `announce(message: string)` via a tiny composable `shared/lib/useAnnouncer.ts`
  (module-level `ref`, so any page can push a message and the single region in `App.vue`
  renders it).
- `apps/web/src/shared/lib/useAnnouncer.ts` — `announce()` + the shared `ref`.
- `apps/web/src/shared/lib/useFocusMain.ts` — helper to `document.getElementById('main')?.focus()`
  on `nextTick`; used by the router hook.
- `apps/web/src/shared/ui/SkipLink.vue` — the skip link, `href="#main"`, `.sr-only`-until-focus.
- Spec test files alongside the pages touched (see below).

### Changed
- `apps/web/src/style.css` — add `.sr-only` utility (PrimeFlex has no equivalent) and its
  `:focus`/`:focus-within` reveal for the skip link. Restore a focus outline only if we find
  an `outline: none` in our own CSS (grep says we currently have none — confirm).
- `apps/web/src/App.vue` — `<SkipLink>` as first child; wrap toolbar contents in
  `<nav :aria-label="t('nav.primaryLabel')">`; mount one `<LiveRegion>`. Keep the
  `route.meta.public` branch.
- `apps/web/src/router/index.ts` — `router.afterEach()` that, when `from` is not the initial
  `START_LOCATION`, calls `useFocusMain()` on `nextTick`.
- `apps/web/src/i18n/locales/{en,uk}.ts` — `nav.primaryLabel`, `nav.skipToContent`,
  `a11y.loading`, `a11y.loaded`, `newConsultation.lineGroupLabel` ("Line {n}").
- All 12 page components — ensure top wrapper is `<main id="main" tabindex="-1">` (most already
  use `<main class="container-…">`; just add the attrs). Pages currently opening with a
  non-`<main>` element: audit during implementation (candidates: none expected, verify).
- Pages with async state machines — `HomePage`, `HexagramListPage`, `HexagramDetailPage`,
  `HexagramComparePage`, `ConsultationHistoryPage`, `ConsultationPage`, `JournalPage`,
  `StatisticsPage`, `NewConsultationPage`, `SharedConsultationPage`, `InterpretationSettingsPage`
  — call `announce()` on their loading/error/loaded watchers. Add `role="alert"` to their error
  `<Message>`.
- `NewConsultationPage.vue` — per-line `<fieldset>` with a `<legend class="sr-only">Line {n}</legend>`
  (visually the existing number `<span>` stays as-is; the legend is the SR label). `aria-busy`
  on the submit `Button` bound to `state.status === 'submitting'`.

## Sequence

1. `.sr-only` CSS + `SkipLink.vue` + `LiveRegion.vue` + `useAnnouncer` + `useFocusMain`.
2. `App.vue` wiring (`<nav>`, skip link, live region) + i18n keys. Manually verify skip link
   with keyboard, nav announced as landmark.
3. `router/index.ts` `afterEach` focus hook; verify first load does *not* focus main, a
   subsequent nav does.
4. Page sweep for `<main id="main" tabindex="-1">` — smallest pages first
   (`HomePage`, `JournalPage`, `StatisticsPage`, `HexagramEditorPage`, …), `ConsultationPage`
   last.
5. Async-state `announce()` wiring, page by page, same order.
6. `NewConsultationPage` per-line fieldsets + `aria-busy`.
7. `aria-current` verification on nav links (vue-router default — likely already emitted;
   assert in `App.spec.ts`).

## Testing

- `App.spec.ts` — skip link is first focusable; `<nav>` has an accessible name; live region
  exists; active link has `aria-current`.
- `NewConsultationPage.spec.ts` — six `<fieldset>` groups, each with a "Line N" legend;
  submit button `aria-busy` toggles with submitting state.
- One representative async page (`HomePage.spec.ts` or `HexagramDetailPage.spec.ts`) —
  `announce()` called with a loading then loaded/error message (spy on the composable).
- `router` test — `afterEach` focuses `#main` on a non-initial navigation, not on the first.
- All existing specs: run `npm run test`; fix only content assertions broken by a new
  wrapper element (structural selectors should be untouched).

## Verification note (2026-08-28)

Automated:
- `npm run verify` passes end to end (web lint/typecheck/test/build; api lint/phpstan/phpunit;
  yijing-core all). Web suite 160 → 165 tests (5 added: 2 in `App.spec.ts`, 2 in
  `HomePage.spec.ts`, 1 `router/index.spec.ts`, plus 2 in `NewConsultationPage.spec.ts`).
- New/updated specs: `App.spec.ts` (skip link first anchor; `<nav>` named; public route has no
  non-skip anchors), `HomePage.spec.ts` (live-region message on load success/failure),
  `NewConsultationPage.spec.ts` (six `fieldset[data-position]` each with a "Line N" `<legend>`;
  submit `aria-busy` toggles), `router/index.spec.ts` (first nav doesn't focus `#main`, the
  next one does).

Live (dev server + accessibility tree / DOM inspection):
- Accessibility tree on `/`: `link "…skip…" href="#main"` first, then
  `navigation "Головна"` landmark, `status` live region (held "Не вдалося завантажити вміст"
  after the API 502), `main` landmark, error `alert`.
- Skip link: `.sr-only` at rest (home screenshot unchanged); on `focus()` → `position: fixed`,
  172×39 at top-left; activating it → `document.activeElement === main#main`.
- Route change `/` → `/journal` → `document.activeElement === main#main`.
- `/consultations/new` manual mode: six line groups, each an sr-only `<legend>` "Лінія 1"…"6",
  2 radios each; submit `aria-busy="false"` at rest. `document.documentElement.lang === "uk"`.
- Console: only the API's own 502s, no Vue/JS errors.

Outstanding before flipping to `verified` (needs a human + a fully-running API):
- NVDA (Windows) / VoiceOver listen-through of landmarks, route-change and load/error
  announcements, and manual-line grouping.
- axe DevTools / WAVE on `/`, `/consultations/new`, `/consultations/:id`, `/hexagrams/:n`.
- Before/after screenshots in light + dark on `/consultations/:id` (only `/` was captured this
  session; the API couldn't serve the interpretation section).
