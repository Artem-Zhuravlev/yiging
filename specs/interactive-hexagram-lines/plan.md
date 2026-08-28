# Plan — Interactive Hexagram Lines (SPEC-044)

## Files

### Changed
- `apps/web/src/entities/hexagram/ui/HexagramLines.vue`
  - props: add `interactive?: boolean` (default false), `selectedPosition?: number | null`
    (default null). `defineEmits<{ select: [position: number] }>()`.
  - template: `<component :is="interactive ? 'button' : 'div'"` for each row, with
    `:type="interactive ? 'button' : undefined"`,
    `:aria-label="interactive ? t('hexagramLines.lineAriaLabel', { position: line.position }) : undefined"`,
    `:aria-pressed="interactive ? String(line.position === selectedPosition) : undefined"`,
    `:class="{ 'hexagram-line-selected': interactive && line.position === selectedPosition }"`,
    `@click="interactive && emit('select', line.position)"`.
    Keep `hexagram-line` class + `data-*` on the same element.
  - The outer wrapper keeps `role="img"` + aria-label (it's still a picture); the buttons are
    interactive children — acceptable (role="img" hides children from AT, so when interactive
    add `role="group"` + drop `role="img"` on the wrapper so the buttons are reachable). Guard:
    `:role="interactive ? undefined : 'img'"`.
  - scoped CSS: `.hexagram-line-selected .hexagram-line-bar { background: var(--p-primary-color); }`
    and a focus-visible outline; button reset (`background:none;border:0;padding:0;cursor:pointer`).
- `apps/web/src/entities/hexagram/ui/HexagramLines.spec.ts` — add: interactive mode renders
  `<button>` rows with `aria-label`/`aria-pressed`; clicking emits `select` with the position;
  default mode still renders `<div>` (existing tests already cover the bar/`data-*` markup).
- `apps/web/src/pages/hexagrams/HexagramDetailPage.vue`
  - `const selectedLine = ref<number | null>(null)`.
  - reset it to `null` inside the existing `watch(kingWenNumber, …)` handler.
  - `function toggleLine(p: number) { selectedLine.value = selectedLine.value === p ? null : p }`.
  - `<HexagramLines>` in the header block: when `state.hexagram.lineStatements` is non-null,
    add `interactive :selected-position="selectedLine" @select="toggleLine"`; else leave as-is.
  - New inline panel right after the `<HexagramLines>` / name block (still inside the header
    flex row or just below it): `v-if="selectedLine !== null && state.hexagram.lineStatements"` —
    a small bordered box: `<h3>{{ t('hexagramDetail.line', { position: selectedLine }) }}</h3>`,
    `<p>{{ state.hexagram.lineStatements[selectedLine - 1] }}</p>`, a close `Button` (text, `×`
    / `t('common.close')`) → `selectedLine = null`.
  - Bottom "Line Texts" `<li>`: `:class="{ 'line-text-selected': lineNumberFor(index) === selectedLine }"`
    where `lineNumberFor(index) = lineStatements.length - index` (list is reversed top-to-bottom).
  - `@keydown.esc` on the wrapper `<main>` or the header div → `selectedLine = null`.
  - scoped CSS: `.line-text-selected { background: var(--p-highlight-background, var(--p-content-hover-background)); border-radius: 4px; }`
    — use a theme var with a safe fallback.
- `apps/web/src/i18n/locales/{en,uk}.ts`: `hexagramLines.lineAriaLabel` = "Line {position}" /
  "Лінія {position}"; `common.close` = "Close" / "Закрити".
- `apps/web/src/pages/hexagrams/HexagramDetailPage.spec.ts` — add: with line text, clicking a
  line button shows the panel with that line's text; clicking again hides it; the bottom list
  entry gets the selected class. With `lineStatements: null`, no line buttons.

## Testing

- `HexagramLines.spec.ts`: default = `<div>` rows (regression); `interactive` = `<button>` rows,
  `aria-label` "Line 3", `aria-pressed` toggles with `selectedPosition`, click → `emitted('select')`
  `[[3]]`.
- `HexagramDetailPage.spec.ts`: existing fixture has `lineStatements` for the hexagram it loads
  (check — if not, extend the fixture). Click line 2's button → panel text contains
  `lineStatements[1]`; the `<li>` for line 2 has `line-text-selected`; click again → panel gone.
  A second test with a `lineStatements: null` fixture → `wrapper.findAll('button[aria-label^="Line"]')`
  is empty.

## Verify

`npm run verify`; browser on `/hexagrams/1`: click lines, check inline panel + bottom-list
highlight + Esc, light and dark; check `/hexagrams/2` etc.; confirm `/hexagrams` grid cards and
`/consultations/:id` diagram are unchanged.

## Verification note (2026-08-28)

- `npm run verify` green (web 185 tests incl. new `HexagramLines` interactive-mode tests and
  two `HexagramDetailPage` tests; api 312; yijing-core 55).
- Live pass on `/hexagrams/1` (dark): 6 line buttons; clicking line 4 shows the inline panel
  with its Legge statement + close, `aria-pressed=true`, and highlights line 4 in the bottom
  list; `Esc` clears it. `/hexagrams` grid confirmed unchanged (64 `role="img"` diagrams, no
  buttons, `<div>` rows).
