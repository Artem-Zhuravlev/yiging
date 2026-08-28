# Plan — History & Journal Pagination (SPEC-041)

## Order: backend contract first (fully testable), then frontend, then sweep existing tests.

## Backend

### New value objects (`apps/api/src/Readings/`)
- `ConsultationListQuery` — readonly: `int $limit`, `?string $cursor`, `?string $q`,
  `list<string> $tags`, `bool $favoriteOnly`. Static `fromRequest(Request): self` doing the
  clamp/parse (default limit 30, clamp 1..100; `favorite` truthy set = `1`/`true`; `tags` split
  on `,` + trim + drop empties). Throws `InvalidArgumentException` on undecodable `cursor`.
- `ConsultationListItem` — readonly projection struct (id, question, method, primary/resulting
  `HexagramSummary`-ish arrays or ints + names, changingLinePositions, createdAt, tags,
  favorite). Keep it a plain data holder with a `toJson(): array`.
- `ConsultationListPage` — `list<ConsultationListItem> $items`, `?string $nextCursor`.
- `ListCursor` (private-ish helper, `Readings/`) — `encode(string $createdAtAtom, int $rowid): string`
  (`base64_encode("$atom|$rowid")`) and `decode(string): array{0:string,1:int}` throwing
  `InvalidArgumentException` on anything malformed.

### `SqliteConsultationRepository`
- `findListPage(ConsultationListQuery $q): ConsultationListPage`:
  - Build `WHERE` fragments: cursor (`(created_at < :cs_at) OR (created_at = :cs_at AND rowid < :cs_rowid)`),
    `q` (question LIKE + EXISTS over consultation_notes, `ESCAPE '\'`, `%`/`_` escaped),
    `tags` (correlated `COUNT(DISTINCT t.name) = :tagCount`), `favorite` (`is_favorite = 1`).
  - `SELECT id, question, method, primary_king_wen_number, changing_line_positions,
     resulting_king_wen_number, created_at, is_favorite, rowid ... ORDER BY created_at DESC,
     rowid DESC LIMIT :limitPlusOne`.
  - Take first `limit`; `nextCursor` = encode(last row) iff a `limit+1`th row came back.
  - One extra query: `SELECT ct.consultation_id, t.name FROM consultation_tags ct JOIN tags t
     ON t.id = ct.tag_id WHERE ct.consultation_id IN (<page ids>) ORDER BY t.name` → group into
     `id => string[]`.
  - Hexagram name/pinyin: derive from `Hexagram::fromKingWenNumber()` (pure, no DB), same as
    `hydrate()` already does — no `consultations`-table name columns exist.
- `allTagNames(): array` — `SELECT DISTINCT name FROM tags ... ORDER BY name` (or restrict to
  tags actually linked: `JOIN consultation_tags` — match the History page's current
  `[...new Set(consultations.flatMap(c => c.tags))]`, i.e. only used tags).
- Keep `findAll()` (used by `export`).

### `ConsultationController`
- `index(Request)` → build `ConsultationListQuery::fromRequest`, catch its
  `InvalidArgumentException` → `422`; call `findListPage`; return
  `{ items: [...->toJson()], nextCursor }`.
- `tags()` → `new JsonResponse($this->repository->allTagNames())`.
- `export()` → the *current* `index()` body (map `findAll()` through `toJson()`).
- `toListItemJson()` — or `ConsultationListItem::toJson()`.

### `config/routes.php` — order matters (FastRoute static vs `{id}`)
```
GET  /api/consultations            index
GET  /api/consultations/tags       tags
GET  /api/consultations/export     export
GET  /api/consultations/{id}       show
```
(`/tags` and `/export` are static, registered before `/{id}` — FastRoute matches static first
anyway, but keep them ordered for readability.)

### Journal
- `JournalListPage` value object (`list<JournalEntry> $items`, `?string $nextCursor`); reuse
  `ListCursor` (move it to a shared spot or duplicate a 6-line helper — prefer
  `Readings\ListCursor` reused, or a tiny `Support\ListCursor`; decide during impl, keep it
  DRY). 
- `SqliteJournalRepository::findPage(int $limit, ?string $cursor): JournalListPage` — same
  cursor WHERE + `LIMIT limit+1` pattern.
- `JournalController::index(Request)` — parse `limit`/`cursor` (422 on bad cursor), return
  `{ items, nextCursor }`.
- Keep `findAll()` only if still used; otherwise drop from interface + impl.

## Frontend

### entities
- `consultation/model.ts`: `ConsultationListItem`, `ConsultationListPage { items; nextCursor }`,
  `ConsultationListParams { limit?; cursor?; q?; tags?; favorite? }`.
- `consultation/api.ts`:
  - `fetchConsultations(params?: ConsultationListParams): Promise<ConsultationListPage>` —
    build query string.
  - `fetchConsultationTags(): Promise<string[]>` → `/consultations/tags`.
  - `fetchConsultationsForExport(): Promise<Consultation[]>` → `/consultations/export`.
- `journal/model.ts`: `JournalPage { items; nextCursor }`.
- `journal/api.ts`: `fetchJournalEntries(params?: { limit?; cursor? }): Promise<JournalPage>`.

### `ConsultationHistoryPage.vue`
- State: `items: ConsultationListItem[]`, `nextCursor: string | null`, `status`
  (`loading|error|ready`), `loadingMore: bool`, `q`, `selectedTags: Set`, `favoritesOnly`,
  `allTags: string[]`.
- `load(reset: boolean)` — on reset clears items+cursor; fetches with current params; appends.
- Watch `[debouncedQ, selectedTags, favoritesOnly]` → `load(true)`.
- Debounce `q` locally (~300ms) — small inline `setTimeout` ref, no new dep.
- "Load more" button when `nextCursor` — calls `load(false)`.
- Tag chips from `allTags` (fetched once in `onMounted`).
- Keep `groupedConsultations` computed over `items` unchanged.
- Export: `await fetchConsultationsForExport()` → `exportConsultationsBackup(all)`.
- Import success → `load(true)` + re-fetch `allTags`.
- Keep `useStatusAnnouncer` wired to `status`.

### `JournalPage.vue`
- State: `items`, `nextCursor`, `status`, `loadingMore`.
- `load(reset)`; "Load more"; `addEntry` prepends to `items` (unchanged behaviour).
- Grouping unchanged.

## Test sweep

- **`ConsultationControllerTest`**: `testIndexReturnsAllConsultationsNewestFirst` → assert
  `items`/`nextCursor` shape. `testIndexAndUpdateResponsesDoNotIncludeRepeats` → read
  `body['items'][0]`. Export/import round-trip tests (~L896, L949, L959) → hit
  `/api/consultations/export`. Add: pagination walk (>2 pages, tied `created_at`), `q` over
  note text, `tags` AND, `favorite`, bad-cursor 422, `limit` clamp, `/tags` endpoint.
- **`JournalControllerTest`**: index shape → `{items,nextCursor}`; add a pagination-walk test.
- **`SqliteConsultationRepositoryTest`** / **`SqliteJournalRepositoryTest`**: add
  `findListPage` / `findPage` unit tests (cursor correctness, filter SQL, tag batching,
  `allTagNames`).
- **web `ConsultationHistoryPage.spec.ts`**: mock the new `fetchConsultations` page shape +
  `fetchConsultationTags`; assert load-more appends, filter change refetches with params,
  grouping intact, export calls `fetchConsultationsForExport`.
- **web `JournalPage.spec.ts`**: page shape, load-more, add-prepends.
- **web `entities/consultation/api.spec.ts`**, **`journal` api specs** if present: query-string
  building, `/export` + `/tags` URLs.

## Verify — done 2026-08-28

- `cd apps/api`: `composer test` 312 tests / 1157 assertions OK; `composer stan` No errors;
  `composer lint` 0 fixable. `npm run verify` (root): all steps pass (web 165 tests / build;
  api 312; yijing-core 55).
- Deviations from this plan: `ListCursor` went to `App\Core` (not `App\Readings`), since it's
  shared by both list endpoints and `Core` is the only namespace both `Readings` and `Journal`
  already import. `HttpGeminiClient` unaffected. Journal repo kept `findAll()` (still used by
  `SqliteJournalRepositoryTest`) and gained a private `hydrate()` shared with `findPage()`.
  History page's favourites toggle renders unconditionally (only the tag chips are gated on a
  non-empty `/tags` response) — matches the pre-SPEC-041 behaviour.
- Live pass (PHP dev server + real seed DB, 14 consultations / 1 journal entry):
  - `GET /api/consultations?limit=1` → 1 item + non-null `nextCursor`; feeding it back as
    `cursor` → the next distinct row, still `nextCursor` non-null.
  - `?cursor=garbage` → `422`.
  - `/api/consultations/tags` → `["career","relationships"]`.
  - `/api/consultations/export` → 14 objects, each with `notes`/`context`/`outcome`/`followUps`.
  - `?q=career` → 1 item.
  - Browser: History renders 14 cards in 4 day-groups, favourites toggle + 2 tag chips;
    clicking `career` → 2 cards (server-filtered); typing `zzz-no-match` (debounced) → 0 cards +
    "nothing matches" empty state; "Export Backup" click issues `GET /api/consultations/export`.
    Journal renders its 1 entry in 1 group, no "Load more".
