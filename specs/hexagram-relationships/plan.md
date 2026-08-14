# Plan — Complete Hexagram Relationships (SPEC-014)

**Depends on spec status:** `approved`

## Technical approach

- `HexagramController::toJson()` gains a `relationships` key, built by a new private
  `relationshipsToJson(Hexagram $hexagram): array` that calls the three existing
  `YijingRelations` static methods and reduces each result through the *existing*
  `hexagramSummaryToJson()`-shaped helper — copy the 3-field `{kingWenNumber, chineseName,
  pinyin}` shape `ConsultationController::hexagramToJson()` already uses (same fields, same
  order), duplicated locally rather than shared cross-module (mirrors the plan's own precedent
  in SPEC-003 of not sharing `trigramToJson()` between `Hexagrams`/`Trigrams` — the ~5 lines
  aren't worth a cross-controller dependency).
- Field naming translates the domain's historical method names to the plan's vocabulary exactly
  once, in this one mapping:
  - `relationships.nuclear` ← `YijingRelations::getNuclearHexagram($hexagram)`
  - `relationships.reversed` ← `YijingRelations::getOpposite($hexagram)` (domain method is named
    `getOpposite` for historical reasons; semantically this is the line-reversed/綜卦 hexagram)
  - `relationships.complement` ← `YijingRelations::getComplement($hexagram)` (polarity-flipped/
    錯卦 — this is what the feature plan calls "opposite/complementary")
- Both `index()` and `show()` route through the same `toJson()`, so both automatically gain
  `relationships` — no separate wiring needed.
- `Hexagram::fromKingWenNumber($id)` already builds a full `Hexagram` with all 6 lines
  (`changing: false` throughout, irrelevant here since `YijingRelations` ignores `changing`) —
  no new construction path needed; `YijingRelations`'s methods take a `Hexagram` directly.

## Architecture decisions

- **No new `YijingRelations` methods, no renaming existing ones.** `getOpposite()` keeps its
  current name in the domain layer (renaming would touch `YijingRelationsTest` and any other
  caller for a purely cosmetic reason) — the vocabulary translation happens exactly once, in
  `HexagramController`'s JSON mapping, which is the only place that needs the plan's naming.
- **`relationships` folded into the existing hexagram response, not a new endpoint.** Per the
  spec's "Out of scope" — three cheap, always-wanted-together summaries don't justify a second
  round-trip.
- **`index()` also computes relationships for all 64 hexagrams per request.** Each
  `YijingRelations` call constructs a `Hexagram` via `fromLines()`, which does a King Wen pattern
  lookup (`HexagramCatalog`, an in-memory array) — no I/O, negligible cost for 64 × 3 calls; not
  worth special-casing `index()` to skip relationships.

## Affected areas

- `apps/api/src/Hexagrams/HexagramController.php` — add `relationships` to `toJson()`.
- `apps/api/tests/Hexagrams/HexagramControllerTest.php` — new assertions.
- `apps/web/src/entities/hexagram/model.ts` — add `relationships: { nuclear, reversed,
  complement: HexagramSummary }` to the `Hexagram` type (reuse the existing `HexagramSummary`
  type already defined in `entities/consultation/model.ts` — or a local equivalent if importing
  across entity boundaries isn't already established practice; check before adding a new type).
- `apps/web/src/entities/hexagram/api.spec.ts` — extend fixture/assertions to cover the new
  field round-tripping through `fetchHexagram()`/`fetchHexagrams()`.

## Data / schema changes

None.

## Risks / open questions

- None currently open.
