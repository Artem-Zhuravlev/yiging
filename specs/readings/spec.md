# SPEC-005 — Readings

**Status:** verified
**Owner:** unassigned
**Last updated:** 2026-08-14

## Problem

A cast (SPEC-004) and a structural hexagram (SPEC-002) are meaningless as a *practice* if
nothing remembers what was asked, when, by which method, and what came of it. Without a
persisted `Consultation`, every casting is thrown away the moment the response is returned —
there is no journal, no history, no way to add a note days later about how a reading played
out.

## Purpose

Define the `Consultation` aggregate — the durable record of one divination session — and its
SQLite persistence, so a cast result becomes a reading that can be revisited, annotated, and
listed later.

## Scope

- `Consultation` aggregate: id, question, method used, primary hexagram, resulting hexagram,
  creation time, notes, tags.
- `ConsultationNote` value object: text + label (`before`/`after`/`later`) + timestamp — plan
  section 25's "before / after / later" journaling need.
- `ConsultationRepository` interface (`save`, `findById`, `findAll`) and its
  `SqliteConsultationRepository` implementation.
- SQLite migration for `consultations`, `consultation_notes`, `tags`, `consultation_tags`.
- Two small injected boundaries, following the `CoinTosser` precedent from SPEC-004: `Clock`
  (for `createdAt`) and `ConsultationIdGenerator` (UUIDv4, dependency-free — no new Composer
  package) — both needed so tests are deterministic, not because either does anything complex.

## Out of scope

- Use cases / application services (`CreateConsultation`, `ListConsultations`,
  `UpdateConsultationNotes`, etc.) — that's the Application layer, a later spec once an HTTP
  surface exists to justify it.
- Any HTTP endpoint (`POST /api/consultations`, `GET /api/consultations`, ...) — later spec.
- Search, filtering, sorting, pagination over consultation history — plan section 24, deferred
  until there's an API/UI that needs it.
- Analytics (most frequent hexagrams, repeated questions, etc.) — plan section 26, explicitly a
  post-MVP phase.
- Canonical/translated hexagram *text* storage (`texts`, `translations` tables from the
  original plan sketch) — out of scope here and arguably never needed as DB tables: SPEC-002
  already keeps hexagram reference data (including the pending classical-text pass) as static
  PHP data in `packages/yijing-core`, not a database concern, per REQ-DM-001. A `Consultation`
  only ever references a hexagram by King Wen number.

## User behavior

`Readings` has no UI or HTTP surface yet — it is consumed by a future Application/API layer.
"User behavior" here means the contract other code relies on:

```
$consultation = Consultation::create(
    id: $idGenerator->generate(),
    question: 'Should I take the offer?',
    method: CastingMethodName::ThreeCoins,
    primaryHexagram: $hexagram,          // from a DivinationMethod::cast() (SPEC-004)
    createdAt: $clock->now(),
);
  → immutable Consultation, resultingHexagram derived automatically via
    $primaryHexagram->getResultingHexagram() (SPEC-002), notes/tags start empty

$consultation = $consultation->withAddedNote(
    new ConsultationNote(NoteLabel::After, 'Took the offer.', $clock->now()),
);
  → new Consultation instance with the note appended; original instance unchanged

$repository->save($consultation);
$repository->findById($consultation->id);
  → the exact same Consultation, reconstructed from storage
```

## Functional requirements

### Consultation aggregate

- **REQ-READ-001** — `Consultation::create()` MUST require: id, question (non-empty string),
  `CastingMethodName`, primary `Hexagram`, and `createdAt`. `resultingHexagram` MUST be derived
  automatically via `Hexagram::getResultingHexagram()` (SPEC-002), never passed in separately,
  so it can never disagree with the primary hexagram's changing lines.
- **REQ-READ-002** — `Consultation` MUST be an immutable value object. `withAddedNote()` and
  `withAddedTag()` MUST return a new instance and MUST NOT mutate the receiver.
- **REQ-READ-003** — `Consultation::changingLinePositions(): list<int>` MUST be derived from
  the primary hexagram's lines (`changing === true`), never stored as independent state, so it
  cannot drift out of sync with the hexagram it describes.
- **REQ-READ-004** — `withAddedTag()` MUST be idempotent — adding a tag that is already present
  MUST NOT create a duplicate entry.
- **REQ-READ-005** — `question` MUST NOT be empty/whitespace-only, and MUST NOT exceed 2000
  characters; construction MUST throw otherwise. The upper bound exists per the plan's own
  security checklist ("limit the size of user input," section 31) — an unbounded question would
  let a client store arbitrarily large text in every consultation row and, once a real
  `InterpretationProvider` (SPEC-008) exists, get sent whole into an LLM prompt at real cost.
  2000 characters is generous for what is, in every realistic case, a sentence or a short
  paragraph.

### ConsultationNote

- **REQ-READ-006** — A `ConsultationNote` MUST record its text (non-empty, and no more than
  5000 characters — same rationale as REQ-READ-005, sized larger since a reflective journal
  note is reasonably longer than a question), a `NoteLabel` (`Before`, `After`, or `Later`), and
  a timestamp. Notes are ordered by insertion, not re-sorted by timestamp (a `Later` note is
  still appended after earlier ones by construction order, matching how the user actually wrote
  them).

### Persistence

- **REQ-READ-007** — `ConsultationRepository::save()` MUST be an upsert: saving a
  `Consultation` whose `id` already exists MUST replace its stored state (including notes and
  tags) rather than erroring or duplicating rows.
- **REQ-READ-008** — `ConsultationRepository::findById()` MUST return `null` (not throw) when
  no consultation with that id exists.
- **REQ-READ-009** — `ConsultationRepository::findAll()` MUST return consultations ordered
  newest-first (`createdAt` descending).
- **REQ-READ-010** — A round trip (`save()` then `findById()`) MUST reproduce an equal
  `Consultation`: same id, question, method, primary/resulting hexagram (including which lines
  were changing), createdAt, notes (with labels and timestamps), and tags.
- **REQ-READ-011** — Deleting a consultation's row (out of scope to implement a `delete()`
  method yet, but schema-level) MUST cascade to its notes and tag associations
  (`ON DELETE CASCADE`), so no orphaned rows are possible once delete is added later.

## Non-functional requirements

- **REQ-READ-012** — `apps/api/src/Readings` depends on `packages/yijing-core` (for
  `Hexagram`) and PDO (for persistence) — it MUST NOT depend on `App\Casting` (SPEC-004);
  `Consultation::create()` takes an already-cast `Hexagram` plus a `CastingMethodName` label,
  not a `DivinationMethod` — keeping which-method-produced-this decoupled from how-it-gets-cast.
- **REQ-READ-013** — `ConsultationIdGenerator`'s default implementation MUST produce RFC
  4122-compliant UUIDv4 strings using `random_bytes()` — no new Composer dependency for this.
- **REQ-READ-014** — All SQL MUST use prepared statements (PDO parameter binding) — no string
  interpolation of user-supplied values into SQL.

## Data requirements

New tables (SQLite), via `apps/api/database/migrations/`:

```sql
CREATE TABLE consultations (
    id TEXT PRIMARY KEY,
    question TEXT NOT NULL,
    method TEXT NOT NULL,                    -- CastingMethodName value
    primary_king_wen_number INTEGER NOT NULL,
    changing_line_positions TEXT NOT NULL,   -- JSON array of ints, e.g. "[1,4]"
    resulting_king_wen_number INTEGER NOT NULL,
    created_at TEXT NOT NULL                 -- ISO 8601
);

CREATE TABLE consultation_notes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    consultation_id TEXT NOT NULL REFERENCES consultations(id) ON DELETE CASCADE,
    label TEXT NOT NULL,                     -- 'before' | 'after' | 'later'
    text TEXT NOT NULL,
    created_at TEXT NOT NULL,
    sort_order INTEGER NOT NULL              -- insertion order, since a note can be added
                                              -- same-second as another
);

CREATE TABLE tags (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE
);

CREATE TABLE consultation_tags (
    consultation_id TEXT NOT NULL REFERENCES consultations(id) ON DELETE CASCADE,
    tag_id INTEGER NOT NULL REFERENCES tags(id) ON DELETE CASCADE,
    PRIMARY KEY (consultation_id, tag_id)
);
```

`primary_king_wen_number` + `changing_line_positions` fully reconstruct the primary `Hexagram`
(via `HexagramCatalog::entryFor()`'s pattern + `Hexagram::fromLines()`, both already public in
`yijing-core`) — no need to store all 6 lines' raw polarity, since polarity is implied by the
King Wen number. `resulting_king_wen_number` is derived data, stored redundantly so it's
queryable/displayable without reconstructing the primary hexagram first.

## API requirements

None — see "Out of scope."

## Edge cases

- Zero changing lines → `changing_line_positions` stored as `"[]"`; `resulting_king_wen_number`
  equals `primary_king_wen_number` (falls out of SPEC-002 REQ-HX-007 with no special-casing
  here).
- `findById()` for an id that was never saved → `null`, not an exception.
- Saving the same `Consultation` id twice with different notes/tags → second save wins
  entirely for notes/tags (upsert replaces the note/tag rows, not merges them) — the aggregate
  passed to `save()` is always the full, current state.
- Empty `question` → throws at `Consultation::create()`, never reaches persistence.
- `question` at exactly 2000 characters → accepted (the limit is inclusive); 2001 characters →
  throws. Same inclusive-boundary rule for `ConsultationNote.text` at 5000 characters.

## Acceptance criteria

- [x] `Consultation::create()` builds an aggregate with a correctly derived
      `resultingHexagram` and empty notes/tags.
- [x] `withAddedNote()` / `withAddedTag()` are pure (return new instances, never mutate).
- [x] `withAddedTag()` is idempotent for duplicate tags.
- [x] `changingLinePositions()` always matches the primary hexagram's `changing` lines.
- [x] `SqliteConsultationRepository::save()` + `findById()` round-trips a `Consultation`
      exactly (id, question, method, primary/resulting hexagram + changing lines, createdAt,
      notes with labels/timestamps in order, tags) — covered by a test that builds a
      non-trivial consultation (multiple notes, multiple tags, several changing lines) and
      asserts full equality after round-tripping.
- [x] `save()` on an existing id upserts (no duplicate/orphaned rows) — covered by a test that
      saves, mutates, re-saves, and re-reads.
- [x] `findAll()` returns newest-first.
- [x] `findById()` for a missing id returns `null`.
- [x] Migration creates all 4 tables with foreign keys enforced (`PRAGMA foreign_keys = ON`,
      already set in `Database::connect()`) — applied and inspected against the dev database.
- [x] `apps/api/src/Readings` has zero dependency on `App\Casting`.
- [x] `question` over 2000 characters and `ConsultationNote.text` over 5000 characters are
      rejected; exactly at the limit is accepted (inclusive boundary).

`apps/api/src/Readings` implements `CastingMethodName`, `NoteLabel`, `ConsultationNote`,
`Consultation`, `Clock`/`SystemClock`, `ConsultationIdGenerator`/`UuidV4ConsultationIdGenerator`,
`ConsultationRepository`/`SqliteConsultationRepository` — 12 tests added (30 total in
`apps/api`, 169 assertions). `npm run verify` passes end to end (web + api + yijing-core).

**Addendum (found during SPEC-006):** `findAll()`'s original tiebreak (`id DESC`, a UUID with
no relation to insertion order) could return same-second consultations in the wrong order —
not caught here because this spec's own tests never created two consultations fast enough to
land in the same `created_at` second. SPEC-006's HTTP-level test did. Fixed by tie-breaking on
SQLite's implicit `rowid` instead. See [SPEC-006](../consultation-api/spec.md).

**Addendum (2026-08-14, input-size hardening):** neither `Consultation::create()` nor
`ConsultationNote` originally bounded the length of user-supplied text, missing the plan's own
security checklist item (section 31, "limit the size of user input"). Added REQ-READ-005's
2000-character cap on `question` and REQ-READ-006's 5000-character cap on note `text` —
enforced the same way the existing empty-string check already was (throws
`\InvalidArgumentException`, caught by `ConsultationController`'s existing `422` handling — no
controller changes needed). `NewConsultationPage.vue`'s question field also gained a matching
`maxlength` HTML attribute for immediate client-side feedback; the backend limit remains the
actual enforcement (never trust client-side-only validation).
