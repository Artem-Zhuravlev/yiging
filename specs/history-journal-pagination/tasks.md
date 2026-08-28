# Tasks — History & Journal Pagination (SPEC-041)

## Backend — consultations

- [x] **TASK-PAGE-001** — `App\Core\ListCursor` (shared by Readings + Journal):
      `encode(atom, rowid)` / `decode(token): [atom, rowid]` (base64 of `"atom|rowid"`),
      `InvalidArgumentException` on malformed input. → REQ-PAGE-002, REQ-PAGE-005
- [x] **TASK-PAGE-002** — `Readings/ConsultationListQuery` + `::fromRequest(Request)`: limit
      default 30 clamp 1..100; parse `cursor`/`q`/`tags`(csv)/`favorite`(truthy);
      `InvalidArgumentException` on bad cursor. → REQ-PAGE-005
- [x] **TASK-PAGE-003** — `Readings/ConsultationListItem` (+ `toJson()`) and
      `Readings/ConsultationListPage` (`items`, `nextCursor`). → REQ-PAGE-001
- [x] **TASK-PAGE-004** — `ConsultationRepository`: add `findListPage(ConsultationListQuery)`
      and `allTagNames()` to the interface; keep `findAll()`. → REQ-PAGE-001, 006
- [x] **TASK-PAGE-005** — `SqliteConsultationRepository::findListPage()`: cursor + `q`
      (question + `EXISTS` note text, `ESCAPE`) + `tags` (AND via COUNT DISTINCT) + `favorite`
      WHERE; `ORDER BY created_at DESC, rowid DESC LIMIT :limit+1`; derive `nextCursor`; one
      batched tag query for the page. No `Consultation` hydration. → REQ-PAGE-001..004, 009
- [x] **TASK-PAGE-006** — `SqliteConsultationRepository::allTagNames()` — distinct *used* tag
      names, sorted. → REQ-PAGE-006
- [x] **TASK-PAGE-007** — `ConsultationController`: `index()` → query+`findListPage`+422 on bad
      cursor; new `tags()`; new `export()` (old index body). → REQ-PAGE-001, 006, 007
- [x] **TASK-PAGE-008** — `config/routes.php`: `GET /api/consultations/tags` and
      `/export` before `/{id}`. → REQ-PAGE-006, 007

## Backend — journal

- [x] **TASK-PAGE-009** — `Journal/JournalListPage` (`items`, `nextCursor`); reuse
      `App\Core\ListCursor`. → REQ-PAGE-008
- [x] **TASK-PAGE-010** — `JournalRepository::findPage(int $limit, ?string $cursor)` in
      interface + `SqliteJournalRepository` (extracted a private `hydrate()` shared with
      `findAll()`, which stays — still used by the repo test). → REQ-PAGE-008
- [x] **TASK-PAGE-011** — `JournalController::index(Request)` → parse `limit`/`cursor`
      (422 on bad cursor), return `{items,nextCursor}`. → REQ-PAGE-008

## Frontend — entities

- [x] **TASK-PAGE-012** — `consultation/model.ts`: `ConsultationListItem`,
      `ConsultationListPage`, `ConsultationListParams`. → REQ-PAGE-010
- [x] **TASK-PAGE-013** — `consultation/api.ts`: `fetchConsultations(params)` →
      `ConsultationListPage`; `fetchConsultationTags()`; `fetchConsultationsForExport()`.
      → REQ-PAGE-010
- [x] **TASK-PAGE-014** — `journal/model.ts` `JournalPage`; `journal/api.ts`
      `fetchJournalEntries(params)` → `JournalPage`. → REQ-PAGE-011

## Frontend — pages

- [x] **TASK-PAGE-015** — `ConsultationHistoryPage.vue`: paged `items` + `nextCursor`,
      "Load more", debounced `q`, server `tags`/`favorite`, reset-on-filter-change, `allTags`
      from `/tags`, grouping unchanged, export via `fetchConsultationsForExport`, import →
      reset+refetch. Keep `useStatusAnnouncer`. → REQ-PAGE-010, 020
- [x] **TASK-PAGE-016** — `JournalPage.vue`: paged `items` + `nextCursor`, "Load more", add
      prepends, grouping unchanged. → REQ-PAGE-011, 020
- [x] **TASK-PAGE-017** — i18n keys: `common.loadMore` (en+uk); any new History strings.
      → REQ-PAGE-010

## Tests

- [x] **TASK-PAGE-018** — `ConsultationControllerTest`: rework index-shape + repeats-shape
      tests; move export/import round-trip to `/api/consultations/export`; add pagination walk
      (>2 pages + tied `created_at`), `q` note-text, `tags` AND, `favorite`, bad-cursor 422,
      `limit` clamp, `/tags`. → REQ-PAGE-001..007, 020
- [x] **TASK-PAGE-019** — `SqliteConsultationRepositoryTest`: `findListPage` cursor-with-tied-
      `created_at`, note-text search + tag + favorite filters, `allTagNames` distinct/sorted.
      (Journal `findPage` is covered by `JournalControllerTest`'s multi-page walk rather than a
      separate repo test.) → REQ-PAGE-002..009
- [x] **TASK-PAGE-020** — `JournalControllerTest`: index `{items,nextCursor}` + pagination
      walk. → REQ-PAGE-008
- [x] **TASK-PAGE-021** — web `ConsultationHistoryPage.spec.ts` + `JournalPage.spec.ts` +
      `entities/*/api.spec.ts`: new page shapes, load-more append, filter→refetch-with-params,
      export path, query-string building. → REQ-PAGE-010, 011
- [x] **TASK-PAGE-022** — `cd apps/api` `composer test`/`stan`/`lint` green; `npm run verify`
      green; manual browser pass (History load-more/filter/search/export; Journal
      load-more/add). Fill `plan.md` note; flip `spec.md` → `implemented`; add SPEC-041 to both
      README tables. → REQ-PAGE-021, 022