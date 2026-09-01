# SPEC-053 — Line Dynamics (Position, Centrality, Correspondence, Riding/Receiving)

**Status:** implemented
**Owner:** unassigned
**Last updated:** 2026-08-28

## Problem

The app models the 64 hexagrams structurally and shows the classical line texts, but not the
*relationships between lines within a hexagram* — the analytical grammar every commentary since
Wang Bi uses to read a hexagram:

- **Correctness of position (當位 / zhèng)** — a line is "correct" when a yang line sits in an
  odd position (1, 3, 5) or a yin line in an even position (2, 4, 6); otherwise it "occupies
  its place improperly" (失位).
- **Centrality (中 / zhōng)** — positions 2 and 5 are central (the middle of the lower / upper
  trigram); a line that is both central and correct (中正) — a yin line at 2, or a yang line
  at 5 — is the most favourable placement.
- **Correspondence (應 / yìng)** — the pairs (1–4), (2–5), (3–6) "correspond" when the two
  lines are of *opposite* polarity; same polarity is "no correspondence" (敵應). The 2–5 pair
  (subject and ruler) is the one that matters most.
- **Riding / receiving (乘 / 承)** — a *yin* line directly **above** a yang line "rides the
  firm" (乘剛), generally inauspicious; a *yin* line directly **below** a yang line "receives /
  supports" it (承), generally favourable.

All four are pure functions of the six line polarities. Surfacing them turns the hexagram page
from a text lookup into an analysis tool.

## Purpose

Compute these four line relationships in `yijing-core`, expose them on the single-hexagram API
response, and render them on the hexagram detail page as a per-line table plus a correspondence
summary — with the Chinese terms shown for the reader who knows them.

## Scope

### `packages/yijing-core`

- `Yijing\Core\LineDynamic` — readonly, one per line:
  - `int $position` (1–6)
  - `bool $correctPosition` — 當位
  - `bool $central` — 中 (position 2 or 5)
  - `bool $centralAndCorrect` — 中正 (`central && correctPosition`)
  - `int $correspondsWith` — the partner position (1↔4, 2↔5, 3↔6)
  - `bool $corresponds` — 正應 (partner is opposite polarity)
  - `bool $ridesFirmBelow` — this line is yin and the line at `position - 1` is yang (乘剛)
  - `bool $supportsFirmAbove` — this line is yin and the line at `position + 1` is yang (承)
  - `toArray(): array`
- `Yijing\Core\LineDynamics` — readonly:
  - `list<LineDynamic> $lines` (6, position order)
  - `public static function of(Hexagram $hexagram): self`
  - `toArray(): list<array<string, mixed>>` (just the six `LineDynamic` arrays)
- Pure — no I/O, no catalog. Nothing about the ruling line (卦主), which needs a sourced
  per-hexagram table — its own spec.

### API

- `GET /api/hexagrams/{id}` gains `lineDynamics: LineDynamic[]` (6 entries) via
  `LineDynamics::of($hexagram)->toArray()`. The list endpoint `GET /api/hexagrams` is
  **unchanged** — this is detail-only, so the 64-item payload doesn't grow.
- `from-lines` (`GET /api/hexagrams/from-lines`, the editor's live preview) also gains it — it
  already returns the same single-hexagram `toJson` shape, and the editor is exactly where
  seeing position/centrality/correspondence update live is useful.

### Frontend

- `entities/hexagram/model.ts`: `LineDynamic` type; `Hexagram` gains
  `lineDynamics?: LineDynamic[]` (optional — only the detail / from-lines responses carry it;
  the list response and its consumers are untouched).
- `HexagramDetailPage.vue`: a "Line dynamics" section (after "Line Texts") with:
  - a **correspondence summary**: the three pairs, each "Lines 2 & 5 — correspond (正應)" or
    "— no correspondence (敵應)", the 2–5 pair emphasised.
  - a **per-line table**: row per position (6 → 1, top to bottom to match the diagram), columns
    Position · Line (yin/yang) · Position (correct 當位 / improper 失位) · Central (中 / 中正 /
    —) · Corresponds with line N (正應 / 敵應) · Adjacency (rides 乘剛 / supports 承 / —).
  - A one-line note explaining the notation, linked to nothing (static help text).
- Localised (en + uk): section title, the column headers, and the term labels — each pairs an
  English phrase with the Chinese term in parentheses, e.g. "Correct (當位)".

## Out of scope

- **The ruling line (卦主 / 成卦之主 / 主卦之主).** Traditionally assigned per hexagram by
  commentators; a correct implementation needs a transcribed 64-entry table (like the classical
  text catalog). Separate spec.
- **Rendering line dynamics on the consultation page**, feeding them into the AI prompt, or
  cross-linking from the reading-guidance panel (SPEC-052). Natural follow-ups; not here.
- **Adjacency (比) as its own signal** beyond the ride/support distinction — 比 without the
  yin/yang contrast carries little interpretive weight and would just be "every line touches
  its neighbours".
- **Trigram-level dynamics** (e.g. which trigram "advances"), the nuclear-trigram reading, or
  seat-of-the-line symbolism (1 = feet, 6 = head).
- **Changing the hexagram list payload.**

## Functional requirements

- **REQ-LD-001** — `LineDynamic.correctPosition` is true exactly when (position ∈ {1,3,5} and
  yang) or (position ∈ {2,4,6} and yin).
- **REQ-LD-002** — `central` is true for positions 2 and 5 only; `centralAndCorrect` is
  `central && correctPosition`.
- **REQ-LD-003** — `correspondsWith` maps 1↔4, 2↔5, 3↔6; `corresponds` is true when that
  partner line has the opposite polarity.
- **REQ-LD-004** — `ridesFirmBelow` is true only for a yin line whose lower neighbour is yang;
  `supportsFirmAbove` is true only for a yin line whose upper neighbour is yang; both false for
  every yang line and at the hexagram's ends where there is no such neighbour.
- **REQ-LD-005** — `GET /api/hexagrams/{id}` and `GET /api/hexagrams/from-lines` include
  `lineDynamics` (6 entries, position order); `GET /api/hexagrams` does not.
- **REQ-LD-006** — The hexagram detail page shows a correspondence summary (3 pairs) and a
  per-line dynamics table using the computed values, with Chinese terms shown.

## Non-functional requirements

- **REQ-LD-020** — `LineDynamics` is pure and fully unit-tested in `yijing-core` (a hexagram
  where every line is correct, one where none is, the correspondence and ride/support cases).
- **REQ-LD-021** — `phpstan` level 8 + `php-cs-fixer` clean in `yijing-core` and `apps/api`.
- **REQ-LD-022** — New UI strings localised (en + uk); `npm run verify` passes; existing
  `Hexagram`-fixture specs stay green (the model field is optional).

## Data requirements

None. Everything is derived from the six line polarities.

## API requirements

- `GET /api/hexagrams/{id}` → response gains `lineDynamics: [{ position, correctPosition,
  central, centralAndCorrect, correspondsWith, corresponds, ridesFirmBelow, supportsFirmAbove }]`.
- `GET /api/hexagrams/from-lines` → same addition.
- `GET /api/hexagrams` → unchanged.

## Edge cases

- Hexagram 63 (既濟, Ji Ji) — the only hexagram where **every** line is correct (yang at
  1/3/5, yin at 2/4/6) and all three pairs correspond: a good positive test fixture.
- Hexagram 64 (未濟, Wei Ji) — every line is in the "wrong" place; all pairs still correspond
  (opposite polarity): the mirror fixture.
- Positions 1 and 6 have only one neighbour → `ridesFirmBelow` is always false at position 1,
  `supportsFirmAbove` always false at position 6.
- A yang line is never "riding" or "supporting" in this model (the relationship is described
  from the yin line); both flags false for all yang lines.

## Acceptance criteria

- [x] `LineDynamics::of()` returns the correct four relationships for hexagram 63 (all correct,
      all correspond) and 64 (none correct, all correspond), plus a ride and a support case.
- [x] `GET /api/hexagrams/{id}` and `.../from-lines` include `lineDynamics`; `GET /api/hexagrams`
      does not.
- [x] The hexagram detail page shows the correspondence summary and the per-line table with
      Chinese terms.
- [x] `npm run verify` passes; the existing hexagram-fixture specs are untouched.
