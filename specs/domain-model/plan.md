# Plan — I Ching Domain Model (SPEC-002)

**Depends on spec status:** `approved` — **spec is currently `draft`, so this plan is not yet
actionable.** Do not implement against it.

## Technical approach (proposed, pending approval)

Immutable value objects in `Yijing\Core\`:

- `Line` — position, polarity, changing flag.
- `Trigram` — three `Line`s + a static lookup table mapping the 8 valid 3-bit patterns to
  trigram identity/metadata.
- `Hexagram` — six `Line`s + a static lookup table mapping the 64 valid 6-bit patterns to King
  Wen number/metadata; composes two `Trigram`s.
- `YijingRelations` — stateless static methods operating on `Hexagram` (nuclear, opposite,
  complement).

Reference data (trigram/hexagram metadata, classical text) proposed as PHP data files (e.g.
`Yijing\Core\Data\Hexagrams`) rather than JSON, so `packages/yijing-core` stays
filesystem-access-free per REQ-DM-001.

## Architecture decisions

To be finalized at approval time. Open question: source/license for judgment, image, and line
statement text (see spec's Data requirements section) blocks writing real reference data.

## Affected areas

- `packages/yijing-core/src/Line.php`
- `packages/yijing-core/src/Trigram.php`
- `packages/yijing-core/src/Hexagram.php`
- `packages/yijing-core/src/YijingRelations.php`
- `packages/yijing-core/src/Data/` (reference data)
- `packages/yijing-core/tests/` (property/table tests for all 64 hexagrams)

## Data / schema changes

None — this package has no database access (REQ-DM-001).

## Risks / open questions

- **Blocking:** classical text source/licensing is unresolved (see spec).
- King Wen sequence lookup could be a computed formula or a static table; a static table is
  simpler to verify against a published reference and is the likely choice, but isn't decided.
