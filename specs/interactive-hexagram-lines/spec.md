# SPEC-044 — Interactive Hexagram Lines

**Status:** implemented
**Owner:** unassigned
**Last updated:** 2026-08-28

## Problem

On the hexagram detail page the six line texts (James Legge) sit in a list at the *bottom* of
the page, far from the line diagram at the top. To read what line 4 says a user scans the
diagram, counts to the fourth line from the bottom, then scrolls down and counts again in the
list. The diagram — the most direct representation of the hexagram — isn't a way in to its own
line texts.

## Purpose

Make the line diagram on the hexagram detail page interactive: click (or keyboard-activate) a
line to reveal that line's statement inline, right next to the diagram. The `HexagramLines`
component gains an opt-in interactive mode; everywhere else it renders exactly as today.

## Scope

- `HexagramLines.vue` gains:
  - `interactive?: boolean` (default `false`) — when true, each line row is a
    `<button type="button">` instead of a `<div>`, keeping the same
    `data-position` / `data-polarity` / `data-changing` attributes and the same bar markup
    inside. The button has `aria-label` "Line {position}" and `aria-pressed` reflecting
    selection.
  - `selectedPosition?: number | null` (default `null`) — the currently-selected line, for the
    `aria-pressed` / selected styling.
  - emits `select(position: number)` on click / Enter / Space (native button behaviour).
  - A subtle selected style (e.g. the bar takes `--p-primary-color`, or a ring) — theme vars
    only, works in light and dark.
  - Non-interactive mode is byte-for-byte unchanged (the existing `role="img"` wrapper, `<div>`
    rows, `HexagramLines.spec.ts` assertions all still hold).
- `HexagramDetailPage.vue`:
  - `selectedLine = ref<number | null>(null)`.
  - Pass `interactive`, `:selected-position="selectedLine"`,
    `@select="toggleLine"` (toggles: clicking the selected line clears it).
  - When `selectedLine !== null` and `lineStatements` is present, render an inline panel beside
    /below the diagram: a "Line {n}" heading, the statement text, and a close control. If
    `lineStatements` is `null` (no classical text for this hexagram), the lines are **not**
    interactive (nothing to reveal) — pass `interactive` only when `lineStatements` exists.
  - The existing bottom "Line Texts" `<ol>` stays; the entry matching `selectedLine` gets a
    highlighted style so the two views agree.
- Localised: `hexagramLines.lineAriaLabel` = "Line {position}", `common.close` = "Close"
  (en + uk). The panel heading reuses `hexagramDetail.line`.
- Keyboard: the line buttons are in the normal tab order; `Esc` while focus is within the
  diagram or the panel clears the selection.

## Out of scope

- **Making `HexagramLines` interactive anywhere else** (list cards, comparison, consultation
  detail, editor, home, casting reveal). Those pass no `interactive` prop and are untouched.
- **Editing line text**, per-line AI interpretation, or linking a line to a consultation.
- **A popover/tooltip library** — the reveal is a plain inline panel.
- **Changing the casting/consultation line diagrams.** Only the hexagram *reference* detail
  page (`/hexagrams/:number`).
- **Touch long-press or hover reveal** — click/activate only.

## Functional requirements

- **REQ-ILINE-001** — With `interactive`, each `HexagramLines` row is a focusable
  `<button type="button">` carrying `aria-label` "Line {position}" and `aria-pressed`
  = (position === selectedPosition); a click/Enter/Space emits `select(position)`.
- **REQ-ILINE-002** — Without `interactive` (the default), `HexagramLines` renders exactly as
  before: `<div>` rows, same bars, same `data-*`, existing tests unchanged.
- **REQ-ILINE-003** — On `/hexagrams/:number` with classical line text available, activating a
  line shows that line's statement in an inline panel with a "Line {n}" heading and a close
  control; activating the same line again (or the close control) clears it.
- **REQ-ILINE-004** — The bottom "Line Texts" entry for the selected line is visually
  highlighted while a line is selected.
- **REQ-ILINE-005** — When a hexagram has no `lineStatements`, its diagram on the detail page is
  not interactive.
- **REQ-ILINE-006** — `Esc` with focus inside the diagram or the panel clears the selection.

## Non-functional requirements

- **REQ-ILINE-020** — Selected styling uses only PrimeVue theme CSS variables; legible light
  and dark.
- **REQ-ILINE-021** — New strings localised (en + uk).
- **REQ-ILINE-022** — `npm run verify` passes; existing `HexagramLines.spec.ts` and
  `HexagramDetailPage.spec.ts` still pass (updated only where the new markup requires).

## Data requirements

None. Uses the `Hexagram.lineStatements` the detail page already fetches.

## API requirements

None.

## Edge cases

- Hexagram with `lineStatements: null` → diagram stays non-interactive; no panel, no highlight.
- A changing-line dot on a line that becomes a button → the dot still renders; the button's
  accessible name is still just "Line {n}".
- Rapidly clicking different lines → panel just swaps content; only one line selected at a time.
- Navigating to a different hexagram (route param change) → `selectedLine` resets to `null`.
- Non-interactive callers pass nothing → no `select` listener, no behaviour change.

## Acceptance criteria

- [x] On `/hexagrams/:number` (with line text), clicking a line reveals its statement in an
      inline panel; clicking it again (or the `×` close, or `Esc`) hides it — verified live on
      `/hexagrams/1` (panel "ЛІНІЯ 4 — In the fourth NINE, undivided…", `aria-pressed=true`) +
      `HexagramDetailPage.spec.ts`.
- [x] The matching bottom "Line Texts" entry is highlighted (`.line-text-selected`) while
      selected — live + spec.
- [x] The diagram elsewhere is unchanged — live: `/hexagrams` grid shows 64 `role="img"`
      diagrams, 0 buttons, `<div>` rows; `HexagramLines.spec.ts` default-mode regression test.
- [x] Line buttons are `<button type="button">` in tab order; `Esc` on the detail wrapper
      clears the selection (live).
- [x] `lineStatements: null` → no line buttons (`HexagramDetailPage.spec.ts`).
- [x] `npm run verify` passes (web 185 tests, api 312, yijing-core 55).

## Implementation note (2026-08-28)

- `HexagramLines.vue`: `interactive` (default false) + `selectedPosition` props, `select` emit.
  Rows use `<component :is="interactive ? 'button' : 'div'">` — same `data-*` + bar markup;
  interactive rows get `type="button"`, `aria-label` "Line N", `aria-pressed`, a
  `.hexagram-line-selected` class (bar → `--p-primary-color`) and a `:focus-visible` outline.
  The wrapper drops `role="img"` for `role="group"` only when interactive so the buttons are
  reachable by AT.
- `HexagramDetailPage.vue`: `selectedLine` ref (reset in the route-param watch), `toggleLine`,
  `selectedLineText` computed. `<HexagramLines>` gets `interactive` only when
  `lineStatements !== null`. Inline panel (bordered box: "Line N" heading + text + `pi-times`
  close) below the diagram; bottom-list `<li>` gets `.line-text-selected`
  (`--p-primary-color` bg, `--p-primary-contrast-color` text). `@keydown.esc` on the content
  wrapper clears it.
