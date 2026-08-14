# SPEC-018 — Deep Hexagram Page

**Status:** verified
**Owner:** unassigned
**Last updated:** 2026-08-15

## Problem

Feature 25 of the plan's next batch asks for a detailed hexagram page with: number, name,
Chinese name, symbol, upper/lower trigram, Judgment, Image, all six line texts, translations,
sources, and related hexagrams — with canonical text, translations, commentaries, and AI
interpretations kept visually separate. Auditing `HexagramDetailPage` (SPEC-007/015) against this
list finds most of it already done — but two real gaps:

1. **All six line texts are missing.** `state.hexagram.lineStatements` has been returned by the
   API since SPEC-002/003 and is fully populated (Legge's line-by-line commentary), but the page
   never renders it — only `judgment`/`image` are shown. This is a genuine content gap, not a
   design decision.
2. **No hexagram symbol character.** The page shows the line diagram and both trigrams' Unicode
   symbols (☰/☷/etc.), but never the hexagram's own single Unicode glyph (U+4DC0–U+4DFF, e.g. ䷊
   for hexagram 11). That block is Unicode-standard-ordered exactly by King Wen sequence
   (confirmed: U+4DC0 = hexagram 1 "The Creative Heaven"/Qian ... U+4DFF = hexagram 64 "Before
   Completion"; codepoint = `0x4DC0 + kingWenNumber − 1`), so this is a trivial, verifiable,
   zero-new-data computation — not something requiring sourcing.
3. **No explicit source attribution in the UI.** The project has stated "James Legge, 1899,
   public domain" in `README.md`/specs throughout, but a reader looking only at
   `/hexagrams/{n}` has no way to know where the Judgment/Image/line text came from.

"Name" and "translations" need honest scoping, not new fabricated content:

- **"Name"** is already shown as `pinyin` (e.g. "Tài") — no separate English title exists in the
  sourced Legge data to add without inventing one (Legge himself mostly transliterates rather
  than titling hexagrams in English), so pinyin stays the answer to "name" here.
- **"Translations" (plural, comparable)** genuinely needs a second real, public-domain
  translator's text — data this session doesn't have, parsed, and verified, the same way Legge's
  was (SPEC-002's dedicated parsing/cross-checking pass). Inventing placeholder or LLM-generated
  "translations" would violate this project's explicit rule (plan feature 27: "Do not use
  LLM-generated text as canonical source data") and this repo's own established practice of only
  shipping independently cross-checked classical text. **This spec explicitly does not attempt
  translation comparison — that's feature 26's job, and it's blocked pending a real second
  source**, flagged to the user rather than silently skipped or faked.

## Purpose

Close the two genuine content gaps (line texts, hexagram symbol) and add source attribution to
`HexagramDetailPage`, using only data and computations already available or trivially/verifiably
derivable — no new canonical text sourcing in this spec.

## Scope

- `Hexagram::symbol(): string` in `packages/yijing-core` — new computed method,
  `mb_chr(0x4DC0 + $this->kingWenNumber - 1)`. Deterministic, verified against the Unicode
  standard's own character names (cross-checked hexagrams 1, 11, 29, 44, 54 against this
  session's own already-verified King Wen data).
- `HexagramController::toJson()` gains a `symbol` field using the new method.
- `HexagramDetailPage.vue`:
  - Renders the hexagram's `symbol` next to its number/Chinese name/pinyin.
  - Renders all six `lineStatements`, one per line position (bottom to top, matching every other
    line-ordered display in this app), each labeled by position.
  - Adds a single, small source-attribution line ("Source: James Legge's *The I Ching* (1899),
    public domain") near the canonical text section — visually distinct from (below) the
    Judgment/Image/line-text content it describes, not interleaved with it.
- `entities/hexagram/model.ts`'s `Hexagram` type gains `symbol: string`.

## Out of scope

- **Translation comparison (feature 26).** Blocked on sourcing and parsing a second real,
  public-domain translation — not attempted here. Flagged to the user as the natural next step
  once real source material is available, not silently skipped.
- **An English hexagram title distinct from pinyin.** No verified source for one exists in this
  project's data; not inventing one.
- **Commentaries** (traditional commentary beyond Legge's own judgment/image text) and **AI
  interpretations** on this page — both explicitly plan items for later specs (consultation-
  scoped AI interpretation already exists, SPEC-008/010; a hexagram-scoped traditional commentary
  layer is new scope nothing has asked for standalone from a specific hexagram page yet).
- **Any change to `HexagramComparePage`/`HexagramEditorPage`.** Their own already-fetched
  `judgment`/`image` display stays as-is; this spec only touches `HexagramDetailPage`.

## User behavior

```
GET /api/hexagrams/11
  -> 200, existing JSON plus "symbol": "䷊"

/hexagrams/11
  -> heading now shows the ䷊ glyph alongside "11. 泰 (Tài)"
  -> six "Line 1" .. "Line 6" entries render Legge's line-by-line commentary (previously absent)
  -> "Source: James Legge's The I Ching (1899), public domain" appears below the canonical text
```

## Functional requirements

- **REQ-DEEPHEX-001** — `GET /api/hexagrams/{id}` and `GET /api/hexagrams` MUST include a
  `symbol` field: `mb_chr(0x4DC0 + kingWenNumber - 1)`.
- **REQ-DEEPHEX-002** — `HexagramDetailPage` MUST render all six entries of `lineStatements`
  when non-null, each labeled with its line position (1-6).
- **REQ-DEEPHEX-003** — `HexagramDetailPage` MUST render the hexagram's `symbol` character.
- **REQ-DEEPHEX-004** — `HexagramDetailPage` MUST show a source-attribution line for the
  canonical text, visually separate from (not interleaved with) the Judgment/Image/line-text
  content itself.
- **REQ-DEEPHEX-005** — `lineStatements` being `null` (should not currently occur — SPEC-002
  populated all 64 — but the type remains nullable) MUST NOT break rendering; show the same
  `NOT_AVAILABLE` placeholder pattern already used for `judgment`/`image`.

## Non-functional requirements

- **REQ-DEEPHEX-006** — No new canonical-text data sourced or fabricated in this spec — the
  `symbol` computation is pure Unicode-standard arithmetic (independently verifiable, not
  "content"), and line texts already exist in the database from SPEC-002.

## Data requirements

None — no schema/persistence change.

## API requirements

`GET /api/hexagrams/{id}` and `GET /api/hexagrams` — response shape gains `symbol`. No other
endpoint changes.

## Edge cases

- Hexagram 1 → `symbol` `䷀` (U+4DC0). Hexagram 64 → `䷿` (U+4DFF). Both ends of the range spot-
  checked, not just an arbitrary middle value.

## Acceptance criteria

- [x] `GET /api/hexagrams/{id}` includes the correct `symbol` for at least hexagrams 1, 11, 44,
      54, and 64 (spot-checked against the Unicode standard's own character names).
- [x] `/hexagrams/{n}` renders the hexagram's symbol character.
- [x] `/hexagrams/{n}` renders all six line texts, labeled by position.
- [x] `/hexagrams/{n}` shows a visually distinct source-attribution line.
- [x] `npm run verify` passes end to end.
- [x] Manually verified against the real running API/UI.
- [x] The user has been told, in this response (not silently omitted), that translation
      comparison (feature 26) is blocked pending a real second public-domain source.
