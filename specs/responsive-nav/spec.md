# SPEC-046 — Responsive Navigation Menu

**Status:** implemented
**Owner:** unassigned
**Last updated:** 2026-08-28

## Problem

The primary navigation in `App.vue` is a `flex flex-wrap` row of seven text-button links
("Yijing", Hexagrams, New Consultation, History, Journal, Statistics, Settings). On a phone it
wraps onto two or three lines, pushing the page content down and looking broken — and the
wrapped rows have no visual grouping, so it reads as a pile of links rather than a nav bar.

## Purpose

Keep the horizontal link row on wide screens; on narrow screens collapse it into a single
hamburger button that opens a drawer with the same links stacked vertically. No routes or link
targets change.

## Scope

- `App.vue`, non-public routes only (the `route.meta.public` collapsed "Yijing" text stays):
  - **≥ `md` (768px)**: the existing horizontal `<router-link>` row, unchanged.
  - **< `md`**: a single icon button (`pi pi-bars`, `aria-label` = "Menu", `aria-expanded`
    bound to the drawer state) in the toolbar's start slot; the horizontal row is hidden
    (`hidden md:flex`), the button is `md:hidden`.
  - The button opens a PrimeVue `Drawer` (left side) containing the same `links` as a vertical
    list of `<router-link>`s. Selecting any link closes the drawer. The drawer has an
    accessible title ("Menu").
  - The toolbar's end slot (dark-mode toggle + EN/UK) is unchanged and stays visible at all
    widths.
- Accessibility: the hamburger button toggles `aria-expanded`; `Drawer` provides its own focus
  trap, `Esc`-to-close, and returns focus to the trigger on close (PrimeVue behaviour). The
  desktop row keeps its `<nav aria-label="Primary">` landmark; the drawer's link list is also
  wrapped in a `<nav aria-label="Primary">`.
- The route watcher / `router.afterEach` focus behaviour (SPEC-039) is untouched; closing the
  drawer on navigation must not fight the focus-to-`#main` hook (close the drawer, let the
  hook run).
- Localised: `nav.menu` = "Menu" / "Меню" (en + uk).

## Out of scope

- **Replacing `Toolbar` with PrimeVue `Menubar`.** Menubar's model/command API and styling are
  a bigger change than the problem needs; the drawer approach reuses the existing `links` array
  as-is.
- **A bottom tab bar**, nested/submenu navigation, or an app-wide layout refactor.
- **Changing which links exist, their order, or their targets.**
- **Animating anything beyond the drawer's own open/close transition** (PrimeVue default,
  already respects reduced motion).
- **The public share route's nav** (stays the bare "Yijing" text).

## Functional requirements

- **REQ-RNAV-001** — On viewports ≥ 768px the primary nav renders as today: a horizontal row
  of the seven links.
- **REQ-RNAV-002** — On viewports < 768px the horizontal row is hidden and a single hamburger
  button is shown in its place; the button has `aria-label` "Menu" and `aria-expanded`
  reflecting the drawer's open state.
- **REQ-RNAV-003** — Activating the hamburger opens a left drawer listing the same seven links
  vertically; activating any link navigates and closes the drawer.
- **REQ-RNAV-004** — The drawer closes on `Esc` and on outside click (PrimeVue default) and
  returns focus to the hamburger button.
- **REQ-RNAV-005** — The dark-mode toggle and EN/UK switch remain visible and functional at all
  widths.
- **REQ-RNAV-006** — On `route.meta.public` routes the nav is still just the bare "Yijing"
  text — no hamburger, no links.

## Non-functional requirements

- **REQ-RNAV-020** — New string localised (en + uk).
- **REQ-RNAV-021** — Both the desktop row and the drawer list are `<nav aria-label="Primary">`
  landmarks; the skip link and SPEC-039 focus hook still work.
- **REQ-RNAV-022** — `npm run verify` passes; `App.spec.ts` still asserts the link labels are
  present on a normal route (they remain in the DOM, just `display:none` below `md`), the skip
  link is first, and no nav links on a public route.

## Data requirements

None.

## API requirements

None.

## Edge cases

- Resizing from mobile to desktop while the drawer is open → the drawer stays open until
  dismissed; the desktop row also appears. Acceptable (rare); no special handling.
- A link to the current route → drawer still closes; router no-ops the navigation.
- `route.meta.public` → neither the row nor the hamburger renders.
- Very small height → `Drawer` scrolls its content (PrimeVue default).

## Acceptance criteria

- [x] At ≥768px the nav is the horizontal seven-link row, hamburger `display: none` — verified
      live at 1100px.
- [x] At <768px the row is `display: none`, one hamburger button shows (`aria-label` "Menu"),
      opens a drawer with all seven links; clicking a link navigates and closes the drawer —
      verified live at 375px + `App.spec.ts`.
- [x] `Esc` and outside-click both close the drawer — verified live (real Escape keypress via an
      explicit `@keydown.esc` handler on the Drawer, plus mask-click). PrimeVue 4.5.5's own
      `closeOnEscape` did not fire in this setup, hence the explicit handler.
- [x] Dark toggle + EN/UK stay visible and in the toolbar end slot at mobile width — verified
      live.
- [x] Public route: no hamburger, no links, bare "Yijing" — `App.spec.ts`.
- [x] `npm run verify` passes (web 190 tests, api 312, yijing-core 55).

## Implementation note (2026-08-28)

- `App.vue`: desktop `<nav>` is now `hidden md:flex`; a `md:hidden` `pi-bars` `Button`
  (`aria-label` = `nav.menu`, `:aria-expanded="mobileNavOpen"`) opens a `<Drawer
  v-model:visible="mobileNavOpen">` (guarded `v-if="!route.meta.public"`, `print-hidden`,
  `:header="nav.menu"`, `@keydown.esc="mobileNavOpen = false"`) whose body is a
  `<nav aria-label>` vertical list of the same `links`, each closing the drawer on click.
  End slot, `SkipLink`, `LiveRegion`, `router-view`, and the `links` computed are unchanged.
- i18n `nav.menu` = "Menu" / "Меню".
