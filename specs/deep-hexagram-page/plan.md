# Plan — Deep Hexagram Page (SPEC-018)

**Depends on spec status:** `approved`

## Technical approach

- `Hexagram::symbol(): string` — new method on the existing readonly `Hexagram` class:
  `mb_chr(0x4DC0 + $this->kingWenNumber - 1)`. `mb_chr()` (not `chr()`) is required since the
  codepoint is well outside the single-byte range.
- `HexagramController::toJson()` — one new line, `'symbol' => $hexagram->symbol()`.
- `HexagramDetailPage.vue`:
  - Symbol rendered inline with the existing `<h1>` (e.g. `<span>{{
    state.hexagram.symbol }}</span> {{ kingWenNumber }}. {{ chineseName }}`).
  - A new section after "Image", `v-for` over `state.hexagram.lineStatements` (reversed for
    display, position 6 first — matching the top-to-bottom convention `HexagramLines`/
    `HexagramComparePage`'s line table already use) with `NOT_AVAILABLE` fallback per the same
    pattern `judgment`/`image` already use — but since `lineStatements` is a nullable *array*
    rather than a nullable *string*, the fallback is a single `v-if="lineStatements === null"`
    guard around the whole section, not per-line (an all-or-nothing field from SPEC-002, never
    partially populated).
  - Attribution line: a small `<p class="text-xs text-neutral-400">` directly after the
    Judgment/Image/line-text block, inside its own wrapping `<div>` so it's structurally (not
    just visually) separate.
- `entities/hexagram/model.ts`: `symbol: string` added to `Hexagram`.

## Architecture decisions

- **`symbol()` computed on `Hexagram`, not stored in `HexagramCatalog`.** Unlike `chineseName`/
  `pinyin` (real transcribed data with no formula), the Unicode symbol is a pure arithmetic
  function of `kingWenNumber` — computing it avoids 64 lines of redundant literal Unicode
  characters and any transcription-error risk.
- **No frontend duplication of the codepoint arithmetic.** Exactly the pattern this whole feature
  batch (21-24) has followed: derived values come from the API, never recomputed in `apps/web`.
- **Translation comparison explicitly deferred, not attempted with placeholder data.** Per the
  spec's "Out of scope" — flagged to the user as a real blocker (needs a second sourced,
  cross-checked, public-domain translation), not silently skipped.

## Affected areas

- `packages/yijing-core/src/Hexagram.php`
- `packages/yijing-core/tests/HexagramTest.php`
- `apps/api/src/Hexagrams/HexagramController.php`
- `apps/api/tests/Hexagrams/HexagramControllerTest.php`
- `apps/web/src/entities/hexagram/model.ts`
- `apps/web/src/pages/hexagrams/HexagramDetailPage.vue`
- `apps/web/src/pages/hexagrams/HexagramDetailPage.spec.ts`
- Every other test fixture with a hand-built `Hexagram` object gains a `symbol` field
  (`HexagramListPage.spec.ts`, `HexagramEditorPage.spec.ts`, `HexagramComparePage.spec.ts`,
  `ConsultationPage.spec.ts`, `entities/hexagram/api.spec.ts`) — mechanical, no behavior change.

## Data / schema changes

None.

## Risks / open questions

- None currently open — the Unicode mapping was independently verified against 5 spot-checked
  codepoints before implementation began.
