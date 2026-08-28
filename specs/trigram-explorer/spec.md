# SPEC-049 — Trigram (Bagua) Explorer

**Status:** implemented
**Owner:** unassigned
**Last updated:** 2026-08-28

## Problem

`GET /api/trigrams` already returns all eight trigrams with rich data — Unicode symbol,
Chinese name, pinyin, natural image, element, family member, compass direction — but the
frontend has no page for it. The eight trigrams are the building blocks of the 64 hexagrams;
there's nowhere in the app to actually study them.

## Purpose

Add a `/trigrams` page that lists the eight trigrams as reference cards and shows them arranged
in the Later Heaven (King Wen) bagua by compass direction. Read-only, no new API.

## Scope

- New `entities/trigram/model.ts` — `Trigram` interface matching the endpoint
  (`id, name, chineseName, pinyin, symbol, element, familyMember, direction, image`).
- New `entities/trigram/api.ts` — `fetchTrigrams(): Promise<Trigram[]>` → `GET /api/trigrams`.
- New `pages/trigrams/TrigramExplorerPage.vue` at route `/trigrams` (`router/index.ts`):
  - `<main id="main">`, `useStatusAnnouncer`, `LoadingSkeleton` while loading, error `Message`
    with `role="alert"` — the established page pattern.
  - **Card grid**: one card per trigram — the large Unicode `symbol`, `{chineseName} ({pinyin})`
    and `name` as heading, then a small definition list: Image, Element, Family, Direction.
    Responsive (`col-12 sm:col-6 lg:col-3`-ish).
  - **Later Heaven arrangement**: a 3×3 grid placing each trigram's `symbol` +
    `chineseName` in the cell matching its `direction` (NW/N/NE / W/·/E / SW/S/SE), centre cell
    empty, with a caption naming the arrangement. Directions come straight from the API
    `direction` field; the page maps the eight English direction strings to grid cells.
- Link to `/trigrams` from `HexagramListPage` next to the existing "Visual Editor" link
  (the explorer is where a user browsing structure would look for it). **Not** added to the
  top nav.
- Localised (en + uk): page title, the four field labels (Image / Element / Family / Direction),
  the arrangement caption. `name`/`image`/`element`/`familyMember`/`direction` *values* come
  from the API in English and are shown as-is (same choice SPEC-038 made for API-supplied
  strings).

## Out of scope

- **The Earlier Heaven (Fu Xi) arrangement.** The API only carries one `direction` (King Wen /
  Later Heaven); adding the Fu Xi order would need new domain data — a separate spec.
- **Per-trigram detail pages, cross-links from hexagram pages to trigram pages, filtering, or
  favourites.**
- **Any API or `yijing-core` change** — the endpoint already returns everything needed.
- **Translating the API's English attribute values** (Metal, Father, Northwest, …).
- **Adding `/trigrams` to the primary navigation.**

## Functional requirements

- **REQ-TRIG-001** — `GET /api/trigrams` is fetched and all eight trigrams render as cards with
  symbol, Chinese name + pinyin, name, and the Image/Element/Family/Direction fields.
- **REQ-TRIG-002** — A 3×3 "Later Heaven arrangement" grid places each trigram in the cell
  matching its API `direction`; the centre cell is empty; the grid has a caption.
- **REQ-TRIG-003** — The page follows the standard load/error/skeleton/announce pattern and has
  a single `<main id="main">` landmark.
- **REQ-TRIG-004** — `HexagramListPage` links to `/trigrams`.

## Non-functional requirements

- **REQ-TRIG-020** — New UI strings localised (en + uk).
- **REQ-TRIG-021** — Responsive card grid; legible light and dark; theme CSS vars only for any
  custom styling.
- **REQ-TRIG-022** — `npm run verify` passes; a `TrigramExplorerPage.spec.ts` covers the card
  render, the arrangement grid placement, and the loading/error states.

## Data requirements

None new. Consumes the existing `GET /api/trigrams`.

## API requirements

None new.

## Edge cases

- `fetchTrigrams` rejects → the standard inline error `Message`, no cards, no grid.
- An unexpected `direction` value (shouldn't happen — the API is a fixed set) → that trigram
  simply isn't placed in the arrangement grid; it still appears as a card. The mapping is a
  plain lookup with no throw.
- Very narrow viewport → cards stack one per row; the 3×3 grid stays 3×3 but shrinks (symbols
  scale down) or scrolls within its own container.

## Acceptance criteria

- [x] `/trigrams` lists all eight trigrams as cards (symbol, 名(pinyin), name,
      Image/Element/Family/Direction) — verified live + `TrigramExplorerPage.spec.ts`.
- [x] A captioned 3×3 grid shows the Later Heaven arrangement by `direction`, centre empty —
      live check: NW☰乾 / N☵坎 / NE☶艮 / W☱兌 / · / E☳震 / SW☷坤 / S☲離 / SE☴巽.
- [x] Loading → `LoadingSkeleton`; a failed fetch → inline error `Message`, no cards — spec.
- [x] `HexagramListPage` header links to `/trigrams` (next to "Visual Editor") — spec + live.
- [x] `npm run verify` passes (web 198, api 312, yijing-core 55).

## Implementation note (2026-08-28)

- `entities/trigram/{model,api}.ts`; `pages/trigrams/TrigramExplorerPage.vue` at `/trigrams`
  (standard load/error/skeleton/announce, `<main id="main">`). Card grid uses a flex
  label/value row per attribute (the grid `<dl>` truncated the longer Ukrainian labels).
  Arrangement is a `CELL_FOR_DIRECTION` lookup → a length-9 row-major array → a
  `grid-template-columns: repeat(3, 1fr)` `<figure>` with a `<figcaption>` caption; unmatched
  directions (only the centre) render an empty cell, no throw.
- `router/index.ts` gains the `/trigrams` route; `HexagramListPage` header gains the link.
  i18n `trigramExplorer.*` (en + uk); API attribute *values* shown as-is.
