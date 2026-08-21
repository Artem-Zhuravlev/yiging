# SPEC-022 — Consultation Timeline

**Status:** verified
**Owner:** unassigned
**Last updated:** 2026-08-21

## Problem

Feature 1 of the plan's next batch (chronological timeline) asks for a better way to browse
consultation history than the current flat list. `ConsultationHistoryPage` (SPEC-009) already
renders every consultation newest-first, but as a single undifferentiated `<ul>` — no grouping by
when a reading happened, and no way to narrow the list by the tags SPEC-013 already lets a user
attach. Both `createdAt` and `tags` are already in every `GET /api/consultations` response; the
gap is purely presentational.

## Purpose

Group the existing history by calendar date (in the viewer's local timezone) under a date
heading, and add a client-side multi-select tag filter that narrows the visible list — both built
entirely from data the page already fetches, no new API surface.

## Scope

- `ConsultationHistoryPage.vue`: derive date groups from the already-fetched, already
  newest-first `Consultation[]` — one heading per unique local calendar day of `createdAt`,
  consultations within a day keep their existing newest-first order, groups themselves stay in
  newest-first order.
- Same page: derive the distinct, alphabetically sorted set of tags across all fetched
  consultations; render as toggle chips above the list.
- Selecting one or more tags filters the grouped list to consultations whose `tags` contain
  **every** selected tag (AND). Deselecting all tags restores the full list.
- A date group that has zero matching consultations after filtering is not rendered (no empty
  heading).
- Distinct empty-state messages for "no consultations at all" vs. "tag filter matched nothing."

## Out of scope

- **Date-range picker filtering.** Only calendar-day grouping + tag filtering was asked for;
  filtering by an explicit start/end date is a different feature.
- **Full-text search over questions/notes.** That's a separate feature (plan feature 5); this
  spec only touches date grouping and tag filtering.
- **Persisting the selected tag filter in the URL query string.** Would let a filtered view be
  bookmarked/shared, and SPEC-021 already established a `?followUpTo=` query-param precedent for
  this page family — but nothing asked for that here, and adding it now is scope creep beyond
  "group by date, filter by tag." Left as a natural follow-up if requested.
- **Server-side filtering, pagination, or a new query parameter on `GET /api/consultations`.**
  The endpoint already returns the full, sorted list with tags included; at this app's personal-
  history scale that's sufficient, matching the N+1-acceptable-at-this-scale precedent SPEC-021
  already established for consultation responses.
- **Tag case/whitespace normalization.** Tags are stored exactly as submitted (SPEC-013); "Work"
  and "work" already show as distinct tags everywhere else in the app (e.g. `ConsultationPage`'s
  tag list). This spec's filter chips inherit that existing behavior rather than introducing new
  normalization logic unrelated to the timeline feature itself.

## User behavior

```
/consultations
  -> "August 21, 2026"
       - Should I take the offer? (1. 乾 → 44. 姤)
       - Did I make the right call? (follow-up, listed under its own createdAt day)
  -> "August 14, 2026"
       - ...

Tag chips: [career] [relationships] [health]
  -> click "career" -> list narrows to consultations tagged "career"; date groups with no
     matches disappear; unmatched groups' headings don't render
  -> click "relationships" too -> list narrows further to consultations tagged BOTH
     "career" AND "relationships"
  -> click "career" again -> deselects it, filter now just "relationships"
  -> deselect all -> full grouped list returns

No consultations tagged "career" AND "relationships" together
  -> "No consultations match the selected tags." (distinct from "No consultations yet —
     cast your first one.")
```

## Functional requirements

- **REQ-TIMELINE-001** — `ConsultationHistoryPage` MUST group loaded consultations under a date
  heading per unique local calendar day of `createdAt`, preserving newest-first order both across
  groups and within each group.
- **REQ-TIMELINE-002** — Each date heading MUST render a locale-formatted date (e.g. via
  `toLocaleDateString()`), not a raw ISO string.
- **REQ-TIMELINE-003** — The page MUST render one toggle chip per distinct tag found across all
  loaded consultations, alphabetically sorted, deduplicated.
- **REQ-TIMELINE-004** — Selecting one or more tag chips MUST narrow the visible list to
  consultations whose `tags` array contains every selected tag (AND semantics).
- **REQ-TIMELINE-005** — A date group with zero consultations remaining after filtering MUST NOT
  render its heading.
- **REQ-TIMELINE-006** — Deselecting every tag chip MUST restore the full, unfiltered grouped
  list.
- **REQ-TIMELINE-007** — Selected tag chips MUST be visually distinguishable from unselected
  ones.
- **REQ-TIMELINE-008** — If an active tag filter matches zero consultations, the page MUST show a
  message distinct from the "no consultations yet" empty state.
- **REQ-TIMELINE-009** — A consultation with `tags: []` MUST be included in the unfiltered
  grouped view and excluded whenever any tag filter is active.
- **REQ-TIMELINE-010** — If no consultations have any tags, no tag-chip row renders at all (no
  empty filter UI).

## Non-functional requirements

- **REQ-TIMELINE-011** — Grouping and filtering happen entirely client-side over the single
  existing `fetchConsultations()` response; no new network request, query parameter, or backend
  change.
- **REQ-TIMELINE-012** — No component outside `pages/consultations` needs new state; filter
  selection stays local `ref` state on `ConsultationHistoryPage`, matching the page's existing
  pattern (no new Pinia store).

## Data requirements

None — no schema or persisted-data change.

## API requirements

None — `GET /api/consultations` is unchanged.

## Edge cases

- Two consultations created a few minutes apart but crossing local midnight → grouped into two
  different date headings, by design (local calendar day, not a rolling 24h window).
- A consultation tagged with the same tag twice server-side (if that were ever possible) → tag
  chip list is deduplicated regardless.
- All consultations share one tag → selecting it renders the full list under its existing date
  groups, functionally a no-op filter.

## Acceptance criteria

- [x] History page groups consultations under locale-formatted date headings, newest-first
      across and within groups.
- [x] Tag chips render for every distinct tag present, alphabetically sorted.
- [x] Selecting multiple tags narrows the list to consultations having all selected tags.
- [x] Date groups with no remaining matches don't render their heading.
- [x] Deselecting all tags restores the full list.
- [x] Zero-match tag filter shows a distinct "no consultations match" message.
- [x] No consultations at all still shows the original "cast your first one" empty state.
- [x] No tags anywhere in the loaded history → no tag-chip row rendered.
- [x] `npm run verify` passes end to end.
