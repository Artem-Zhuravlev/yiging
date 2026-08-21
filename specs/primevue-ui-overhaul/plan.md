# Plan — PrimeVue UI Overhaul (SPEC-037)

1. Install `primevue`, `@primeuix/themes`, `primeicons`, `primeflex`; remove `tailwindcss`/
   `@tailwindcss/vite`.
2. `src/theme.ts` — red-primary Aura preset via `definePreset`. Register in `main.ts` alongside
   `ToastService`/`ConfirmationService`; import `primeicons/primeicons.css` and
   `primeflex/primeflex.css`; replace `style.css`'s Tailwind import with a small hand-written base
   stylesheet (box-sizing reset, body background/color from PrimeVue theme tokens, `@media print`
   rules for the controls that must hide when printing).
3. `App.vue` — PrimeVue `Menubar` (or `Toolbar` + `Button`s) for top navigation, replacing the
   hand-rolled Tailwind nav.
4. Rewrite pages in dependency order (simplest first, to establish component conventions before
   tackling the large ones):
   `HomePage` → `HexagramListPage` → `StatisticsPage` → `InterpretationSettingsPage` →
   `JournalPage` → `HexagramEditorPage` → `SharedConsultationPage` → `HexagramComparePage` →
   `HexagramDetailPage` → `NewConsultationPage` → `ConsultationHistoryPage` →
   `HexagramLines.vue` (shared entity component) → `ConsultationPage` (largest, last).
   Component mapping used throughout: `<button>` → `Button`, `<input>`/`<textarea>` →
   `InputText`/`Textarea`, card `<div>`s → `Card`, list/table markup → `DataTable`/`DataView` or
   `Panel`, inline validation/error text → `Message`, badges/labels (tags, lens pills) → `Tag`,
   confirmations for destructive-ish actions → kept as plain PrimeVue buttons (no new confirm
   dialogs added — out of scope per spec).
5. After each page: run its existing `*.spec.ts` (or the closest one) and fix selectors broken by
   the new DOM shape; keep every `id`/button-label the tests already key on.
6. Once every page is converted: `npm run verify` end to end, then manual browser pass covering
   every route.
