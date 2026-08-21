# SPEC-024 — Personal Statistics

**Status:** verified
**Owner:** unassigned
**Last updated:** 2026-08-21

## Problem

Feature 3 of the plan's next batch asks for personal statistics — hexagram frequency, yin/yang
ratio, most frequent tags — over a user's own consultation history. SPEC-022 and SPEC-023 both
surfaced patterns *within* individual consultations or a single page's list; nothing yet
aggregates *across* the whole history into a single view. All the underlying data
(`primary_king_wen_number`, tags) already exists in `consultations`/`tags`/`consultation_tags`.

## Purpose

Add a `GET /api/statistics` endpoint that aggregates across every consultation — total count,
per-hexagram cast frequency, aggregate yin/yang line ratio, and per-tag frequency — computed
server-side via SQL `GROUP BY` (not by shipping every consultation to the client to aggregate),
and a `/statistics` page presenting it.

## Scope

- New `App\Readings\StatisticsRepository` interface, `compute(): ConsultationStatistics`, and
  `SqliteStatisticsRepository` implementation.
- Three small readonly value objects: `HexagramFrequency` (`kingWenNumber`, `chineseName`,
  `pinyin`, `count`), `TagFrequency` (`name`, `count`), `ConsultationStatistics`
  (`totalConsultations`, `hexagramFrequency: list<HexagramFrequency>`, `yinLineCount`,
  `yangLineCount`, `tagFrequency: list<TagFrequency>`).
- `hexagramFrequency`: one entry per **distinct primary hexagram actually cast**, count = how
  many consultations had it as their primary hexagram, sorted by count descending then King Wen
  number ascending (stable tie-break). Hexagrams never cast are simply absent — not zero-padded
  to all 64.
- `yinLineCount`/`yangLineCount`: total yin/yang lines across every consultation's primary
  hexagram's full 6 lines (all lines, not only changing ones) — the "as cast" hexagram, not the
  resulting one. See "Out of scope" for why resulting hexagrams and changing-only lines are
  excluded.
- `tagFrequency`: one entry per distinct tag name, count = how many consultations carry it,
  sorted by count descending then name ascending.
- `GET /api/statistics` — new, unauthenticated (matches every other endpoint), read-only
  endpoint, `StatisticsController::index()`.
- `apps/web`: `entities/statistics/{model.ts,api.ts}`, `pages/statistics/StatisticsPage.vue` at
  route `/statistics`, linked from the main nav (`App.vue`).
- `StatisticsPage` renders: total consultation count; a simple bar-style list of hexagram
  frequency (number, Chinese name, pinyin, count); a yin/yang ratio (counts and computed
  percentage); a list of tag frequency (name, count). An explicit empty-history message when
  `totalConsultations` is `0`.

## Out of scope

- **Resulting-hexagram frequency or a separate resulting-hexagram yin/yang count.** The plan
  names "hexagram frequency" and "yin/yang ratio" without specifying primary vs. resulting; this
  spec picks the primary (as-cast) hexagram consistently for both, matching what
  [SPEC-023](../repeated-pattern-detection/spec.md) already established as the natural "this is
  the hexagram this consultation is about" default. Resulting-hexagram statistics are a natural,
  separately-requestable follow-up, not silently folded in here.
- **Counting only changing lines toward the yin/yang ratio.** A ratio over just the (typically 0-
  6, often few) changing lines per consultation would be dominated by consultations with more
  changing lines; counting every one of the 6 lines per primary hexagram gives every consultation
  equal weight, which is the more meaningful "ratio" reading.
- **Time-series / trend-over-time statistics** (e.g. "your yin/yang ratio this month vs. last").
  This spec computes one aggregate snapshot over the full history, not a windowed or trended one.
- **Statistics scoped to a tag or date range** (e.g. "hexagram frequency just for 'career'-tagged
  consultations"). One global aggregate only; filtering is a separate, larger feature.
- **Caching or pre-computing the aggregate.** At this app's personal scale, `GROUP BY` over the
  full `consultations` table on every request is cheap; no materialized view or cache layer is
  introduced.

## User behavior

```
GET /api/statistics
  -> {
       "totalConsultations": 12,
       "hexagramFrequency": [
         {"kingWenNumber": 1, "chineseName": "乾", "pinyin": "Qián", "count": 3},
         {"kingWenNumber": 2, "chineseName": "坤", "pinyin": "Kūn", "count": 2}
       ],
       "yinYangRatio": {"yin": 34, "yang": 38},
       "tagFrequency": [
         {"name": "career", "count": 5},
         {"name": "relationships", "count": 3}
       ]
     }

/statistics
  -> "12 consultations" · hexagram frequency list · "34 yin / 38 yang (47% / 53%)" · tag
     frequency list

No consultations yet
  -> "No consultations yet — nothing to show statistics for."
```

## Functional requirements

- **REQ-STATS-001** — `GET /api/statistics` MUST return `totalConsultations`,
  `hexagramFrequency`, `yinYangRatio` (`{yin, yang}`), and `tagFrequency`.
- **REQ-STATS-002** — `hexagramFrequency` MUST include exactly one entry per distinct primary
  hexagram actually cast at least once, with an accurate count, sorted by count descending then
  King Wen number ascending.
- **REQ-STATS-003** — `yinYangRatio.yin` + `yinYangRatio.yang` MUST equal
  `totalConsultations * 6` (every consultation contributes exactly its primary hexagram's 6
  lines).
- **REQ-STATS-004** — `tagFrequency` MUST include exactly one entry per distinct tag name in use,
  with an accurate count of how many consultations carry it, sorted by count descending then name
  ascending.
- **REQ-STATS-005** — With zero consultations, the endpoint MUST return `totalConsultations: 0`,
  `hexagramFrequency: []`, `yinYangRatio: {"yin": 0, "yang": 0}`, `tagFrequency: []` — not an
  error.
- **REQ-STATS-006** — `StatisticsPage` MUST render total count, hexagram frequency, yin/yang
  ratio, and tag frequency from a single `GET /api/statistics` call.
- **REQ-STATS-007** — `StatisticsPage` MUST show a distinct empty-history message instead of
  empty lists/zeroes when `totalConsultations` is `0`.
- **REQ-STATS-008** — The main nav (`App.vue`) MUST link to `/statistics`.

## Non-functional requirements

- **REQ-STATS-009** — Hexagram-frequency and tag-frequency aggregation MUST be computed via SQL
  `GROUP BY`, not by loading every full `Consultation` (with its notes/tags/outcome/follow-up
  resolution) into PHP first.
- **REQ-STATS-010** — No component outside `entities/statistics` may call `apiGet` directly for
  this data.

## Data requirements

None — no schema change. Reads existing `consultations`, `tags`, `consultation_tags` columns.

## API requirements

One new endpoint: `GET /api/statistics`, no query parameters, always `200`.

## Edge cases

- An "orphaned" tag with zero consultations attached can't occur — no delete path exists for
  either consultations or tags in this app, so every row in `tags` is always joinable back to at
  least the consultation that created it.
- Every consultation sharing one primary hexagram → `hexagramFrequency` has exactly one entry,
  count equal to `totalConsultations`.
- A consultation whose primary hexagram is all-yang (hexagram 1) contributes 6 to `yang`, 0 to
  `yin`; all-yin (hexagram 2) the reverse.

## Acceptance criteria

- [x] `GET /api/statistics` returns all four fields with correct aggregate values against real
      seeded data.
- [x] Zero consultations → all-zero/empty response, `200`, not an error.
- [x] `yin + yang` always equals `totalConsultations * 6`.
- [x] `/statistics` renders the four sections; shows the empty-history message when there's
      nothing yet.
- [x] Nav includes a working link to `/statistics`.
- [x] `npm run verify` passes end to end.
- [x] Manually verified against the real running API/UI with genuine seeded consultation data.
