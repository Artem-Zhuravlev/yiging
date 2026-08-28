# Plan — Trigram (Bagua) Explorer (SPEC-049)

## Files

### New
- `apps/web/src/entities/trigram/model.ts`
  ```ts
  export interface Trigram {
    id: string
    name: string
    chineseName: string
    pinyin: string
    symbol: string
    element: string
    familyMember: string
    direction: string
    image: string
  }
  ```
- `apps/web/src/entities/trigram/api.ts`
  ```ts
  import { apiGet } from '../../shared/api/http'
  import type { Trigram } from './model'
  export function fetchTrigrams(): Promise<Trigram[]> {
    return apiGet<Trigram[]>('/trigrams')
  }
  ```
- `apps/web/src/pages/trigrams/TrigramExplorerPage.vue`
  - state machine `loading | error | loaded` (+ `useStatusAnnouncer`), `LoadingSkeleton`,
    `<Message severity="error" role="alert">`.
  - `<main id="main" tabindex="-1" class="container-lg mx-auto p-4">`.
  - card grid: `<ul class="grid list-none p-0 m-0">` → `<li class="col-6 sm:col-4 lg:col-3">`
    → `<Card>` with the big `symbol` (`text-4xl`), `{{ chineseName }} ({{ pinyin }})` + `name`,
    and a `<dl>` of Image/Element/Family/Direction.
  - arrangement: a `CELL_FOR_DIRECTION` map
    `{ Northwest: 0, North: 1, Northeast: 2, West: 3, East: 5, Southwest: 6, South: 7, Southeast: 8 }`;
    build a length-9 array (`byCell[cell] = trigram`), render a
    `display:grid; grid-template-columns: repeat(3, 1fr)` of 9 cells; cell 4 (centre) shows
    nothing (or a small "後天" label — keep empty for simplicity); each filled cell shows the
    `symbol` + `chineseName`. Wrapped in `<figure>` with `<figcaption>` = the caption string.
    Cells that come out empty (only the centre) render an empty placeholder div.
- `apps/web/src/pages/trigrams/TrigramExplorerPage.spec.ts`

### Changed
- `apps/web/src/router/index.ts` — add
  `{ path: '/trigrams', name: 'trigrams', component: () => import('../pages/trigrams/TrigramExplorerPage.vue') }`.
- `apps/web/src/pages/hexagrams/HexagramListPage.vue` — next to the "Visual Editor"
  `router-link`, add `<router-link to="/trigrams" class="text-sm">{{ t('trigramExplorer.link') }}</router-link>`
  (the header row already has one link; make it a small flex gap of two).
- `apps/web/src/i18n/locales/{en,uk}.ts` — new `trigramExplorer` block:
  `title`, `link` ("Trigrams" / "Триграми"), `arrangement` ("Later Heaven arrangement" /
  "Посленебесне розташування"), `loadError`, and field labels `image` / `element` / `family` /
  `direction` ("Image/Element/Family/Direction" ; "Образ/Стихія/Родина/Напрямок").

## Testing — `TrigramExplorerPage.spec.ts`
- mock `entities/trigram/api`'s `fetchTrigrams`.
- fixture: the eight real trigrams (copy the API shape).
- tests:
  - loaded → 8 cards; a card contains its `symbol`, `chineseName`, `image`, `element`.
  - the arrangement `<figure>` places Qian (Northwest) in grid cell 0 and Li (South) in cell 7
    — assert by reading the cells in order and checking the symbol/name at those indices.
  - loading → `LoadingSkeleton` present (`.p-skeleton`); the sr-only "Loading…" text.
  - `fetchTrigrams` rejects → error text shown, no cards.
- `HexagramListPage.spec.ts` — add: a link with `to="/trigrams"` exists in the header. (The
  existing tests find links by text / `to`; adding one more `<a>` shouldn't break the
  count-based ones — check `renders every consultation, linking...`-style counts; HexagramList
  tests count `.hexagram-card` / chips, not header links, so safe. Verify.)

## Verify

`npm run verify`; browser: `/trigrams` shows 8 cards + the arrangement grid, dark + light,
narrow width; the Explorer header links to it; a forced fetch error shows the inline message.

## Verification note (2026-08-28)

- `npm run verify` green (web 198 tests incl. new `TrigramExplorerPage.spec.ts` + updated
  `HexagramListPage.spec.ts`; api 312; yijing-core 55).
- Live pass: `/trigrams` renders 8 trigram cards (symbol + 名(pinyin) + name + Image/Element/
  Family/Direction) and the "Посленебесне розташування" 3×3 grid places NW☰乾 / N☵坎 / NE☶艮 /
  W☱兌 / centre empty / E☳震 / SW☷坤 / S☲離 / SE☴巽 — the standard King Wen bagua. Explorer
  header links to it. Dark theme legible.
