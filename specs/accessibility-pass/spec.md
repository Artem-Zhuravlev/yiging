# SPEC-039 — Accessibility Pass

**Status:** implemented
**Owner:** unassigned
**Last updated:** 2026-08-28

## Problem

The UI has grown across 38 specs without a dedicated accessibility review. Concrete gaps
today:

- **No landmarks.** `App.vue` renders the nav inside a PrimeVue `Toolbar` (a plain `<div>`),
  and most pages wrap content in `<main>` but the app has no consistent `<nav>`/`<header>`
  structure. A screen-reader user has no "skip to content" and no way to jump between regions.
- **Async state is silent.** Every page models `loading` / `error` / `loaded` states
  (`HomePage`, `ConsultationPage`, `ConsultationHistoryPage`, `HexagramDetailPage`, …) but the
  DOM swaps happen with no `aria-live` region, so a non-sighted user gets no feedback that a
  fetch finished or failed. The "Casting…" → navigation transition on `NewConsultationPage` is
  likewise unannounced.
- **Focus is never managed.** On client-side route changes focus stays on the clicked link
  (or is lost to `<body>`); after casting a reading the user is navigated to a new page with no
  focus move to its heading. Keyboard users lose their place on every navigation.
- **Active nav item is not exposed.** Nav links have no `aria-current="page"`; the only visual
  cue is whatever `router-link-active` styling PrimeVue's `p-button-text` happens to give.
- **Manual casting form.** `NewConsultationPage`'s six line rows use `<label>` + `RadioButton`
  per polarity, but each row's "which line is this" is only a bare `<span>` number, not a
  grouping label a screen reader associates with the radios.
- **Icon-only / symbolic content.** Mostly handled (`HexagramLines` has `role="img"` +
  `aria-label`, the dark-mode toggle has `aria-label`), but this pass should confirm coverage
  rather than assume it.

None of this blocks a sighted mouse user, which is why it accumulated — but it's real debt and
the fixes are small, low-risk, and localized.

## Purpose

Bring the existing UI up to a baseline of keyboard- and screen-reader-usability — landmarks,
skip link, live-region announcements for async state, focus management on navigation — without
changing any visual design, layout, routes, or feature behavior.

## Scope

### Document structure & landmarks
- `App.vue`: wrap the toolbar nav in a semantic `<nav aria-label="Primary">` (the `Toolbar`
  component stays; add the element around/via its markup). Keep the public-route collapsed
  state (SPEC-029) — the bare "Yijing" text stays, just inside the `<nav>`.
- Add a visible-on-focus **skip link** ("Skip to main content") as the first focusable element,
  targeting `#main`.
- Every routed page's top-level `<main>` gets `id="main"` and `tabindex="-1"` (so focus can be
  moved to it programmatically without making it a persistent tab stop). Audit all 12 page
  components; any that use a different wrapper element switch to `<main>`.

### Live-region announcements
- Add one shared, visually-hidden `aria-live="polite"` status region (a small
  `shared/ui/` component or a composable + element in `App.vue`).
- Wire the existing per-page `loading` / `error` state machines to announce transitions
  ("Loading…", the error message text, "Loaded" where a silent success would otherwise be
  confusing). Error `Message` components additionally get `role="alert"`.
- `NewConsultationPage`: the "Casting…" state is announced; the submit button gets
  `aria-busy="true"` while submitting.

### Focus management
- On route change, move focus to `#main` of the newly-rendered page (a router `afterEach` hook
  plus a small `nextTick` focus call, or a `<RouterView>` wrapper). Exclude the very first
  page load (don't steal focus from the skip link on cold load).
- Respect users arriving via in-page anchor / back-forward cache where reasonable — a plain
  focus-to-main is acceptable for all cases here.

### Forms
- `NewConsultationPage`: each of the six manual-line rows becomes a `<fieldset>` (or a
  `role="group"` with `aria-labelledby`) whose label is "Line {n}", so the polarity radios and
  the changing checkbox are grouped and announced with their line number. The outer
  "Lines (top to bottom)" `<fieldset>`/`<legend>` stays.
- Confirm every standalone icon-only control has an `aria-label` (dark-mode toggle already
  does; check the favorites star on `HexagramListPage`, the lens ✓ marker — the ✓ is
  `aria-hidden` which is correct).

### Nav state
- Nav `router-link`s expose `aria-current="page"` on the active route (vue-router sets this
  automatically on `<router-link>` unless suppressed — verify it survives the `p-button`
  styling and isn't stripped).

## Out of scope

- **Visual redesign** — colors, contrast ratios, focus-ring restyling, spacing. This pass is
  semantics + focus only. A contrast/theming audit is a separate spec if wanted. (If a focus
  outline is currently `outline: none` anywhere in our CSS we restore it, but we don't design a
  new one.)
- **Automated a11y test tooling** (axe-core / vitest-axe integration). Worth doing but a
  separate infra decision; this spec verifies with targeted `*.spec.ts` assertions and a manual
  screen-reader/keyboard pass.
- **PrimeVue component internals.** We rely on PrimeVue's own ARIA for `Button`, `RadioButton`,
  `Checkbox`, `Select`, `Message`, etc. and don't patch the library.
- **`SharedConsultationPage` nav** beyond confirming the skip link + `<main>` + landmark
  pattern also applies there.
- **i18n of any new strings** is *in* scope (skip link, "Loading…", "Line {n}") — they go
  through `vue-i18n` like everything else (SPEC-038) — but no new locales.

## User behavior

- A keyboard user pressing Tab on any page first hits "Skip to main content"; activating it
  moves focus past the nav to the page's `<main>`.
- A screen-reader user hears the primary navigation as a named landmark and the main content as
  a `main` landmark, and can jump between them.
- Navigating (e.g. clicking "History", or casting a reading) moves the screen-reader's focus to
  the new page's main region, and the loading/loaded/error transition is announced.
- On the manual casting form, each line's radios are announced as "Line 3, Yang / Yin,
  Changing" rather than an ungrouped list of radios.

## Functional requirements

- **REQ-A11Y-001** — A skip link is the first focusable element on every route; when activated
  it moves keyboard focus to that page's `<main>` (`id="main"`).
- **REQ-A11Y-002** — Every routed page exposes exactly one `<main id="main" tabindex="-1">`
  landmark; the primary navigation is a `<nav>` landmark with an accessible name.
- **REQ-A11Y-003** — After a client-side route change (but not the initial cold load), focus
  moves to the new page's `#main`.
- **REQ-A11Y-004** — A polite `aria-live` region announces each page's `loading` → `loaded` /
  `error` transitions; error messages are also `role="alert"`.
- **REQ-A11Y-005** — On `NewConsultationPage`, the submit button is `aria-busy` while casting
  and the "Casting…" state is announced.
- **REQ-A11Y-006** — Each manual-line row on `NewConsultationPage` is a labelled group
  ("Line {n}") associating its polarity radios and changing checkbox.
- **REQ-A11Y-007** — The active primary-nav link carries `aria-current="page"`.
- **REQ-A11Y-008** — No routed page has a WAVE/axe "no main landmark", "no heading",
  "multiple main", or "empty link/button" error in a manual check.

## Non-functional requirements

- **REQ-A11Y-020** — No change to rendered visual design in either theme (light/dark): a
  reviewer diffing screenshots before/after sees only the on-focus skip link.
- **REQ-A11Y-021** — All new user-facing strings are localized (en + uk).
- **REQ-A11Y-022** — No route, API call, store, or feature-behavior change.
- **REQ-A11Y-023** — `npm run verify` passes (lint, typecheck, test, build) and existing
  `*.spec.ts` selectors keep working (structural `id` / `data-*` / role-based queries must not
  break; content assertions updated only where a new wrapper element is introduced).

## Data requirements

None. No schema, no persistence, no new entities.

## API requirements

None. No endpoint added or changed.

## Edge cases

- Cold page load → focus stays available for the skip link; the route-change focus hook must
  no-op on the first navigation.
- Public share route (`meta.public`) → skip link + `<main>` + landmark still apply; nav is
  still the collapsed bare-text form, wrapped in `<nav>`.
- A page that finishes loading with content identical to its skeleton (rare) → still announces
  "Loaded" once, not on every reactive tick.
- User with `prefers-reduced-motion` → no impact (we add no motion); noted only to confirm.
- vue-router already emitting `aria-current` → we must not double-set or strip it.

## Acceptance criteria

- [x] Tabbing from the top of any page reveals a "Skip to main content" link that moves focus
      to `<main>`. — verified live: skip link is the first DOM anchor, `.sr-only` until focus
      then `position: fixed` visible, activating it sets `document.activeElement` to `main#main`.
- [x] Every routed page has exactly one `<main id="main">` and a named `<nav>` landmark
      (asserted in `App.spec.ts` + live accessibility-tree check: `navigation "Головна"` and
      `main` landmarks present). Manual axe/WAVE pass still recommended before `verified`.
- [x] Clicking a nav link or casting a reading moves focus to the destination page's `<main>`;
      the initial load does not steal focus from the skip link. — `router/index.spec.ts` +
      live check (nav `/` → `/journal` moved `activeElement` to `main#main`).
- [~] A screen reader announces loading and error transitions — verified structurally: the
      shared `role="status" aria-live="polite"` region receives the localized message on every
      page's load/error transition (`HomePage.spec.ts` asserts "Content loaded" /
      "Failed to load content"; live check showed the region holding "Не вдалося завантажити
      вміст" after a failed fetch). A real NVDA/VoiceOver listen-through is still recommended
      before `verified`.
- [x] Manual-line rows are announced with their line number. — `NewConsultationPage.spec.ts`
      (six `fieldset[data-position]`, each `<legend>` "Line N") + live check ("Лінія 1"…"Лінія 6").
- [x] Before/after screenshots in both themes are visually identical except the on-focus skip
      link — home page screenshot shows no visible change; skip link is `.sr-only` until focus.
- [x] `npm run verify` passes end to end.

## Outstanding before `verified`

- Manual NVDA (Windows) or VoiceOver listen-through of: landmark list, route-change
  announcement, load/error announcement, manual-line grouping.
- axe DevTools / WAVE run on `/`, `/consultations/new`, `/consultations/:id`, `/hexagrams/:n`
  against a fully-running API (this session's API returned 502 for AI-backed calls, so the
  consultation detail page's interpretation section could not be exercised end to end).
