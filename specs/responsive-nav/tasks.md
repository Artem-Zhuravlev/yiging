# Tasks — Responsive Navigation Menu (SPEC-046)

- [x] **TASK-RNAV-001** — `App.vue`: desktop `<nav>` becomes `hidden md:flex`; add a
      `md:hidden` hamburger `Button` (`pi pi-bars`, `aria-label` = `t('nav.menu')`,
      `:aria-expanded="mobileNavOpen"`, `@click` opens). Public branch unchanged.
      → REQ-RNAV-001, 002, 006
- [x] **TASK-RNAV-002** — `App.vue`: add `<Drawer v-model:visible="mobileNavOpen">` (left,
      `:header="t('nav.menu')"`, `print-hidden`) containing a `<nav aria-label>` vertical list
      of the same `links`; each link `@click="mobileNavOpen = false"`. → REQ-RNAV-003, 004, 021
- [x] **TASK-RNAV-003** — i18n `nav.menu` (en + uk). → REQ-RNAV-020
- [x] **TASK-RNAV-004** — `App.spec.ts`: existing tests still green; add a hamburger-present +
      `aria-expanded` toggle test on a normal route, and a no-hamburger test on a public route.
      → REQ-RNAV-002, 006, REQ-RNAV-022
- [x] **TASK-RNAV-005** — `npm run verify` green; browser pass at mobile + desktop width
      (`resize_window`), drawer open/link/Esc, dark + locale still work, public route bare;
      fill `plan.md` note; flip `spec.md` → `implemented`; add SPEC-046 to both README tables.
      → REQ-RNAV-004, 005, REQ-RNAV-022