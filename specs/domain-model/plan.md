# Plan — I Ching Domain Model (SPEC-002)

**Depends on spec status:** `approved` — spec is now `approved` for a structural implementation
pass; this plan is actionable for that scope.

## Technical approach

Immutable value objects in `Yijing\Core\`, `declare(strict_types=1)`, zero dependencies beyond
PHP:

- `LinePolarity` — backed enum, `Yin`/`Yang`.
- `Line` — readonly value object: position, polarity, changing flag; `withPolarityFlipped()`
  for the pure flip transformation.
- `TrigramId` — enum, 8 cases (Qian, Kun, Zhen, Kan, Gen, Xun, Li, Dui).
- `Trigram` — three `Line`s (positions 1–3) + derived `TrigramId`, built via
  `Trigram::fromLines()` against `Data\TrigramCatalog`'s 3-bit pattern lookup.
- `Hexagram` — six `Line`s (positions 1–6) + King Wen number + Chinese name/pinyin, built via
  `Hexagram::fromLines()` against `Data\HexagramCatalog`'s 6-bit pattern lookup; composes two
  `Trigram`s via `getUpperTrigram()`/`getLowerTrigram()`. Judgment/image/line-statement fields
  are nullable, populated in a later pass.
- `YijingRelations` — stateless static methods operating on `Hexagram` (nuclear, opposite,
  complement).

Reference data lives in `Yijing\Core\Data\TrigramCatalog` / `HexagramCatalog` as plain PHP
arrays (not JSON/files), so `packages/yijing-core` stays filesystem-access-free per REQ-DM-001.

## Architecture decisions

- **Text source resolved:** James Legge's 1899 translation (public domain) for judgment/image/
  line statements — deferred to a follow-up pass, not part of this plan's implementation.
- **King Wen sequence as a static table**, not a computed formula — the traditional ordering has
  no simple closed-form derivation from the six-line pattern; a static table is verified against
  a published reference (e.g. Wikipedia's "List of hexagrams of the I Ching") while being
  written, then locked in by completeness/uniqueness tests plus spot checks against
  independently well-documented hexagrams.
- **Judgment/image/line-statement fields are nullable on `Hexagram`**, not defaulted to
  placeholder strings, so "not yet populated" is never confused with "genuinely empty."

## Affected areas

- `packages/yijing-core/src/LinePolarity.php`
- `packages/yijing-core/src/Line.php`
- `packages/yijing-core/src/TrigramId.php`
- `packages/yijing-core/src/Trigram.php`
- `packages/yijing-core/src/Hexagram.php`
- `packages/yijing-core/src/YijingRelations.php`
- `packages/yijing-core/src/Data/TrigramCatalog.php`
- `packages/yijing-core/src/Data/HexagramCatalog.php`
- `packages/yijing-core/tests/` (Trigram, Hexagram, Line, ChangingLines, YijingRelations)
- `packages/yijing-core/phpstan.neon`, `.php-cs-fixer.php` (tooling parity with `apps/api`)
- `scripts/verify.mjs` (add yijing-core lint/stan steps)

## Data / schema changes

None — this package has no database access (REQ-DM-001).

## Risks / open questions

- Classical text population (judgment/image/6 line statements × 64 hexagrams) is a separate,
  larger follow-up pass — not started here. Tracked in `tasks.md`.
- King Wen sequence table accuracy depends on correctly cross-referencing a public source during
  implementation; the completeness/uniqueness invariant tests catch internal inconsistency
  (duplicates, gaps) but not a wrong-yet-internally-consistent table, so spot checks against
  independently documented hexagrams (1, 2, 11, 12, 63, 64) are also required.
