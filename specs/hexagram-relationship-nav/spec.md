# SPEC-015 — Hexagram Relationship Navigation

**Status:** verified
**Owner:** unassigned
**Last updated:** 2026-08-15

## Problem

Feature 22 of the plan's next batch asks for a Hexagram Explorer where users can "navigate to
related hexagrams" and "navigate through its relationship graph." Browsing all 64, opening one,
and seeing its structure/trigrams already exist (`HexagramListPage`/`HexagramDetailPage`,
SPEC-007). `GET /api/hexagrams/{id}` now returns `relationships.nuclear/reversed/complement`
(SPEC-014) — but `HexagramDetailPage` doesn't render them, so there's no way to actually click
from one hexagram to a related one today.

## Purpose

Add a "Related Hexagrams" section to `HexagramDetailPage` linking to each of the three
relationships SPEC-014 already exposes, using the existing `router-link` pattern
`HexagramListPage` already uses — so clicking through the relationship graph (hexagram → its
nuclear → *that* hexagram's own nuclear/reversed/complement → ...) works with zero new backend
code.

## Scope

- `HexagramDetailPage.vue`: a new section, positioned after the existing upper/lower trigram
  `<dl>`, rendering the three relationships from `state.hexagram.relationships` as labeled
  `router-link`s to `/hexagrams/{kingWenNumber}`, each showing King Wen number + Chinese name
  (matching `HexagramListPage`'s existing per-card content, minus the line diagram — a small
  inline link doesn't need one).
- Self-referential relationships (e.g. hexagram 1's nuclear is itself) render as a plain,
  non-linking label rather than a link to the current page — clicking "navigate to itself" isn't
  a real navigation and would be a confusing no-op.
- **Fix `HexagramDetailPage`'s route-reactivity bug, discovered while manually verifying this
  spec.** Since SPEC-007, the page has fetched its hexagram in `onMounted()`, which only runs
  once. Navigating from one hexagram's page to another's via a same-route param change (exactly
  what a relationship link does — `/hexagrams/11` → `/hexagrams/54`) reuses the existing component
  instance, so `onMounted()` never re-fires and the page silently keeps showing the *previous*
  hexagram's data under the new URL. This spec's entire purpose is clicking between hexagram
  pages, so this bug is directly in scope, not a tangential cleanup — REQ-RELNAV-005 below.

## Out of scope

- **Any new backend work.** SPEC-014 already exposes everything this spec needs; this is a
  frontend-only change.
- **A visual graph/diagram of the relationship network.** The plan asks to "navigate through" the
  graph, not visualize it as a graph structure — three labeled links per page, clicked
  repeatedly, already satisfies that; a force-directed graph or similar would be substantially
  more UI than the requirement asks for.
- **Consultation-scoped relationships (resulting hexagram via changing lines).** Already shown on
  `ConsultationPage` (SPEC-009/SPEC-010); this spec is about the standalone Hexagram Explorer.
- **Breadcrumbs or a navigation history trail.** The browser's own back button already covers
  "how did I get here" for a click-through flow like this; not asked for.

## User behavior

```
/hexagrams/11 (Tai)
  -> "Related Hexagrams" section:
       Nuclear: 54. 歸妹 (Gui Mei)      [links to /hexagrams/54]
       Reversed: 12. 否 (Pi)            [links to /hexagrams/12]
       Complement: 12. 否 (Pi)          [links to /hexagrams/12]
  -> clicking "Nuclear: 54. 歸妹" navigates to /hexagrams/54, which shows *its* relationships
     (nuclear, reversed, complement of 54), and so on — the graph is explorable by repeated
     clicks, no dead ends.

/hexagrams/1 (Qian, all yang — nuclear is itself)
  -> "Nuclear: 1. 乾 (self)" rendered as plain text, not a link.
```

## Functional requirements

- **REQ-RELNAV-001** — `HexagramDetailPage`'s loaded state MUST render all three relationships
  (`nuclear`, `reversed`, `complement`) from the already-fetched `state.hexagram.relationships`,
  each labeled with its relationship name.
- **REQ-RELNAV-002** — Each relationship whose `kingWenNumber` differs from the current
  hexagram's MUST be a `router-link` to `/hexagrams/{kingWenNumber}`.
- **REQ-RELNAV-003** — Each relationship whose `kingWenNumber` equals the current hexagram's
  (self-referential) MUST render as plain text, not a link.
- **REQ-RELNAV-004** — No relationship math (nuclear/reversed/complement computation) may exist
  in `HexagramDetailPage` or anywhere in `apps/web` — only rendering of values already returned
  by the API.
- **REQ-RELNAV-005** — Navigating from one hexagram's detail page to another's via a relationship
  link (or any other same-route param change, e.g. editing the URL directly) MUST re-fetch and
  fully replace the displayed hexagram — the page MUST NOT continue showing the previous
  hexagram's data under the new URL.

## Non-functional requirements

None beyond the existing page's established loading/error/not-found handling, which this spec
doesn't change.

## Data requirements

None.

## API requirements

None — consumes the existing `GET /api/hexagrams/{id}` response from SPEC-014 as-is.

## Edge cases

- A hexagram where two relationships coincide (e.g. Tai's `reversed` and `complement` both being
  Pi) → both render as separate links to the same page; not deduplicated, since they're
  conceptually distinct relationships that happen to share a target for this specific pattern.

## Acceptance criteria

- [x] `/hexagrams/11` (Tai) shows three relationship links: nuclear→54, reversed→12,
      complement→12, each navigable.
- [x] Clicking a relationship link navigates to and correctly loads that hexagram's own detail
      page (relationship graph is transitively explorable) — the previously-loaded hexagram's
      data does not linger under the new URL (REQ-RELNAV-005).
- [x] `/hexagrams/1` (Qian) renders its self-referential nuclear/reversed as plain text, not a
      link to itself.
- [x] No relationship-computation logic added anywhere under `apps/web`.
- [x] `npm run verify` passes end to end.
- [x] Manually verified against the real running API/UI: click through at least two hops of the
      relationship graph.
