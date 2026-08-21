# SPEC-023 — Repeated Pattern Detection

**Status:** verified
**Owner:** unassigned
**Last updated:** 2026-08-21

## Problem

Feature 2 of the plan's next batch asks for repeated-hexagram/changing-line detection — the same
primary hexagram, resulting hexagram, or exact set of changing lines recurring across a user's
own consultation history is meaningful in I Ching practice (a hexagram "following you around"),
but nothing in the app today surfaces it. `primary_king_wen_number`, `resulting_king_wen_number`,
and `changing_line_positions` are already plain, queryable columns on `consultations`
(SPEC-005) — the gap is purely that nothing reads across rows to find matches.

## Purpose

When viewing a single consultation's detail page, show which of the user's other consultations
share its primary hexagram, share its resulting hexagram, or share its exact set of changing
lines — each as a distinct, separately labeled list of links.

## Scope

- `ConsultationRepository` gains three new read methods:
  - `findByPrimaryHexagramNumber(int $kingWenNumber, string $excludeId): list<ConsultationSummary>`
  - `findByResultingHexagramNumber(int $kingWenNumber, string $excludeId): list<ConsultationSummary>`
  - `findByChangingLinePositions(array $positions, string $excludeId): list<ConsultationSummary>`
  - All three exclude the consultation being viewed, ordered newest-first (`created_at DESC`).
- `GET /api/consultations/{id}` (only — see "Out of scope") gains a `repeats` object in its
  response:
  ```json
  "repeats": {
    "primaryHexagram": [{"id": "...", "question": "..."}],
    "resultingHexagram": [{"id": "...", "question": "..."}],
    "changingLines": [{"id": "...", "question": "..."}]
  }
  ```
  Each array is `[]`, never omitted, when there are no matches. `changingLines` is always `[]`
  (no query run) when the viewed consultation itself has zero changing lines — see "Edge cases."
- `ConsultationPage.vue` renders up to three small sections — "Same primary hexagram before",
  "Same resulting hexagram before", "Same changing lines before" — each a list of links to the
  matching consultations, only rendered when its array is non-empty. Nothing renders at all when
  all three are empty (no "no repeats found" noise for first-time or non-matching consultations).

## Out of scope

- **`GET /api/consultations` (the history list) does not gain `repeats`.** Computing this per row
  across an entire list would mean, for the changing-lines pattern specifically, comparing every
  row against every other row — real O(n²) work on top of the notes/tags/outcome/follow-up
  resolution `index()` already does per row. The list is not where this information is useful
  (SPEC-022's timeline is about browsing recency and tags, not repetition); the single-consultation
  detail page is, and that's a `findById()`-scale lookup only. Matches the precedent
  [SPEC-018](../deep-hexagram-page/spec.md) set of scoping an expensive computed field to one
  detail page rather than a list.
- **`create()`/`update()` responses do not gain `repeats` either**, for the same reason — those
  return through the same shared JSON-building code as `index()`, and a newly created or
  freshly-edited consultation reloading its own detail page (`show()`) a moment later already
  gets it. No workflow needs it inline in the create/update response itself.
- **Cross-type matching** (e.g. this consultation's primary hexagram equaling another's
  *resulting* hexagram). Three same-kind comparisons only — primary-to-primary, resulting-to-
  resulting, changing-lines-to-changing-lines — matching exactly what the plan's feature
  description names ("repeated hexagram/changing line detection"), not a general
  every-combination relationship graph.
- **A numeric "how many times has this hexagram appeared" count or statistic.** That's personal
  statistics (a different, later plan feature) — this spec only lists and links the specific past
  consultations, it doesn't aggregate.
- **Limiting or paginating the match lists.** At this app's personal-history scale, matches for
  a single hexagram number are expected to be few; no cap is applied.

## User behavior

```
GET /api/consultations/abc-123
  -> "repeats": {
       "primaryHexagram": [{"id": "def-456", "question": "Should I take the offer?"}],
       "resultingHexagram": [],
       "changingLines": []
     }

/consultations/abc-123
  -> "Same primary hexagram before" section, one link: "Should I take the offer?"
  -> no "Same resulting hexagram before" or "Same changing lines before" section (both empty)

A consultation with zero changing lines
  -> "repeats.changingLines" is always [] — no query even attempted, since "no changing lines"
     matching every other no-changing-lines consultation isn't a meaningful repeated pattern.
```

## Functional requirements

- **REQ-REPEAT-001** — `GET /api/consultations/{id}` MUST include a `repeats` object with
  `primaryHexagram`, `resultingHexagram`, and `changingLines` keys, each a
  `{id, question}[]`, `[]` (not omitted) when empty.
- **REQ-REPEAT-002** — `repeats.primaryHexagram` MUST list every other consultation (excluding
  the one being viewed) whose `primaryHexagram.kingWenNumber` equals this consultation's,
  newest-first.
- **REQ-REPEAT-003** — `repeats.resultingHexagram` MUST list every other consultation whose
  `resultingHexagram.kingWenNumber` equals this consultation's, newest-first.
- **REQ-REPEAT-004** — `repeats.changingLines` MUST list every other consultation whose
  `changingLinePositions` is exactly the same set as this consultation's, newest-first — but MUST
  be `[]` without running a match query when this consultation's own `changingLinePositions` is
  empty.
- **REQ-REPEAT-005** — `GET /api/consultations` (list), `POST /api/consultations` (create), and
  `PATCH /api/consultations/{id}` (update) responses MUST NOT include `repeats`.
- **REQ-REPEAT-006** — `ConsultationPage` MUST render a distinctly labeled, linked list for each
  non-empty `repeats` category, and MUST render nothing for this feature when all three are
  empty.

## Non-functional requirements

- **REQ-REPEAT-007** — Matching relies on `changing_line_positions` being stored as a
  deterministically ordered (ascending-by-position) JSON array — already true of every existing
  row, since `Consultation::changingLinePositions()` derives it from the hexagram's own
  position-ordered lines — so exact-string SQL equality is sufficient; no per-row JSON
  decode/sort comparison in PHP is needed.
- **REQ-REPEAT-008** — No component outside `entities/consultation` may call `apiGet` directly
  for this data.

## Data requirements

None — no schema change. All three new repository methods query existing columns.

## API requirements

`GET /api/consultations/{id}` response gains one new top-level key, `repeats`. No other endpoint,
request shape, or status code changes.

## Edge cases

- A consultation whose changing lines are `[1, 3, 5]` vs. another's `[1, 3, 5]` → match (string-
  equal JSON). `[1, 3, 5]` vs. `[3, 1, 5]` → cannot occur given REQ-REPEAT-007's ordering
  guarantee, so this case doesn't need separate handling.
- A hexagram appearing as this consultation's primary AND as another consultation's primary AND
  that same other consultation also happens to be its own `followUpTo` → both features render
  independently; no special-casing needed, they're unrelated relationships.
- Exactly one other consultation shares a pattern vs. several → both render as a list; a
  single-item list is not special-cased into different copy.

## Acceptance criteria

- [x] `GET /api/consultations/{id}` returns `repeats` with all three keys, correctly populated.
- [x] A consultation with no changing lines returns `repeats.changingLines: []` with no wasted
      query.
- [x] `GET /api/consultations`, `POST /api/consultations`, `PATCH /api/consultations/{id}`
      responses contain no `repeats` key.
- [x] `ConsultationPage` shows each non-empty category as a distinctly labeled linked list.
- [x] `ConsultationPage` shows nothing extra when all three categories are empty.
- [x] `npm run verify` passes end to end.
- [x] Manually verified against the real running API/UI with genuinely repeated hexagrams and
      changing-line sets across multiple consultations.
