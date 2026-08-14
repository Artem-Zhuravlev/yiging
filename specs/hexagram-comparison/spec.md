# SPEC-017 — Hexagram Comparison

**Status:** verified
**Owner:** unassigned
**Last updated:** 2026-08-15

## Problem

Feature 24 of the plan's next batch asks to compare two hexagrams: line-by-line differences,
common/changed lines, upper/lower trigram differences, structural relationships, and relevant
texts — and asks this to also work for a consultation's primary/resulting pair. Nothing today
computes a line-by-line diff between two arbitrary hexagrams; `ConsultationPage` renders its
primary and resulting hexagrams side by side but with no structured comparison (SPEC-009/010).
The relationship data needed ("is B this hexagram's nuclear?") already exists per-hexagram via
SPEC-014's `relationships` field — comparing two hexagrams just needs to line up two already-full
hexagram payloads and add the one genuinely new computation: a position-by-position line diff.

## Purpose

Add a read-only `GET /api/hexagrams/compare?a={n}&b={n}` endpoint returning both hexagrams' full
detail (reusing `HexagramController::toJson()`, so `relationships` and canonical text are
included for both) plus a line-by-line comparison and trigram-difference flags — computed via one
small new domain method, `HexagramComparator::compareLines()`. Add a `/hexagrams/compare` page
that renders it, reachable both standalone (pick any two hexagrams) and from a consultation's
detail page (compare its primary and resulting hexagram in one click).

## Scope

- `HexagramComparator::compareLines(Hexagram $a, Hexagram $b): list<LineComparison>` in
  `packages/yijing-core` — a new, small, pure domain method (position, both polarities, whether
  they differ), mirroring `Line`/`Hexagram`'s existing readonly-value-object style.
- `GET /api/hexagrams/compare?a={n}&b={n}`: `{"a": <full hexagram JSON>, "b": <full hexagram
  JSON>, "lineComparisons": [...], "upperTrigramDiffers": bool, "lowerTrigramDiffers": bool}`.
  Missing/non-numeric `a`/`b` → `422`; numeric but out of `1..64` range → `404` (matches
  `show()`'s existing out-of-range behavior for the same underlying resource).
- "Structural relationships" and "relevant texts" need **no new API fields**: both are already
  present in `a`/`b`'s own full JSON (`relationships`, `judgment`, `image`, `lineStatements` —
  SPEC-002/003/014) — the comparison page reads them directly, the same way `HexagramDetailPage`
  already does (REQ-HEXCMP-004 below keeps this an explicit constraint, not an accident).
- `entities/hexagram` gains `compareHexagrams(a, b): Promise<HexagramComparison>` and the
  `LineComparison`/`HexagramComparison` types.
- New page `HexagramComparePage.vue` at `/hexagrams/compare?a={n}&b={n}`: a small form to change
  which two hexagrams are compared, two `HexagramLines` diagrams, a line-by-line table, a common/
  changed-line count, trigram-difference indicators, and — derived client-side by simple equality
  checks against the already-fetched `relationships` (no relationship math) — which structural
  relationship, if any, connects A and B in either direction.
- `ConsultationPage.vue` gains a "Compare hexagrams" link to
  `/hexagrams/compare?a={primary}&b={resulting}`, satisfying the "should also work for a
  consultation's primary/resulting pair" requirement via composition, not a separate code path.

## Out of scope

- **Comparing more than two hexagrams at once.** The feature asks to "compare two hexagrams,"
  not build an N-way comparison matrix.
- **A dedicated backend endpoint for the consultation pairing.** `/hexagrams/compare?a=X&b=Y`
  already handles any two King Wen numbers, including a consultation's primary/resulting —
  linking to it with the right query params is sufficient; no `GET
  /api/consultations/{id}/compare` is needed.
- **Highlighting comparison state on the shared `HexagramLines` component.** That component's
  `changing` dot already carries a specific, established meaning (a cast's changing line,
  SPEC-004/009/010) — reusing it for "this line differs in the comparison" would conflate two
  different concepts on one visual. The comparison page gets its own line-by-line table instead.

## User behavior

```
GET /api/hexagrams/compare?a=11&b=44
  -> 200, {"a": {...full hexagram 11...}, "b": {...full hexagram 44...},
           "lineComparisons": [
             {"position":1,"aPolarity":"yang","bPolarity":"yang","changed":false},
             {"position":2,"aPolarity":"yang","bPolarity":"yang","changed":false},
             {"position":3,"aPolarity":"yang","bPolarity":"yang","changed":false},
             {"position":4,"aPolarity":"yin","bPolarity":"yang","changed":true},
             {"position":5,"aPolarity":"yin","bPolarity":"yang","changed":true},
             {"position":6,"aPolarity":"yin","bPolarity":"yang","changed":true}
           ],
           "upperTrigramDiffers": true, "lowerTrigramDiffers": false}

GET /api/hexagrams/compare?a=0&b=5
  -> 404 {"error": "Not Found"} (a=0 out of range)

GET /api/hexagrams/compare?a=abc&b=5
  -> 422 {"error": "..."} (a is not numeric)

/hexagrams/compare?a=11&b=44
  -> two diagrams, a 6-row line table (3 unchanged, 3 changed for this pair), "Upper trigrams
     differ" / "Lower trigrams match" indicators, and — only when one of A/B's already-fetched
     `relationships` names the other — a note such as "54 is 11's nuclear hexagram" (checked
     against hexagram 11's own `relationships.nuclear/reversed/complement`, not recomputed).

/consultations/{id} (primary hexagram 11, resulting hexagram 44)
  -> "Compare hexagrams" link -> /hexagrams/compare?a=11&b=44
```

## Functional requirements

- **REQ-HEXCMP-001** — `GET /api/hexagrams/compare?a={n}&b={n}` MUST return, for valid `a`/`b`
  (each a King Wen number `1..64`), `200` with `a`/`b` (each the full hexagram JSON
  `GET /api/hexagrams/{id}` already returns) plus `lineComparisons` (6 entries, position 1-6,
  `aPolarity`/`bPolarity`/`changed`) and `upperTrigramDiffers`/`lowerTrigramDiffers`.
- **REQ-HEXCMP-002** — Missing or non-numeric `a`/`b` MUST respond `422`. A numeric `a`/`b`
  outside `1..64` MUST respond `404` — matching `show()`'s existing behavior for the same
  out-of-range condition on a single hexagram.
- **REQ-HEXCMP-003** — `lineComparisons` MUST be computed via a new pure domain method,
  `HexagramComparator::compareLines()`, in `packages/yijing-core` — not inline array logic in the
  controller, and not duplicated in `apps/web`.
- **REQ-HEXCMP-004** — The comparison page MUST derive "structural relationship" and "relevant
  texts" display purely from `a`/`b`'s already-returned `relationships`/`judgment`/`image`/
  `lineStatements` fields — no new relationship or text-lookup logic anywhere in `apps/web`.
- **REQ-HEXCMP-005** — `a=b` (comparing a hexagram to itself) MUST succeed normally: `200`, all 6
  `lineComparisons` entries `changed: false`, `upperTrigramDiffers`/`lowerTrigramDiffers` both
  `false` — not a special-cased error.
- **REQ-HEXCMP-006** — `ConsultationPage` MUST link to `/hexagrams/compare?a={primary King Wen
  number}&b={resulting King Wen number}` for its already-loaded consultation.

## Non-functional requirements

- **REQ-HEXCMP-007** — No changes to the shared `HexagramLines` component's existing `changing`
  semantics (SPEC-004/009/010) — the comparison page renders its own line-by-line table rather
  than repurposing that indicator.

## Data requirements

None — no persistence, matching every other `HexagramController` endpoint.

## API requirements

`GET /api/hexagrams/compare` — see "User behavior"/"Functional requirements" above. No other
endpoint's behavior changes.

## Edge cases

- `a=b` → succeeds, all lines unchanged (REQ-HEXCMP-005).
- A comparison where A's nuclear happens to equal B, but B's nuclear does *not* equal A (nuclear
  is not generally self-inverse, unlike reversed/complement) → the page shows the relationship
  only in the direction it actually holds (checked both ways, not assumed symmetric).

## Acceptance criteria

- [x] `GET /api/hexagrams/compare?a=11&b=44` returns both full hexagrams plus 6 correct
      `lineComparisons` entries and correct trigram-difference flags.
- [x] `a=b` returns a valid `200` with all lines unchanged, not an error.
- [x] Missing/non-numeric `a`/`b` → `422`; out-of-range numeric `a`/`b` → `404`.
- [x] `/hexagrams/compare?a=11&b=44` renders both diagrams, the line table, trigram-difference
      indicators, and correctly shows "54 is 11's nuclear hexagram" when comparing 11 and 54.
- [x] `ConsultationPage` has a working "Compare hexagrams" link using its primary/resulting King
      Wen numbers.
- [x] No hexagram-comparison logic duplicated in `apps/web`.
- [x] `npm run verify` passes end to end.
- [x] Manually verified against the real running API/UI, including the link from an actual
      consultation's detail page.
