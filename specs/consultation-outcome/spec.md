# SPEC-020 — Consultation Outcome

**Status:** verified
**Owner:** unassigned
**Last updated:** 2026-08-15

## Problem

Feature 31 of the plan's next batch asks to let a user return to a consultation later and record
what actually happened, the outcome, and a reflection — explicitly as a **separate historical
record**, never modifying the original consultation or its interpretation. Today the only way to
add anything after the fact is a note (SPEC-005/013): a free-text journal entry with a
`before`/`after`/`later` label, appended to a list, with no structure distinguishing "what
happened" from "the outcome" from "reflection on it." That's the wrong shape for this feature —
notes stay exactly what they are; outcome needs its own identity.

## Purpose

Add a `ConsultationOutcome` — three independently-settable optional fields
(`whatActuallyHappened`, `outcome`, `reflection`) plus a `recordedAt` timestamp — stored in its
own table (`consultation_outcomes`, one row per consultation) and its own domain value object,
never folded into the `consultations` row itself. Settable/updatable via the existing
`PATCH /api/consultations/{id}` endpoint (three more optional top-level keys, following
SPEC-019's established pattern) rather than a new endpoint, since it's still "update this
consultation's mutable facets" — the separation this spec cares about is in the data model
(a linked entity, not new columns on `consultations`), not necessarily the URL.

## Scope

- New `ConsultationOutcome` readonly value object (`App\Readings`, mirrors `ConsultationNote`'s
  style): `whatActuallyHappened: ?string`, `outcome: ?string`, `reflection: ?string`,
  `recordedAt: \DateTimeImmutable`. Each non-null field capped at 5000 characters (matches
  `ConsultationNote`/SPEC-019's existing limit).
- New table `consultation_outcomes`: `consultation_id TEXT PRIMARY KEY REFERENCES
  consultations(id) ON DELETE CASCADE`, three nullable `TEXT` columns, `recorded_at TEXT NOT
  NULL`. `consultation_id` as the primary key enforces "at most one outcome per consultation" at
  the schema level — this is a 1:1 link, not a list like notes.
- `Consultation` gains `public ?ConsultationOutcome $outcome = null` and a new
  `withUpdatedOutcome(?string $whatActuallyHappened, ?string $outcome, ?string $reflection,
  \DateTimeImmutable $recordedAt): self` wither (same "caller resolves final values, this method
  just validates and rebuilds" shape as SPEC-019's `withUpdatedContext`). Every existing wither
  (`withAddedNote`, `withAddedTag`, `withUpdatedContext`) and both static constructors
  (`create`, `reconstitute`) must carry `$this->outcome`/the new parameter through explicitly —
  the exact class of bug SPEC-019 found and fixed for context fields.
- `PATCH /api/consultations/{id}` gains three more optional top-level keys —
  `whatActuallyHappened`, `outcome`, `reflection` — using the same present-string-sets/
  present-null-clears/absent-leaves-unchanged semantics SPEC-019 established for the five
  context keys. Touching any of the three sets/refreshes `recordedAt` to the current time.
  `outcome` (the field) never existed as a `Consultation` top-level key before, so there's no
  name collision with anything already in the request/response shape.
- `SqliteConsultationRepository`: `save()` upserts the `consultation_outcomes` row whenever
  `$consultation->outcome !== null` (mirrors the notes/tags delete-and-reinsert precedent, but
  as a single-row upsert rather than a list replace, since this is 1:1); `hydrate()` loads it
  (or leaves `outcome` `null` if no row exists — including every consultation that predates this
  spec, exactly like SPEC-019's compatibility requirement for context fields).
- `ConsultationController::toJson()` includes `"outcome": {...} | null` in the response — the
  full three fields plus `recordedAt`, or `null` if never recorded.
- Frontend: `entities/consultation` types/`ConsultationPatch` gain the three fields;
  `ConsultationPage.vue` gains an outcome display + edit form (mirrors SPEC-019's context form:
  pre-filled, single "Save Outcome" button, re-synced from the server response after save).

## Out of scope

- **Multiple outcome entries over time / an outcome history.** The feature says "record... the
  outcome" (singular) — one current, editable record per consultation, not a journal. If a user
  wants a dated trail of how their understanding evolved, that's what notes already do
  (SPEC-005's `later`-labeled notes exist for exactly this).
- **Deleting/clearing the entire outcome record.** Individual fields within it can be cleared to
  `null` via `PATCH` (matching SPEC-019's per-field semantics), but there's no "un-record the
  outcome entirely" operation — not asked for, and `consultation_id` as the primary key makes
  "the outcome doesn't exist yet" (no row) meaningfully different from "the outcome exists but
  all three fields are currently blank" (a row with `recordedAt` set) in a way nothing has asked
  to collapse.
- **Any change to the original consultation, its primary/resulting hexagram, or any stored
  interpretation.** Explicitly ruled out by the feature text itself — `withUpdatedOutcome()`
  touches only the new `outcome` property, structurally incapable of touching anything else.
- **A dedicated `/api/consultations/{id}/outcome` endpoint.** Per "Purpose" above — the
  separation this spec cares about is the data model, not the URL; reusing the existing `PATCH`
  endpoint avoids a near-duplicate endpoint for what's still "update this consultation."

## User behavior

```
PATCH /api/consultations/{id}
{"whatActuallyHappened": "Took the offer.", "outcome": "Started two weeks later, going well."}
  -> 200, "outcome": {"whatActuallyHappened": "Took the offer.",
                       "outcome": "Started two weeks later, going well.",
                       "reflection": null, "recordedAt": "2026-08-15T..."}
     (reflection untouched — never set — stays null; recordedAt is now, since this PATCH did
     touch at least one outcome field)

PATCH /api/consultations/{id}
{"reflection": "Glad I trusted the reading."}
  -> 200, whatActuallyHappened/outcome from before are preserved (only reflection + recordedAt
     change)

GET /api/consultations/{id} (never had an outcome recorded)
  -> 200, "outcome": null

GET /api/consultations/{id} (an existing, pre-SPEC-020 consultation)
  -> 200, "outcome": null — loads exactly as before, no error
```

## Functional requirements

- **REQ-OUTCOME-001** — `PATCH /api/consultations/{id}` MUST accept `whatActuallyHappened`,
  `outcome`, `reflection` as optional top-level keys, alongside the existing `note`/`tag`/five
  context keys (SPEC-013/019).
- **REQ-OUTCOME-002** — Each of the three, present with a string value, MUST set that field;
  present with `null` MUST clear it; absent MUST leave it unchanged — same semantics SPEC-019
  established for context fields.
- **REQ-OUTCOME-003** — Touching any of the three MUST set `recordedAt` to the current time on
  the resulting `ConsultationOutcome`, whether this is the first time an outcome is recorded for
  this consultation or a later edit.
- **REQ-OUTCOME-004** — Each field, when non-null, MUST be capped at 5000 characters — exceeding
  it responds `422`.
- **REQ-OUTCOME-005** — `GET`/`POST`/`PATCH` responses MUST include `"outcome"`: either the full
  `{whatActuallyHappened, outcome, reflection, recordedAt}` object, or `null` if never recorded.
- **REQ-OUTCOME-006** — Recording/editing an outcome MUST NOT change `question`, `method`,
  `primaryHexagram`, `resultingHexagram`, `changingLinePositions`, `notes`, `tags`, or any of the
  five SPEC-019 context fields on the same consultation.
- **REQ-OUTCOME-007** — Existing (pre-SPEC-020) consultations MUST continue to load via `GET`
  with `outcome: null`, no error.
- **REQ-OUTCOME-008** — Every `Consultation` wither method (`withAddedNote`, `withAddedTag`,
  `withUpdatedContext`) MUST preserve an already-set `outcome` unchanged when doing something
  unrelated to it (regression coverage for the exact bug class SPEC-019 found).

## Non-functional requirements

- **REQ-OUTCOME-009** — `consultation_outcomes` is a genuinely separate table (not new columns
  on `consultations`) — the migration only creates the new table, no `ALTER TABLE
  consultations`.
- **REQ-OUTCOME-010** — No component outside `entities/consultation` may call `apiPatch`
  directly for this (mirrors every prior frontend spec's layering rule).

## Data requirements

New table `consultation_outcomes`: `consultation_id` (PK, FK to `consultations.id`, cascade
delete), `what_actually_happened` (nullable TEXT), `outcome` (nullable TEXT), `reflection`
(nullable TEXT), `recorded_at` (TEXT, not null).

## API requirements

`PATCH /api/consultations/{id}` — request body extended as described above.
`GET`/`POST`/`PATCH` responses — `outcome` field added. No endpoint's URL or method changes.

## Edge cases

- `PATCH` touching none of the three outcome keys (and none of note/tag/context either) → `422`,
  same "at least one" rule SPEC-013/019 already enforce, now covering these three keys too.
- A consultation with an outcome row where all three fields have been cleared to `null` (but the
  row exists, with a `recordedAt`) → `outcome` in the response is a non-null object with three
  `null` fields, not collapsed back to `outcome: null` — the row's existence (and its
  `recordedAt`) is itself meaningful history, per "Out of scope" above.

## Acceptance criteria

- [x] `PATCH` with any of the three outcome fields creates/updates the outcome record and
      returns it with the correct `recordedAt`.
- [x] `PATCH` touching only one outcome field leaves the other two (and `notes`/`tags`/context
      fields) unchanged.
- [x] A field over 5000 characters → `422`.
- [x] `GET` on a consultation that never had an outcome recorded → `outcome: null`.
- [x] An existing (pre-migration) consultation still loads with `outcome: null`.
- [x] Recording an outcome does not alter `question`/hexagrams/`changingLinePositions`/existing
      notes, tags, or context fields on the same consultation (verified directly, not just by
      absence of a code path).
- [x] Every wither method (`withAddedNote`, `withAddedTag`, `withUpdatedContext`) preserves an
      already-set outcome.
- [x] `ConsultationPage` has a working outcome display + edit form.
- [x] `npm run verify` passes end to end.
- [x] Manually verified against the real running API/UI, including an existing pre-migration
      consultation and confirming the new migration was applied to the real dev database (not
      just test databases) before testing.
