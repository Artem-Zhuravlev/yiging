# SPEC-028 — Consultation History Backup (Export/Import JSON)

**Status:** verified
**Owner:** unassigned
**Last updated:** 2026-08-21

## Problem

Feature 20 of the plan's next batch (data portability) asks for exporting/importing the full
consultation history as JSON — a client-side backup, no external storage, matching
[SPEC-001](../project-architecture/spec.md)'s no-Docker/no-Redis/no-external-service posture. The
app has no delete endpoint and no accounts; a user's only copy of their history today lives in one
SQLite file with no user-facing way to get it out or back in.

## Purpose

Let a user download their entire consultation history as one JSON file (a plain browser download,
no server round-trip beyond the existing `GET /api/consultations`), and later restore it — exactly,
including original IDs, timestamps, hexagrams, notes, tags, context, outcome, favorite flag, and
follow-up links — via a new import endpoint, so a genuine backup/restore actually reproduces the
original data rather than an approximation with today's date stamped on everything.

## Scope

- **Export** (`ConsultationHistoryPage.vue`): an "Export Backup (JSON)" button that takes the
  already-fetched `GET /api/consultations` response array and triggers a browser download
  (`Blob` + a temporary `<a download>`, no new request) named
  `yijing-backup-<YYYY-MM-DD>.json`.
- **Import**: `POST /api/consultations/import` (new) accepts a JSON array in exactly the shape
  `GET /api/consultations` produces (so "download the file, re-upload the same file" round-trips
  losslessly by construction) and, in one transaction:
  - Rejects the whole batch (`422`) if any item's `id` already exists in `consultations`, or if
    any `followUpToConsultationId` doesn't resolve to either another item in the same batch or an
    already-existing consultation.
  - Otherwise inserts every item using `Consultation::reconstitute()` (the same factory
    `SqliteConsultationRepository::hydrate()` already uses for "trusted, previously-validated
    state") — preserving the original `id`, `createdAt`, hexagrams (rebuilt from
    `primaryHexagram.kingWenNumber` + `changingLinePositions`, `resultingHexagram.kingWenNumber`),
    notes, tags, context fields, outcome, favorite flag, and follow-up link, in two passes (insert
    all rows first with follow-up links deferred, then set the links) so cross-references within
    the same batch resolve regardless of array order.
  - Returns `201` with `{"imported": <count>}`.
- Frontend: `ConsultationHistoryPage.vue` gains an "Import Backup (JSON)" file-picker button;
  selecting a file reads it as text, `JSON.parse`s it, `POST`s to `/api/consultations/import`,
  shows a success/error message, and refreshes the visible list on success.

## Out of scope

- **Partial-success / per-item import reporting.** All-or-nothing per batch — matches how this
  API already treats validation elsewhere (`PATCH`/`POST` reject the whole request on any
  problem, never partially apply). A more forgiving "import what's valid, report what's not" mode
  is a real but separable enhancement.
- **Overwriting or merging with an existing consultation of the same ID.** Any ID collision
  rejects the entire batch — the safe default for a personal-history restore, where silently
  overwriting real data because a backup was re-imported by accident would be a genuinely bad
  failure mode.
- **A UI preview of the backup file's contents before importing.** The button reads and submits
  the selected file directly; reviewing/editing before import is a separate, larger feature.
- **Scheduled or automatic backups.** Manual export/import only, matching the plan's own framing
  ("client-side backup") and this app's total absence of any background job runner.
- **Encrypting the exported file.** It's the user's own local download; no new capability is
  needed beyond what any other file on their machine already has.
- **Importing a file produced by a different, incompatible schema version.** The import endpoint
  validates structurally (required fields, resolvable follow-up links) but doesn't attempt to
  migrate an older/foreign export shape — this app has had no schema-breaking export shape change
  yet, so nothing to migrate from.

## User behavior

```
/consultations
  -> "Export Backup (JSON)" -> downloads yijing-backup-2026-08-21.json (the exact
     GET /api/consultations array)

/consultations
  -> "Import Backup (JSON)" -> file picker -> select yijing-backup-2026-08-21.json
  -> POST /api/consultations/import [...]
  -> 201 {"imported": 12} -> success message, list refreshes showing all 12 restored
     consultations with their original dates/IDs/notes/tags/context/outcome/favorite/follow-ups

Re-importing the same file a second time
  -> 422 (every ID already exists) -> clear error message, nothing is duplicated or overwritten
```

## Functional requirements

- **REQ-BACKUP-001** — The "Export Backup (JSON)" button MUST trigger a browser download of the
  exact array `GET /api/consultations` returns, as a `.json` file, with no additional network
  request.
- **REQ-BACKUP-002** — `POST /api/consultations/import` MUST accept a JSON array matching the
  `GET /api/consultations` response shape.
- **REQ-BACKUP-003** — If any item's `id` in the import batch already exists in `consultations`,
  the entire request MUST fail with `422` and no data MUST be written.
- **REQ-BACKUP-004** — If any item's `followUpToConsultationId` doesn't resolve to either another
  item in the same batch or an existing consultation, the entire request MUST fail with `422` and
  no data MUST be written.
- **REQ-BACKUP-005** — On success, every item MUST be inserted preserving its original `id`,
  `createdAt`, `question`, `method`, primary/resulting hexagram (including changing lines), notes
  (with their own original `createdAt`), tags, all five context fields, outcome (including its own
  `recordedAt`), `favorite`, and `followUpToConsultationId`.
- **REQ-BACKUP-006** — A successful import MUST return `201` with `{"imported": <count>}`.
- **REQ-BACKUP-007** — `ConsultationHistoryPage` MUST offer an import file picker that reads the
  selected file, submits it, shows a success or error message, and refreshes the visible list on
  success.

## Non-functional requirements

- **REQ-BACKUP-008** — The whole import MUST be applied in a single database transaction — a
  mid-batch failure MUST leave `consultations`/`consultation_notes`/`tags`/`consultation_tags`/
  `consultation_outcomes` completely unchanged.
- **REQ-BACKUP-009** — No component outside `entities/consultation` may call `apiPost` directly
  for the import request.

## Data requirements

None — no schema change. Reuses every existing table.

## API requirements

New endpoint: `POST /api/consultations/import`. Request body: a JSON array of consultation
objects (the `GET /api/consultations` shape). Response: `201 {"imported": <count>}` on success,
`422 {"error": "..."}` on any validation failure (duplicate ID, unresolved follow-up link,
malformed item).

## Edge cases

- An empty array (`[]`) → `201 {"imported": 0}`, a no-op, not an error.
- Two items in the same batch that are follow-ups of each other (A follows up on B, B follows up
  on A) → both resolve fine structurally (each references an ID present in the batch); no cycle
  detection is attempted, matching [SPEC-021](../consultation-follow-ups/spec.md)'s own explicit
  "no cross-consultation cycle detection" scope decision.
- A batch containing a consultation whose `followUpToConsultationId` points at a *real, already-
  existing* consultation (not part of this batch) → resolves correctly; the check only needs "does
  this ID exist somewhere reachable," not "is it in this batch."
- Malformed JSON in the uploaded file → the frontend catches the `JSON.parse` error before ever
  sending a request, shows an inline error, never calls the API with garbage.

## Acceptance criteria

- [x] Export downloads a `.json` file containing the exact current history.
- [x] Importing that exact file into a fresh database round-trips every field (id, createdAt,
      hexagrams, changing lines, notes with their own createdAt, tags, all context fields,
      outcome with its own recordedAt, favorite, follow-up links).
- [x] Re-importing a file whose IDs already exist fails with `422`, no data duplicated or altered.
- [x] An unresolvable follow-up link in the batch fails the whole import with `422`.
- [x] An empty array imports as a no-op success (`imported: 0`).
- [x] `ConsultationHistoryPage` shows success/error feedback and refreshes the list after import.
- [x] `npm run verify` passes end to end.
- [x] Manually verified against the real running API/UI: exported real seeded history, imported
      it into a throwaway fresh database, confirmed every field round-tripped.
