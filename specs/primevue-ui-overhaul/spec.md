# SPEC-037 — PrimeVue UI Overhaul

**Status:** verified
**Owner:** unassigned
**Last updated:** 2026-08-21

## Problem

`apps/web` has been built entirely with hand-rolled Tailwind utility classes on plain HTML
elements (`<button>`, `<input>`, `<textarea>`, ad-hoc `<div>` cards). This is functional but
visually plain, and every page reinvents its own button/card/form styling from scratch. The user
asked for a proper component library instead: PrimeVue (Vue-native widget library) + PrimeFlex
(utility CSS for layout), with a red-toned theme.

## Purpose

Replace the app's raw-HTML-plus-Tailwind UI with PrimeVue components (Button, InputText,
Textarea, Card, DataTable, Tag, Message, Dialog, etc.) styled by a red-primary PrimeVue theme, and
PrimeFlex for grid/spacing layout — across every page and shared UI component in `apps/web` —
without changing any existing behavior, API calls, routes, or business logic.

## Scope

- Add `primevue`, `@primevue/themes`, `primeicons`, `primeflex` to `apps/web`.
  **2026-08-21 update**: npm's `latest` tag for `primevue` (5.0.1) and its theme package
  (`@primeuix/themes`) turned out to require a paid "PrimeUI" commercial license — without a key
  they render an "Invalid PrimeUI License" watermark (confirmed live, not just from docs).
  `primeicons@latest` (8.x) has the same restriction. Pinned instead to the last fully MIT-licensed
  major versions: `primevue@^4.5.5` (`v4-stable` dist-tag), `@primevue/themes@^4.5.4`,
  `primeicons@^7.0.0`. `primeflex` was unaffected (still MIT at `^4.0.0`). This keeps the app
  entirely free/open with no license key or account required, matching what was actually asked for.
- Remove `tailwindcss`/`@tailwindcss/vite` — PrimeFlex + PrimeVue's own component styles replace
  Tailwind utility classes everywhere; no page keeps Tailwind classes after this spec.
- Register the PrimeVue plugin in `main.ts` with a custom preset (`src/theme.ts`): Aura preset
  with `semantic.primary` remapped to PrimeVue's built-in `red` palette — "some nice theme with
  red tones," per the user's request.
- Rewrite every `.vue` file's `<template>` to use PrimeVue components instead of raw
  `<button>`/`<input>`/`<textarea>`/ad-hoc card `<div>`s, and PrimeFlex classes (`flex`,
  `formgrid`, `grid`, `col-*`, spacing helpers) for layout instead of Tailwind's.
- `<script setup>` logic (state, API calls, computed values, validation) is unchanged — this is a
  presentation-layer rewrite, not a functional one.
- All existing element `id`s referenced by tests (e.g. `#edit-what-happened`) and all button label
  text used by tests (`wrapper.findAll('button').find(...)`) are preserved so existing test
  intent keeps working; tests are updated only where PrimeVue's rendered DOM structure genuinely
  requires a different selector (e.g. a `DataTable` row instead of a raw `<tr>`).
- `print:hidden`-equivalent behavior (some controls are hidden when printing, e.g. the interpretation
  lens tabs) is preserved using a plain `@media print` rule in `style.css`, since PrimeFlex has no
  print-variant utilities.

## Out of scope

- **No new features or behavior changes.** Every page keeps its existing fields, buttons, states,
  and API calls; this is a pure re-skin.
- **PrimeVue's DataTable server-side features** (lazy loading, pagination server round-trips) —
  any table used here is a simple client-rendered list, matching current behavior.

**2026-08-22 update**: Dark mode was originally scoped out above, but the default light theme
turned out not to work for the user, and default browser underlines on every `<a>` (never reset
in `style.css`) looked broken next to PrimeVue's own unstyled buttons. Added `src/darkMode.ts` — a
toggle in the toolbar next to the locale switcher, using the `darkModeSelector: '.p-dark'` this
spec already wired up, persisted in `localStorage`, defaulting new visitors to dark rather than
PrimeVue's usual light default. `style.css` now resets `a { text-decoration: none }` with an
`:hover` underline for plain inline links (excluded for anything already styled as a `.p-button`).
Manually verified both themes on the home and consultation-detail pages.

**2026-08-22 update 2**: user reported insufficient responsiveness. Automated a horizontal-overflow
scan (`document.documentElement.scrollWidth` vs `clientWidth`) across every route at 320/375/768px
widths — found exactly one real bug: `ConsultationPage`'s header (`<h1>` + favorite/print/share
button row) used `justify-content-between` with no wrap on the outer flex container, so on narrow
screens the button row (`flex-shrink-0`) overflowed the viewport by ~460px instead of dropping
below the title. Fixed by making that row `flex-column` below the `sm:` breakpoint (stacking title
above buttons) and `sm:flex-row sm:justify-content-between` above it, removing the now-unnecessary
`flex-shrink-0`. Every other route was already overflow-free at all three widths, including live
AI-interpretation content and the outcome-link box with real long text.

**2026-08-22 update 3**: user reported two more issues. (1) "No main container, everything
stretched full-width" — every page's `<main>` used Tailwind-named classes
(`max-w-screen-{sm,md,lg}`) that were never ported to a PrimeFlex (or custom) equivalent when
Tailwind was removed; PrimeFlex's own `.max-w-*` scale tops out at `30rem`, nowhere near a page
container, so these classes had silently done nothing since the original conversion — every page
has been full-width and uncentered the whole time. Added `.container-{sm,md,lg}` to `style.css`
(`40rem`/`48rem`/`64rem`, matching the Tailwind breakpoints they replace) and swapped every page
over; confirmed centered and width-constrained at 1600px. (2) The red primary color "wasn't
comfortable or nice to look at" — swapped to PrimeVue's built-in `blue` palette in `theme.ts`
(`redTheme` renamed `appTheme` throughout), per the user's choice among a few alternatives offered.

## Acceptance criteria

- [x] `npm install` resolves with `primevue`/`@primevue/themes`/`primeicons`/`primeflex`;
      `tailwindcss`/`@tailwindcss/vite` removed from `apps/web/package.json`.
- [x] Every `.vue` file under `apps/web/src` renders through PrimeVue components/PrimeFlex layout
      classes; no Tailwind utility classes remain.
- [x] `npm run verify` (typecheck, lint, test, build) passes.
- [x] Manually verified in the browser: navigation, home, new consultation, consultation detail
      (including AI interpretation/outcome/follow-up sections with a real Gemini call, linking and
      saving an outcome), history, hexagram list/detail/compare/editor, journal, statistics, and
      settings all render correctly with the red theme and remain fully functional.
