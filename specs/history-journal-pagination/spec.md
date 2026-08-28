# SPEC-041 — History & Journal Pagination

**Status:** implemented
**Owner:** unassigned
**Last updated:** 2026-08-28

## Problem

`GET /api/consultations` returns the **entire** history, every row fully hydrated: for each
consultation the controller loads its notes, tags, and outcome (three extra queries per row via
`SqliteConsultationRepository::hydrate()`), then `toJson()` runs one more query per row for
`findFollowUpSummaries()`. That's ~4·N queries plus N object graphs on every visit to the
History page — which also downloads and holds the whole thing in memory to do its tag filter
(SPEC-022/025), favorites filter, and question/note text search (SPEC-026) client-side. Journal
(`GET /api/journal`) has the same "return everything" shape, minus the N+1.

For a single-user practice tool this isn't on fire today, but it's real debt: cost grows
linearly with every consultation ever cast, forever, and the History page can't get cheaper
without moving its filtering server-side.

## Purpose

Make the History and Journal list endpoints cursor-paginated and push History's
search/tag/favorite filtering to the server, so the default view costs one bounded query
regardless of how large the history grows — without regressing any SPEC-022/025/026/028
behaviour a user can observe.

## Scope

### `GET /api/consultations` — paginated + filtered list

Query parameters (all optional):

| param      | meaning                                                                   |
| ---------- | ------------------------------------------------------------------------- |
| `limit`    | page size, default `30`, clamped to `1..100`                             |
| `cursor`   | opaque token from a previous response's `nextCursor`; omit for page 1   |
| `q`        | case-insensitive substring; matches `question` **or** any note's `text` |
| `tags`     | comma-separated tag names; AND semantics (row must carry every one)     |
| `favorite` | `1`/`true` → only favourited consultations                              |

Response shape:

```json
{
  "items": [ ConsultationListItem, ... ],
  "nextCursor": "<token>" | null
}
```

`ConsultationListItem` — a deliberately lean projection, everything the History page's cards
and grouping need and nothing else:

```
{ id, question, method,
  primaryHexagram:  { kingWenNumber, chineseName, pinyin },
  resultingHexagram:{ kingWenNumber, chineseName, pinyin },
  changingLinePositions: number[],
  createdAt, tags: string[], favorite: bool }
```

No `notes`, `context`, `outcome`, `followUpTo`/`followUps`, `repeats`. Ordering is unchanged:
`created_at DESC, rowid DESC`. The cursor encodes the `(created_at, rowid)` of the last item
returned; the next page is everything strictly after it in that order. Implementation: one
`SELECT … WHERE <cursor+filters> ORDER BY created_at DESC, rowid DESC LIMIT :limit+1` (the
extra row tells us whether `nextCursor` is non-null), plus **one** batched
`consultation_id → tag name` query for the page's rows. No `Consultation` hydration, no
per-row follow-up query.

- `q`: `WHERE (question LIKE %q% COLLATE NOCASE OR EXISTS (SELECT 1 FROM consultation_notes n
  WHERE n.consultation_id = consultations.id AND n.text LIKE %q% COLLATE NOCASE))`. This
  searches the **whole** history, not just what's loaded — a strict improvement over the
  current client-side search.
- `tags`: a correlated `COUNT(DISTINCT name) = :tagCount` subquery over `consultation_tags`.
- `favorite`: `AND is_favorite = 1`.
- An invalid `cursor` (malformed / not decodable) → `422`. An out-of-range `limit` is clamped,
  not rejected. `tags` naming a tag that doesn't exist → simply matches nothing.

### `GET /api/consultations/tags` — new

Returns `string[]` of every distinct consultation tag name, sorted — so the History page's
filter chips list the full tag vocabulary, not just tags on the loaded page. Route registered
before `/{id}`.

### `GET /api/consultations/export` — new

Returns the **full fat backup array** — exactly what `GET /api/consultations` returns today
(every consultation, full `toJson()` including notes with timestamps, tags, context, outcome,
follow-up links, favourite) — for the SPEC-028 "Export Backup (JSON)" download. This is the one
place that still pays the O(N) cost, and it's an explicit user action, not a page load. Route
before `/{id}`. `POST /api/consultations/import` is unchanged.

### `GET /api/journal` — paginated

Query params `limit` (default `30`, clamp `1..100`) and `cursor` (opaque `(created_at, rowid)`
token). Response `{ "items": JournalEntry[], "nextCursor": string|null }`. Ordering unchanged
(`created_at DESC, rowid DESC`). No filtering (journal has none).

### Repository changes

- `ConsultationRepository`: add `findListPage(ConsultationListQuery $query): ConsultationListPage`
  and `allTagNames(): array`. `findAll()` stays (now used only by `export` and any existing
  caller). New value objects `ConsultationListQuery` (limit, cursor, q, tags, favoriteOnly) and
  `ConsultationListItem` / `ConsultationListPage` (items + nextCursor).
- `JournalRepository`: add `findPage(int $limit, ?string $cursor): JournalPage`. `findAll()`
  stays if still referenced, else removed.
- Cursor codec is a small private helper (base64 of `"{createdAtAtom}|{rowid}"`); a decode
  failure surfaces as an `InvalidArgumentException` the controller maps to `422`.

### Frontend — `ConsultationHistoryPage.vue`

- Loads page 1 on mount; **"Load more"** button while `nextCursor !== null`; new pages append
  to `items`.
- Search box → `q`, debounced (~300ms). Tag chips → `tags`. Favourites toggle → `favorite`.
  Changing any of the three resets the list (`items = []`, `cursor = null`) and refetches
  page 1 with the new params.
- Tag chips are built from `GET /api/consultations/tags` (fetched once), not from the loaded
  items.
- Date grouping (SPEC-022) stays exactly as-is — client-side, over `items`.
- "Export Backup (JSON)" → `GET /api/consultations/export`, then the existing client-side
  download of that array. "Import Backup (JSON)" unchanged; on success it resets and refetches
  page 1.
- Empty states: distinct copy for "no consultations yet" (page 1 empty, no filters) vs "nothing
  matches" (page 1 empty, filters active) — same split as today.

### Frontend — `JournalPage.vue`

- Loads page 1 on mount; **"Load more"** while `nextCursor !== null`; new entries append.
- Adding an entry still prepends to `items` locally (no refetch).
- Date grouping unchanged.

### Frontend — entities

- `entities/consultation/model.ts`: `ConsultationListItem`, `ConsultationListPage`
  (`{ items, nextCursor }`), `ConsultationListParams`.
- `entities/consultation/api.ts`: `fetchConsultations(params): Promise<ConsultationListPage>`,
  `fetchConsultationTags(): Promise<string[]>`, `fetchConsultationsForExport(): Promise<Consultation[]>`.
  `exportConsultationsBackup()` unchanged (still takes an array); the page fetches via
  `fetchConsultationsForExport()` first.
- `entities/journal/*`: `fetchJournalEntries(params): Promise<JournalPage>`.

## Out of scope

- **Full-text search index (FTS5).** `LIKE %q%` is enough at this scale and needs no schema or
  extension. A dedicated FTS spec can supersede this later.
- **Server-side date grouping.** Grouping stays a client-side presentational concern.
- **Infinite-scroll / virtualisation.** A plain "Load more" button is the interaction.
- **Paginating the single-consultation detail, statistics, hexagram, or interpretation
  endpoints.** Only the two list endpoints.
- **Changing sort order or offering sort options.**
- **`repeats` / follow-up data in the list.** Detail-only, unchanged.
- **Auth / per-user scoping.** Still a single-user app (its own separate concern).

## Functional requirements

- **REQ-PAGE-001** — `GET /api/consultations` returns `{ items, nextCursor }`; `items` length
  ≤ `limit`; `nextCursor` is non-null iff more rows exist after the page.
- **REQ-PAGE-002** — Passing a response's `nextCursor` back as `cursor` returns the next
  distinct page with no gaps or repeats, in `created_at DESC, rowid DESC` order.
- **REQ-PAGE-003** — `q` filters by case-insensitive substring of `question` or any note
  `text`, across the whole history.
- **REQ-PAGE-004** — `tags` filters with AND semantics; `favorite` restricts to favourites;
  all three filters compose.
- **REQ-PAGE-005** — `limit` defaults to 30 and is clamped to `1..100`; a malformed `cursor`
  returns `422`.
- **REQ-PAGE-006** — `GET /api/consultations/tags` returns all distinct tag names, sorted.
- **REQ-PAGE-007** — `GET /api/consultations/export` returns the full array of fully-populated
  consultation objects, byte-compatible with what `POST /api/consultations/import` accepts
  (SPEC-028 round-trip preserved).
- **REQ-PAGE-008** — `GET /api/journal` returns `{ items, nextCursor }` with the same
  `limit`/`cursor` semantics.
- **REQ-PAGE-009** — Serving page 1 of `GET /api/consultations` issues a bounded number of
  queries (one page query + one tag batch), independent of total history size — no per-row
  notes/outcome/follow-up queries.
- **REQ-PAGE-010** — The History page: loads page 1, "Load more" appends, changing
  search/tags/favourite resets to page 1, date grouping still renders per local day, export
  downloads the whole history, import still round-trips.
- **REQ-PAGE-011** — The Journal page: loads page 1, "Load more" appends, a newly added entry
  appears immediately without a refetch.

## Non-functional requirements

- **REQ-PAGE-020** — No observable regression of SPEC-022 (date grouping), SPEC-025 (favourites
  filter), SPEC-026 (search — now whole-history), or SPEC-028 (backup export/import round-trip).
- **REQ-PAGE-021** — `phpstan` level 8 + `php-cs-fixer` clean; web lint + typecheck clean.
- **REQ-PAGE-022** — `npm run verify` passes end to end.

## Data requirements

No schema change. New in-memory value objects only. Cursor is a derived, opaque encoding of
`(created_at, rowid)` — not stored.

## API requirements

- `GET /api/consultations?limit&cursor&q&tags&favorite` → `{ items: ConsultationListItem[], nextCursor: string|null }`
- `GET /api/consultations/tags` → `string[]`
- `GET /api/consultations/export` → `Consultation[]` (full/fat)
- `GET /api/journal?limit&cursor` → `{ items: JournalEntry[], nextCursor: string|null }`
- `GET /api/consultations/{id}`, `POST/PATCH /api/consultations`, `POST /api/consultations/import`,
  `POST /api/journal` — unchanged.

## Edge cases

- Empty history → `{ items: [], nextCursor: null }`.
- Exactly `limit` rows total → `nextCursor: null` (the `limit+1` probe finds nothing extra).
- Two consultations with the same `created_at` second → `rowid` in the cursor breaks the tie;
  no row is skipped or repeated across the page boundary.
- `cursor` from a consultation later deleted → paging still proceeds from that `(created_at,
  rowid)` position (rows strictly after it); nothing crashes. (No delete endpoint exists today,
  but import/backup restores could reintroduce ids.)
- `q` containing `%` or `_` → treated literally (escaped in the `LIKE` with an `ESCAPE` clause).
- History page with a filter active and zero matches on page 1 → "nothing matches" empty state,
  no "Load more".
- Adding a journal entry while not on page 1's newest state is impossible (entries only prepend
  and page 1 is always newest-first) → the prepend is always correct.

## Acceptance criteria

- [x] `GET /api/consultations` is paginated (`{items,nextCursor}`), filterable by
  `q`/`tags`/`favorite`, `limit` clamped, bad `cursor` → 422 — `ConsultationControllerTest`
  (index-page shape, `q` over note text, `tags` AND + `favorite`, malformed-cursor 422,
  `limit=0`/`limit=9999` clamp) + live curl.
- [x] Paging with `nextCursor` walks the whole history with no gaps/dupes — controller test
  with 5 rows / `limit=2` and a repo test with three rows sharing an exact `created_at`
  (rowid tie-break).
- [x] `GET /api/consultations/tags` returns distinct used tags sorted;
  `GET /api/consultations/export` returns the full fat array — the SPEC-028
  export→import round-trip test now reads `/api/consultations/export` and still passes
  field-by-field.
- [x] `GET /api/journal` paginated with the same `{items,nextCursor}` + `limit`/`cursor` +
  422-on-bad-cursor semantics — `JournalControllerTest` (page shape, multi-page walk,
  malformed cursor).
- [x] `findListPage()` runs exactly two queries (page + one batched tag query), no
  `Consultation` hydration and no per-row notes/outcome/follow-up query — structural review of
  `SqliteConsultationRepository::findListPage()`.
- [x] History page: load-more appends & passes the cursor, filter/search change resets to page
  1 with server params (verified live against the real API — `career` chip → 2 rows,
  `zzz-no-match` search → "nothing matches"), date grouping intact (4 day headings over 14
  rows), export hits `/api/consultations/export`, import resets + refetches. Journal page:
  load-more appends & passes the cursor, adding an entry prepends with no refetch.
- [x] `npm run verify` passes end to end (web 165 tests; api 312; yijing-core 55).

## Implementation note (2026-08-28)

- Cursor codec landed as `App\Core\ListCursor` (shared by `Readings` + `Journal` — `Core` is the
  one namespace both already depend on, keeping the no-cross-domain-import convention intact).
- New: `ConsultationListQuery` / `ConsultationListItem` / `ConsultationListPage`,
  `JournalListPage`; repo methods `findListPage()` / `allTagNames()` (consultations),
  `findPage()` (journal); controller actions `index` (now `Request`-taking), `tags`, `export`
  (consultations) and a `Request`-taking `index` (journal). `findAll()` kept on both repos
  (export + existing repo tests).
- Frontend: `fetchConsultations(params) → ConsultationListPage`, `fetchConsultationTags()`,
  `fetchConsultationsForExport()`; `fetchJournalEntries(params) → JournalPage`.
  `ConsultationHistoryPage` and `JournalPage` rewritten around paged `items` + `nextCursor` +
  a "Load more" button; History debounces the search box (~300ms) and drives `q`/`tags`/
  `favorite` as server params, resetting to page 1 on any change; date grouping stays
  client-side; the favourites toggle is always visible, tag chips come from `/tags`.
- Search is now whole-history (server `LIKE` over `question` + note `text`), a strict
  improvement over the old loaded-set-only client search.
