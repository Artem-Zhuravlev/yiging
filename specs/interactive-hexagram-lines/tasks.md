# Tasks — Interactive Hexagram Lines (SPEC-044)

- [x] **TASK-ILINE-001** — `HexagramLines.vue`: `interactive` + `selectedPosition` props,
      `select` emit; rows become `<button>` when interactive (same `data-*` + bars), `aria-label`
      "Line N", `aria-pressed`, selected class; wrapper drops `role="img"` (uses `role="group"`)
      only when interactive; default mode byte-identical. Scoped CSS for selected + focus-visible,
      theme vars only. → REQ-ILINE-001, 002, 020
- [x] **TASK-ILINE-002** — `HexagramDetailPage.vue`: `selectedLine` ref (reset on route param
      change), `toggleLine`; pass `interactive`/`selected-position`/`@select` to `HexagramLines`
      **only when `lineStatements` is non-null**; inline panel (`Line N` heading + statement +
      close) below the diagram; highlight the matching bottom "Line Texts" `<li>`; `Esc` clears.
      → REQ-ILINE-003, 004, 005, 006
- [x] **TASK-ILINE-003** — i18n `hexagramLines.lineAriaLabel`, `common.close` (en + uk).
      → REQ-ILINE-021
- [x] **TASK-ILINE-004** — `HexagramLines.spec.ts`: default `<div>` regression + interactive
      `<button>` (`aria-label`, `aria-pressed` follows `selectedPosition`, click emits
      `select` with position). → REQ-ILINE-001, 002
- [x] **TASK-ILINE-005** — `HexagramDetailPage.spec.ts`: click a line → panel shows that
      statement + bottom `<li>` highlighted; click again → hidden; `lineStatements: null` →
      no line buttons. → REQ-ILINE-003, 004, 005
- [x] **TASK-ILINE-006** — `npm run verify` green; browser pass on `/hexagrams/:n` (panel,
      highlight, Esc, light + dark) and confirm the grid / consultation diagrams unchanged; fill
      `plan.md` note; flip `spec.md` → `implemented`; add SPEC-044 to both README tables.
      → REQ-ILINE-022