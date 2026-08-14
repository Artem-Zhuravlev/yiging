# Plan — Hexagram Relationship Navigation (SPEC-015)

**Depends on spec status:** `approved`

## Technical approach

- `HexagramDetailPage.vue`: add a `<div>` after the existing upper/lower trigram `<dl>`, listing
  the three relationships. Each entry:
  - Label ("Nuclear" / "Reversed" / "Complement").
  - If `relationship.kingWenNumber !== state.hexagram.kingWenNumber`: a `router-link` to
    `/hexagrams/${relationship.kingWenNumber}`, text `{kingWenNumber}. {chineseName}` (same
    format `HexagramListPage`'s cards already use).
  - Else: the same text, not wrapped in a link (a `<span>`), plus `(self)` so it's visually clear
    why there's no link rather than looking like a bug.
- No new component needed — three short entries don't justify extracting a
  `RelatedHexagrams.vue`; inline in the existing `<template>`, consistent with how the rest of
  the page (Judgment/Image sections) is already structured as plain inline blocks.
- `relatedHexagrams`: a `computed<RelatedHexagram[]>` mapping `state.hexagram.relationships` to
  `{ label, summary, isSelf }`, driving a single `v-for` in the template instead of three
  hand-copied blocks.
- **Bug fix, discovered manually verifying this spec (REQ-RELNAV-005):** replace `onMounted()`
  with `watch(kingWenNumber, fetchAndSetState, { immediate: true })`. `onMounted()` only runs
  once per component instance; Vue Router reuses the same `HexagramDetailPage` instance across a
  same-route param change (`/hexagrams/11` → `/hexagrams/54`), so the fetch never re-ran and the
  page silently kept showing the previous hexagram. `watch()` on the route-derived
  `kingWenNumber` computed (with `immediate: true` to cover the first mount too) fixes both the
  new relationship-link navigation and any other same-route param change (e.g. editing the URL).

## Architecture decisions

- **Comparison for "is this self-referential" happens in the template via a computed property,
  not duplicated per relationship.** A single `isSelf(kingWenNumber: number): boolean` helper
  (comparing against `state.hexagram.kingWenNumber`) avoids repeating the same three-way
  comparison inline three times.
- **No visual graph/diagram.** Per spec's "Out of scope" — matches the plan's actual ask
  ("navigate through," not "visualize").

## Affected areas

- `apps/web/src/pages/hexagrams/HexagramDetailPage.vue`
- `apps/web/src/pages/hexagrams/HexagramDetailPage.spec.ts`

## Data / schema changes

None.

## Risks / open questions

- None currently open.
