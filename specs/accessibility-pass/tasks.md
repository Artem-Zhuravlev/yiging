# Tasks — Accessibility Pass (SPEC-039)

Each task references the requirement(s) it fulfills.

## Shared plumbing

- [x] **TASK-A11Y-001** — Add `.sr-only` (and focus-reveal) utility to `src/style.css`;
      confirm no existing `outline: none` in our CSS. → REQ-A11Y-001, REQ-A11Y-020
- [x] **TASK-A11Y-002** — `shared/ui/SkipLink.vue` (`href="#main"`, sr-only until focus).
      → REQ-A11Y-001
- [x] **TASK-A11Y-003** — `shared/lib/announce.ts` (module `ref` + `announce()`) and
      `shared/ui/LiveRegion.vue` (`role="status"` + `aria-live="polite"`, sr-only). Plus
      `shared/lib/useStatusAnnouncer.ts` — reusable `watch` that maps a page's status ref to
      localized announce calls (keeps per-page wiring to one line). → REQ-A11Y-004
      *(named `announce.ts` / `useStatusAnnouncer.ts`, not the `useAnnouncer.ts` placeholder.)*
- [x] **TASK-A11Y-004** — `shared/lib/focusMain.ts` — focus `#main` on `nextTick`.
      → REQ-A11Y-003 *(named `focusMain.ts`, not `useFocusMain.ts`.)*
- [x] **TASK-A11Y-005** — i18n keys in `locales/{en,uk}.ts`: `nav.primaryLabel`,
      `nav.skipToContent`, `a11y.loading`, `a11y.loaded`, `newConsultation.lineGroupLabel`.
      → REQ-A11Y-021

## App shell

- [x] **TASK-A11Y-006** — `App.vue`: render `<SkipLink>` first; wrap toolbar nav in
      `<nav :aria-label>`; mount `<LiveRegion>`; keep `meta.public` collapsed branch inside the
      `<nav>`. → REQ-A11Y-002
- [x] **TASK-A11Y-007** — `router/index.ts`: `afterEach` calling `useFocusMain()` when
      `from !== START_LOCATION`. → REQ-A11Y-003
- [x] **TASK-A11Y-008** — vue-router emits `aria-current="page"` on the active nav
      `router-link` by default; the `p-button` classes don't strip it. Left as the router
      default (no explicit binding needed). → REQ-A11Y-007

## Page sweep — landmarks (smallest first, ConsultationPage last)

- [x] **TASK-A11Y-009** — `<main id="main" tabindex="-1">` on: `HomePage`, `JournalPage`,
      `StatisticsPage`, `HexagramEditorPage`, `InterpretationSettingsPage`. → REQ-A11Y-002
- [x] **TASK-A11Y-010** — same on: `HexagramListPage`, `HexagramDetailPage`,
      `HexagramComparePage`, `NewConsultationPage`, `SharedConsultationPage`. → REQ-A11Y-002
- [x] **TASK-A11Y-011** — same on: `ConsultationHistoryPage`, `ConsultationPage`.
      → REQ-A11Y-002

## Async-state announcements

- [x] **TASK-A11Y-012** — Wire `announce()` + `role="alert"` on error `<Message>` for
      `HomePage`, `HexagramDetailPage`, `HexagramListPage`, `HexagramComparePage`,
      `StatisticsPage`, `JournalPage`, `InterpretationSettingsPage`, `SharedConsultationPage`.
      → REQ-A11Y-004
- [x] **TASK-A11Y-013** — Same for `ConsultationHistoryPage` and `ConsultationPage` (multiple
      independent async sections on the latter — announce the page-level load + interpretation
      fetch). → REQ-A11Y-004
- [x] **TASK-A11Y-014** — `NewConsultationPage`: `aria-busy` on submit button + announce
      "Casting…". → REQ-A11Y-005

## Forms

- [x] **TASK-A11Y-015** — `NewConsultationPage`: wrap each of the six manual-line rows in a
      `<fieldset>` with an sr-only `<legend>` "Line {n}"; keep the visible number span and the
      outer "Lines (top to bottom)" fieldset. → REQ-A11Y-006
- [x] **TASK-A11Y-016** — Audited icon-only controls: `HexagramListPage` favorites star and
      `App.vue` dark-mode toggle both already carry `aria-label`; the lens `✓` marker is
      `aria-hidden`; the hexagram Unicode glyph is `aria-hidden`. No regressions, nothing to
      add. → REQ-A11Y-008
- [x] Also applied the per-line `<fieldset>`/sr-only `<legend>` treatment to
      `HexagramEditorPage` — same line-row pattern as `NewConsultationPage`, left consistent
      rather than half-done. → REQ-A11Y-006 (spirit)

## Tests & verification

- [x] **TASK-A11Y-017** — `App.spec.ts`: skip link is the first anchor + `.skip-link`; `<nav>`
      has an `aria-label`; public route has no nav links beyond the skip link.
      → REQ-A11Y-001, 002, 007
- [x] **TASK-A11Y-018** — `NewConsultationPage.spec.ts`: six line groups w/ "Line N" legend;
      `aria-busy` toggles. → REQ-A11Y-005, 006
- [x] **TASK-A11Y-019** — `HomePage.spec.ts`: asserts `liveMessage` becomes "Content loaded"
      on success and "Failed to load content" on error. → REQ-A11Y-004
- [x] **TASK-A11Y-020** — router test: focus moves to `#main` on non-initial nav, not on first
      load. → REQ-A11Y-003
- [x] **TASK-A11Y-021** — Run full `npm run test`; repair only wrapper-element-induced content
      assertion breaks. → REQ-A11Y-023
- [~] **TASK-A11Y-022** — Keyboard pass done live (skip link reveal + activation → `main`;
      route change → `main`); home-page screenshot confirms no visible change. **Still owed
      before `verified`:** a real NVDA/VoiceOver listen-through and an axe/WAVE run against a
      fully-running API. → REQ-A11Y-008, REQ-A11Y-020
- [x] **TASK-A11Y-023** — `npm run verify` green; flip `spec.md` status to `implemented`.
      → REQ-A11Y-023
