# Plan — Hexagram Comparison (SPEC-017)

**Depends on spec status:** `approved`

## Technical approach

- `packages/yijing-core/src/LineComparison.php` — new readonly value object:
  `__construct(int $position, LinePolarity $aPolarity, LinePolarity $bPolarity, bool $changed)`,
  mirroring `Line`'s style.
- `packages/yijing-core/src/HexagramComparator.php` — new stateless class (mirrors
  `YijingRelations`): `compareLines(Hexagram $a, Hexagram $b): list<LineComparison>` —
  `array_map` over `$a->lines`/`$b->lines` pairwise (both always exactly 6, position-ordered, per
  `Hexagram::fromLines()`'s existing guarantee), comparing polarity per position.
- `HexagramController::compare(Request $request): Response`:
  - Parse `a`/`b` from `$request->query->get()`; non-numeric/missing → `422`.
  - `Hexagram::fromKingWenNumber((int) $a)` / `(int) $b`, catching `\InvalidArgumentException` →
    `404` (same pattern `show()` already uses).
  - Build response: `{'a' => $this->toJson($hexA), 'b' => $this->toJson($hexB), 'lineComparisons'
    => ..., 'upperTrigramDiffers' => ..., 'lowerTrigramDiffers' => ...}` — `toJson()` is the
    exact method `index()`/`show()` already use, so `relationships`/text come along for free.
  - `upperTrigramDiffers`/`lowerTrigramDiffers`: `$hexA->getUpperTrigram()->id !==
    $hexB->getUpperTrigram()->id` (enum comparison) — a direct comparison of two already-computed
    domain values, not a new calculation, so it stays inline in the controller rather than adding
    another domain method for a one-line boolean.
- Route: `GET /api/hexagrams/compare`, registered alongside the other `/api/hexagrams/*` routes.
- `entities/hexagram/model.ts`: `LineComparison`, `HexagramComparison { a: Hexagram; b: Hexagram;
  lineComparisons: LineComparison[]; upperTrigramDiffers: boolean; lowerTrigramDiffers: boolean }`.
- `entities/hexagram/api.ts`: `compareHexagrams(a: number, b: number): Promise<HexagramComparison>`.
- `HexagramComparePage.vue`:
  - Reads `a`/`b` from `useRoute().query` (`ref`s, `watch`ed together like a single fetch trigger,
    same reactive-fetch shape established in SPEC-015/016), defaulting to `1`/`2` if absent.
  - A small form (two number inputs, "Compare" button) lets the user change `a`/`b`, updating the
    route's query via `router.push` (so the comparison is bookmarkable/shareable, and a
    consultation's "Compare hexagrams" link is just a normal navigation into this same page).
  - Two `HexagramLines` diagrams (undecorated — no `changing` prop passed).
  - A `<table>` of `lineComparisons`, one row per position (6 → 1, matching the top-to-bottom
    convention used elsewhere), highlighting `changed` rows.
  - A `computed` deriving the structural-relationship note: checks
    `comparison.a.relationships.nuclear/reversed/complement.kingWenNumber ===
    comparison.b.kingWenNumber` (and the same check with `a`/`b` swapped) — equality checks only,
    matching `HexagramDetailPage`'s existing `isSelf`-style pattern (SPEC-015), not new math.
  - Judgment/image shown for both `a`/`b` directly from the already-fetched response, each with a
    "View full page" link (same pattern as `HexagramEditorPage`, SPEC-016).
- `ConsultationPage.vue`: one new `router-link` near the existing hexagram diagrams, `:to`
  computed from `state.consultation.primaryHexagram.kingWenNumber`/`resultingHexagram.kingWenNumber`.

## Architecture decisions

- **`a=b` is not special-cased or rejected.** Comparing a hexagram to itself is a degenerate but
  valid case (REQ-HEXCMP-005) — rejecting it would be an arbitrary restriction nothing asked for.
- **Trigram-difference booleans computed inline in the controller, not a new domain method.**
  Comparing two already-known `TrigramId` enum values for equality isn't a "hexagram calculation"
  in the sense the plan is protecting against (pattern→number lookup, trigram derivation) — it's
  a one-line equality check, consistent with `relationshipsToJson()`'s existing inline style.
- **Query-param-driven page, not path params.** Two independent, changeable, optional-with-
  sensible-defaults values fit query params better than `/hexagrams/compare/:a/:b`; also makes
  the URL directly shareable/bookmarkable, which a "Compare hexagrams" link from a consultation
  benefits from.

## Affected areas

- `packages/yijing-core/src/LineComparison.php` (new)
- `packages/yijing-core/src/HexagramComparator.php` (new)
- `packages/yijing-core/tests/HexagramComparatorTest.php` (new)
- `apps/api/src/Hexagrams/HexagramController.php`
- `apps/api/config/routes.php`
- `apps/api/tests/Hexagrams/HexagramControllerTest.php`
- `apps/web/src/entities/hexagram/model.ts`
- `apps/web/src/entities/hexagram/api.ts`
- `apps/web/src/entities/hexagram/api.spec.ts`
- `apps/web/src/pages/hexagrams/HexagramComparePage.vue` (new)
- `apps/web/src/pages/hexagrams/HexagramComparePage.spec.ts` (new)
- `apps/web/src/pages/consultations/ConsultationPage.vue`
- `apps/web/src/pages/consultations/ConsultationPage.spec.ts`
- `apps/web/src/router` (new route)

## Data / schema changes

None.

## Risks / open questions

- None currently open.
