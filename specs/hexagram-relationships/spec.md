# SPEC-014 — Complete Hexagram Relationships

**Status:** verified
**Owner:** unassigned
**Last updated:** 2026-08-15

## Problem

`packages/yijing-core`'s `YijingRelations` already computes the three relationships intrinsic to
any single hexagram — nuclear (互卦), line-reversed (綜卦), and polarity-flipped complement
(錯卦) — and `Hexagram::changeLine()`/`getResultingHexagram()` already compute the fourth,
consultation-scoped relationship (transformation via changing lines). All four are tested at the
domain level (`YijingRelationsTest`, `HexagramTest`). But `GET /api/hexagrams/{id}` — the only
place a hexagram's data reaches the frontend today — returns none of them. A future Hexagram
Explorer (feature 22) that wants to let a user navigate from one hexagram to its nuclear/reversed/
complement hexagram would otherwise have to either add a second API round-trip per relationship
or re-derive King Wen numbers in Vue — duplicating domain logic the backend already has.

## Purpose

Expose the three hexagram-intrinsic relationships (nuclear, reversed, complement) as part of
`GET /api/hexagrams/{id}`'s response, computed by the existing domain layer — no new domain
logic, no relationship logic in the frontend.

## Scope

- `HexagramController::show()` (and `index()`, for consistency) additionally returns a
  `relationships` object built from the existing `YijingRelations::getNuclearHexagram()`/
  `getOpposite()`/`getComplement()`, each reduced to the same `{kingWenNumber, chineseName,
  pinyin}` summary shape `Consultation`'s JSON already uses for `primaryHexagram`/
  `resultingHexagram` (`ConsultationController::hexagramToJson()`-equivalent) — no line data
  duplicated, since the client already has (or can fetch) the full hexagram by number.
- API field naming resolves the terminology mismatch between the domain layer's historical method
  names and the plan's vocabulary once, here, so nothing downstream has to translate: `complement`
  (═ `YijingRelations::getComplement()`, polarity-flipped, 錯卦 — "opposite/complementary" in the
  plan's words), `reversed` (═ `YijingRelations::getOpposite()`, line-order reversed, 綜卦 —
  "reversed/inverted" in the plan's words), `nuclear` (═ `getNuclearHexagram()`, unchanged
  naming).
- `entities/hexagram` (frontend) gains the `relationships` field on its `Hexagram` type so it
  round-trips through the existing typed API client — no new frontend logic yet (no UI consumes
  it until feature 22).

## Out of scope

- **Resulting hexagram / transformation via changing lines.** Already fully implemented and
  exposed — it's consultation-scoped (a specific casting's chosen changing lines), not a property
  of a hexagram in isolation, and already appears as `Consultation.resultingHexagram` (SPEC-005/
  SPEC-006). Nothing to add here.
- **Any UI for browsing/navigating relationships.** That's feature 22 (Hexagram Explorer),
  building on this spec's API surface.
- **A dedicated `/api/hexagrams/{id}/relationships` endpoint.** The relationship set is small
  (3 summaries), always wanted together with the hexagram itself, and cheap to compute
  (`Hexagram::fromLines()` on 6 already-known lines) — folding it into the existing response
  avoids a second round-trip for no benefit. Revisit only if a future consumer needs relationships
  without the rest of the hexagram payload.
- **Caching/precomputation.** `YijingRelations` calls are pure, deterministic, and cheap (no I/O);
  computing them per-request is fine at this scale, same reasoning as the rest of `yijing-core`.

## User behavior

```
GET /api/hexagrams/11
  -> 200, existing Hexagram JSON, plus:
     "relationships": {
       "nuclear":    { "kingWenNumber": 54, "chineseName": "...", "pinyin": "..." },
       "reversed":   { "kingWenNumber": 12, "chineseName": "...", "pinyin": "..." },
       "complement": { "kingWenNumber": 12, "chineseName": "...", "pinyin": "..." }
     }
```

(Hexagram 11 Tai's reversed and complement both happen to be 12 Pi — a real, documented
coincidence for this particular pair, not a bug: reversed flips line *order*, complement flips
line *polarity*; for Tai's specific pattern (111000) both operations produce the same result.)

## Functional requirements

- **REQ-REL-001** — `GET /api/hexagrams/{id}` and `GET /api/hexagrams` MUST include a
  `relationships` object with exactly three keys: `nuclear`, `reversed`, `complement`.
- **REQ-REL-002** — Each relationship value MUST be computed via the corresponding existing
  `YijingRelations` method (`getNuclearHexagram()`, `getOpposite()`, `getComplement()`) applied to
  `Hexagram::fromKingWenNumber($id)` — no relationship logic duplicated in the controller.
- **REQ-REL-003** — Each relationship value MUST be the `{kingWenNumber, chineseName, pinyin}`
  summary shape, matching `Consultation`'s existing hexagram-summary JSON shape exactly (same
  three keys, same types) so the frontend can reuse its existing `HexagramSummary` type.
- **REQ-REL-004** — A hexagram whose nuclear/reversed/complement is itself (e.g. hexagram 1's
  nuclear is itself) MUST report its own `kingWenNumber` — no special-casing "self" as null or
  omitting the key.

## Non-functional requirements

- **REQ-REL-005** — No new domain logic: this spec adds zero new methods to
  `YijingRelations`/`Hexagram` — it only wires already-tested methods into the API response.

## Data requirements

None — no persistence involved; relationships are derived, not stored.

## API requirements

`GET /api/hexagrams/{id}` and `GET /api/hexagrams` — response shape extended with
`relationships` as described above. `404` behavior for an unknown `id` is unchanged.

## Edge cases

- Hexagram 1 (Qian, all yang): nuclear is itself (documented: an all-yang or all-yin hexagram's
  nuclear is always itself) — `relationships.nuclear.kingWenNumber` MUST be `1`.
- Any hexagram: `reversed` and `complement` MAY coincide (see Tai/Pi example above) — this is
  correct output, not deduplicated or flagged.

## Acceptance criteria

- [x] `GET /api/hexagrams/{id}` response includes `relationships.nuclear/reversed/complement`,
      each the correct King Wen number (spot-checked against `YijingRelationsTest`'s existing
      known pairs: Tai↔Pi nuclear 54, Tai reversed→Pi, Qian complement→Kun).
- [x] `GET /api/hexagrams` (list) includes the same `relationships` object per entry.
- [x] Self-referential relationships (e.g. hexagram 1's nuclear) report their own number, not
      null/omitted.
- [x] `entities/hexagram`'s `Hexagram` type includes `relationships`, verified by a passing
      round-trip test.
- [x] `npm run verify` passes end to end (lint, typecheck, tests, build, phpstan, phpunit for
      both `apps/api` and `packages/yijing-core`).
