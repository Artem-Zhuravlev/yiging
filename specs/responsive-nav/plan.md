# Plan — Responsive Navigation Menu (SPEC-046)

## Files

### Changed
- `apps/web/src/App.vue`
  - `import Drawer from 'primevue/drawer'`.
  - `const mobileNavOpen = ref(false)`.
  - `#start` slot, non-public branch — replace the single `<nav>` with:
    ```
    <template v-if="!route.meta.public">
      <nav :aria-label="t('nav.primaryLabel')" class="hidden md:flex flex-wrap gap-2">
        <router-link v-for="link in links" ... class="p-button p-button-text p-button-sm">
          {{ link.label }}
        </router-link>
      </nav>
      <Button
        class="md:hidden"
        icon="pi pi-bars"
        text rounded
        :aria-label="t('nav.menu')"
        :aria-expanded="mobileNavOpen"
        @click="mobileNavOpen = true"
      />
    </template>
    <div v-else class="font-semibold">Yijing</div>
    ```
  - Before `<LiveRegion />` (or anywhere top-level), add:
    ```
    <Drawer v-model:visible="mobileNavOpen" :header="t('nav.menu')" class="print-hidden">
      <nav :aria-label="t('nav.primaryLabel')" class="flex flex-column gap-1">
        <router-link
          v-for="link in links"
          :key="link.to"
          :to="link.to"
          class="p-button p-button-text justify-content-start"
          @click="mobileNavOpen = false"
        >
          {{ link.label }}
        </router-link>
      </nav>
    </Drawer>
    ```
  - No change to `#end`, `SkipLink`, `LiveRegion`, `router-view`, or the `links` computed.
- `apps/web/src/i18n/locales/{en,uk}.ts`: `nav.menu` = "Menu" / "Меню".

## Notes / risks
- PrimeVue `Drawer` (v4 name; `primevue@^4.5.5` has it) teleports to `<body>`. In `App.spec.ts`
  (jsdom) an unopened Drawer renders nothing, so the existing text assertions (which read the
  always-rendered desktop `<nav>`) are unaffected. `hidden md:flex` is a CSS class — the
  links stay in the DOM at all widths, so `wrapper.text()` still contains them.
- `aria-expanded` on a `<Button>` — PrimeVue passes unknown attrs through to the root
  `<button>`, so `:aria-expanded="mobileNavOpen"` lands correctly (boolean → "true"/"false").
- Closing the drawer via a link click sets `mobileNavOpen = false` *before* the router
  navigation resolves; the SPEC-039 `afterEach` focus-to-`#main` then runs. PrimeVue Drawer's
  "restore focus to trigger" on close could momentarily fight it — acceptable: the focus hook
  runs on `nextTick` after navigation, which is after the drawer's synchronous close. If a real
  conflict shows in the browser pass, guard by closing the drawer in a `watch(() => route.path)`
  instead of the click handler.

## Testing — `App.spec.ts`
- Existing tests unchanged in intent; they still pass (desktop `<nav>` always in DOM).
- Add:
  - "renders a hamburger menu button on a normal route" — `wrapper.find('button[aria-label]')`
    with the menu label exists; `aria-expanded` is `"false"` initially; after
    `await button.trigger('click')` the component's `mobileNavOpen` is true (assert via
    `aria-expanded` becoming `"true"` — note the Button re-renders with the new bound value).
  - "does not render the hamburger on a public route" — with `route.meta = { public: true }`,
    no button carrying the menu aria-label, and (existing) no `<a>` besides the skip link.
- Drawer contents are teleported; testing the drawer's own link list is left to the manual
  browser pass (mounting with `attachTo` + querying `document.body` is brittle for this).

## Verify

`npm run verify`; browser: `resize_window` mobile → confirm hamburger replaces the row, opens a
drawer with all seven links, a link navigates + closes it, `Esc` closes it, dark/locale still
work; `resize_window` desktop → row is back and unchanged; visit a `/share/...` route → bare
"Yijing".

## Verification note (2026-08-28)

- `npm run verify` green (web 190 tests incl. 2 new App.spec tests; api 312; yijing-core 55).
- Live pass (browser pane): at 375px the seven links are replaced by one hamburger
  (`aria-label="Меню"`, `aria-expanded` toggles); the drawer opens with all 7 links, clicking
  "Історія" navigates to `/consultations` and closes the drawer; outside-click and a real
  Escape keypress (via an explicit `@keydown.esc` on the Drawer — PrimeVue 4.5.5
  `closeOnEscape` did not fire in this setup) both close it. Dark toggle + EN/UK stay visible.
  At 1100px the horizontal row is back (`display: flex`, 7 links) and the hamburger is
  `display: none`.
