# SPEC-030 — Practice Journal

**Status:** verified
**Owner:** unassigned
**Last updated:** 2026-08-21

## Problem

Feature 10 of the plan's next batch asks for a practice journal — free-form entries not tied to
any specific consultation, on their own chronological timeline. Everything the app persists today
(`apps/api/src/Readings`) is anchored to a cast hexagram; there's nowhere to write a general
reflection, a question still forming, or a note about the practice itself. `apps/api/src/Journal`
already exists as an empty, reserved directory — this spec is the first thing to put in it.

## Purpose

Let a user write free-text journal entries, independent of any consultation, and see them listed
newest-first, grouped by calendar date — the same date-grouped presentation
[SPEC-022](../consultation-timeline/spec.md) already established for consultation history, so
"journal" and "history" read consistently even though they're separate pages.

## Scope

- New `App\Journal` module, mirroring `App\Readings`'s existing self-contained shape (this
  codebase has no cross-module domain imports anywhere yet — `Hexagrams`/`Trigrams`/`AI` don't
  import from `Readings` either — so `Journal` gets its own small `Clock`/`SystemClock`/
  `JournalEntryIdGenerator` rather than reaching into `Readings`'s):
  - `JournalEntry` (readonly): `id`, `text` (same 5000-character limit as
    `ConsultationNote`/context fields, non-empty), `createdAt`.
  - `JournalRepository` (interface) / `SqliteJournalRepository`: `save()`, `findAll(): list<JournalEntry>`
    ordered newest-first (`created_at DESC`).
  - `JournalController`: `create()` (`POST /api/journal`), `index()` (`GET /api/journal`).
- New table `journal_entries` (`id TEXT PRIMARY KEY`, `text TEXT NOT NULL`,
  `created_at TEXT NOT NULL`) — no relation to `consultations`, by design (an entry is never
  "about" a specific reading; SPEC-005's `consultation_notes` already covers that case).
- `apps/web`: `entities/journal/{model.ts,api.ts}`, `pages/journal/JournalPage.vue` at `/journal`,
  linked from the main nav (not shown on public share routes, same as every other nav link).
  `JournalPage` has a text form to add an entry (same shape as `ConsultationPage`'s add-note form)
  and a date-grouped list of existing entries, reusing the exact grouping logic
  `ConsultationHistoryPage.vue` already has (`toLocaleDateString()` as the group key, walking the
  newest-first list once).

## Out of scope

- **A single merged timeline interleaving journal entries with consultations.** The plan's
  phrasing ("on a shared timeline") is read here as "the journal has its own chronological
  timeline, presented the same way consultation history's is" — not literally merging two
  different content types into one combined feed. That's a real, separately-scoped feature
  (needs its own decision about how a consultation-shaped card and a journal-entry-shaped card
  render side by side) that this spec doesn't attempt.
- **Tags, favorites, search, or editing/deleting entries.** This is the first pass at the
  journal — a place to write and read entries chronologically. Every one of those is a real,
  separable enhancement (this app already has tags/favorites/search precedent for consultations
  to extend from later), not silently bundled in.
- **Linking a journal entry to a consultation.** Deliberately the opposite of
  `consultation_notes` — an entry with no foreign key to anything, matching "not tied to a
  specific consultation" from the plan's own description.

## User behavior

```
POST /api/journal {"text": "Feeling like I need to slow down before the next reading."}
  -> 201 {"id": "...", "text": "...", "createdAt": "..."}

GET /api/journal
  -> [ {...newest...}, {...}, ... ]  (newest-first)

/journal
  -> text box + "Add Entry" -> new entry appears at the top of today's date group immediately
  -> "August 21, 2026" heading, entries listed newest-first beneath it, same as /consultations
```

## Functional requirements

- **REQ-JOURNAL-001** — `POST /api/journal` MUST accept `{"text": string}`, reject empty or
  over-5000-character text with `422`, and return `201` with the created entry
  (`id`, `text`, `createdAt`).
- **REQ-JOURNAL-002** — `GET /api/journal` MUST return every entry, newest-first.
- **REQ-JOURNAL-003** — `JournalPage` MUST render a form to add an entry and, on success, show
  the new entry in the list without a full page reload.
- **REQ-JOURNAL-004** — `JournalPage` MUST group entries under a date heading per unique local
  calendar day, newest-first across and within groups — the same presentation
  `ConsultationHistoryPage` already uses.
- **REQ-JOURNAL-005** — An empty journal MUST show a distinct empty-state message, not an empty
  list with no explanation.

## Non-functional requirements

- **REQ-JOURNAL-006** — `journal_entries` has no foreign key to `consultations` or any other
  table.
- **REQ-JOURNAL-007** — No component outside `entities/journal` may call `apiGet`/`apiPost`
  directly for this data.

## Data requirements

New table `journal_entries` (`id`, `text`, `created_at`). No change to any existing table.

## API requirements

Two new endpoints: `POST /api/journal`, `GET /api/journal`. No existing endpoint changes.

## Edge cases

- Text at exactly 5000 characters → accepted (matches `ConsultationNote`'s own boundary
  behavior).
- Text that's only whitespace → rejected as empty, same as a consultation note's own validation.
- Two entries created within the same second → both persist; ordering between them isn't
  specified beyond "newest-first by `created_at`" (matches the existing acceptable tie-break
  looseness this app already has for consultations at the same timestamp — SPEC-005's `findAll()`
  breaks ties with `rowid`, and `SqliteJournalRepository` does the same).

## Acceptance criteria

- [x] `POST /api/journal` creates an entry; empty or over-limit text returns `422`.
- [x] `GET /api/journal` returns all entries newest-first.
- [x] `JournalPage` adds an entry via the form and shows it immediately, grouped under today's
      date heading.
- [x] An empty journal shows a distinct empty-state message.
- [x] `npm run verify` passes end to end.
- [x] Manually verified against the real running API/UI.
