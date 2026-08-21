# Plan — Practice Journal (SPEC-030)

**Depends on spec status:** `approved`

## Technical approach

- Migration `2026_08_21_000002_create_journal_entries.php`: `CREATE TABLE journal_entries (id
  TEXT PRIMARY KEY, text TEXT NOT NULL, created_at TEXT NOT NULL);`.
- `App\Journal\JournalEntry` — readonly, constructor validates non-empty/≤5000 chars (same
  pattern/limit as `App\Readings\ConsultationNote`).
- `App\Journal\Clock`/`SystemClock`, `App\Journal\JournalEntryIdGenerator`/
  `UuidV4JournalEntryIdGenerator` — near-identical to their `App\Readings` counterparts, kept as
  separate small classes in the new module rather than imported across modules, matching this
  codebase's existing convention of fully self-contained domain modules (no module currently
  imports another's internals).
- `App\Journal\JournalRepository` (interface) / `SqliteJournalRepository`: `save()` (plain
  `INSERT`, entries are never updated so no upsert needed), `findAll()` (`SELECT * FROM
  journal_entries ORDER BY created_at DESC, rowid DESC`, same tie-break pattern as
  `SqliteConsultationRepository::findAll()`).
- `App\Journal\JournalController` — same constructor shape as `ConsultationController`
  (`Config` in, builds its own repository via `Database::connect()`); `create()` validates and
  saves, `index()` lists.
- `config/routes.php` gains `POST /api/journal`, `GET /api/journal`.
- `apps/web/src/entities/journal/model.ts` — `JournalEntry { id, text, createdAt }`,
  `NewJournalEntryRequest { text: string }`.
- `apps/web/src/entities/journal/api.ts` — `createJournalEntry()`, `fetchJournalEntries()`.
- `apps/web/src/pages/journal/JournalPage.vue` — `State` union matching
  `ConsultationHistoryPage.vue`'s shape; a `groupedEntries` computed reusing the exact same
  date-grouping walk (group key = `toLocaleDateString()` on `createdAt`, one pass over the
  newest-first array); an add-entry form (textarea + submit) at the top, same loading/error
  `FormState` pattern `ConsultationPage.vue` already uses for its own note-adding form.
- `router/index.ts` gains `/journal`; `App.vue`'s nav gains a "Journal" link (inside the existing
  non-public `<nav>` branch only).

## Architecture decisions

- **A fully separate `App\Journal` module, not new columns/tables bolted onto `App\Readings`.**
  Matches the spec's own reasoning: an entry has no relationship to any consultation, so it
  doesn't belong in the `Readings` bounded context at all — this is a second, independent
  top-level domain, exactly like `Hexagrams`/`Trigrams`/`AI` already are.
- **`journal_entries` is a plain, dependency-free table.** No foreign keys, no join tables — the
  simplest possible shape for "id, text, timestamp," matching REQ-JOURNAL-006.
- **Reusing the client-side date-grouping *pattern* (not a shared component) from
  `ConsultationHistoryPage.vue`.** The two pages group conceptually identically but render
  different item shapes (a consultation card vs. a journal entry's plain text) — a shared
  generic "group by date" component would need to accept a render-slot per item type for what's
  otherwise a ~15-line computed property; duplicating that small computed is simpler to read in
  each file than factoring out a generic component for two call sites.

## Affected areas

- `apps/api/database/migrations/2026_08_21_000002_create_journal_entries.php` (new)
- `apps/api/src/Journal/JournalEntry.php` (new)
- `apps/api/src/Journal/Clock.php` (new)
- `apps/api/src/Journal/SystemClock.php` (new)
- `apps/api/src/Journal/JournalEntryIdGenerator.php` (new)
- `apps/api/src/Journal/UuidV4JournalEntryIdGenerator.php` (new)
- `apps/api/src/Journal/JournalRepository.php` (new)
- `apps/api/src/Journal/SqliteJournalRepository.php` (new)
- `apps/api/src/Journal/JournalController.php` (new)
- `apps/api/tests/Journal/*` (new)
- `apps/api/config/routes.php`
- `apps/web/src/entities/journal/model.ts` (new)
- `apps/web/src/entities/journal/api.ts` (new)
- `apps/web/src/pages/journal/JournalPage.vue` (new)
- `apps/web/src/pages/journal/JournalPage.spec.ts` (new)
- `apps/web/src/router/index.ts`
- `apps/web/src/App.vue`
- `apps/web/src/App.spec.ts`

## Data / schema changes

New table `journal_entries`, no relation to any existing table.

## Risks / open questions

- None currently open.
