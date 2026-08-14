# SPEC-013 — Consultation Notes & Tags Editing

**Status:** verified
**Owner:** unassigned
**Last updated:** 2026-08-15

## Problem

The original plan's Definition of Done (section 34) explicitly lists "add a note" as a
required step in the MVP flow — question → cast → hexagram → changing lines → resulting
hexagram → canonical text → AI interpretation → **add a note** → save → find in history. This
was never built: `Consultation::withAddedNote()`/`withAddedTag()` have existed since SPEC-005,
`ConsultationPage` has displayed notes/tags read-only since SPEC-009, but nothing between them
— no `PATCH` endpoint, no UI — ever let a person actually add one. SPEC-006 deferred this as
"no concrete UI need yet," which was a misreading: the plan already specified the need.

## Purpose

Add `PATCH /api/consultations/{id}` (exactly the endpoint the plan's own API list names,
section 21) to append a note and/or a tag to an existing consultation, and a small form on
`ConsultationPage` to use it — completing the MVP flow's one missing step.

## Scope

- `PATCH /api/consultations/{id}`: body `{"note": {"label": "before"|"after"|"later", "text":
  "..."}}` and/or `{"tag": "..."}` (both keys optional, at least one required) — appends via
  `Consultation::withAddedNote()`/`withAddedTag()` (both already exist, SPEC-005), saves via
  the existing `ConsultationRepository::save()` (already an upsert, SPEC-005), returns the
  updated `Consultation` in the same JSON shape `POST`/`GET` already use.
- `NewNotePage`-equivalent UI: not a separate page — a small form directly on
  `ConsultationPage` (label select, text field, "Add Note" button) and a small tag-adding
  control (text field, "Add Tag" button), both calling the new endpoint and updating the
  page's already-rendered consultation in place on success.
- `entities/consultation` gains `updateConsultation(id, patch)`.

## Out of scope

- **Editing or deleting an existing note/tag.** The plan's own flow only ever asks to *add* a
  note; notes are explicitly append-only/immutable-once-written in the domain model
  (`Consultation::withAddedNote()` only appends, `ConsultationNote` has no update path) — this
  spec doesn't change that. Editing/removal is a different, larger decision (should a
  historical journal entry be mutable?) not asked for here.
- **Removing a tag.** Same reasoning — `withAddedTag()` only adds; this spec doesn't add a
  remove capability that doesn't otherwise exist.
- **Bulk operations** (adding several notes/tags in one call). One note and/or one tag per
  `PATCH` call, matching one form submission = one action.
- **A general-purpose `PATCH` supporting arbitrary field updates** (e.g. changing the
  question). Only `note`/`tag` are recognized keys — this endpoint does exactly what the plan
  asked for, not a generic update mechanism nothing has asked for.

## User behavior

```
PATCH /api/consultations/{id}
{"note": {"label": "after", "text": "Took the offer."}}
  -> 200, the updated Consultation JSON, with the new note appended (in order, after any
     existing notes)

PATCH /api/consultations/{id}
{"tag": "career"}
  -> 200, updated Consultation, tag appended (deduped if already present - withAddedTag()'s
     existing idempotence, SPEC-005 REQ-READ-004)

PATCH /api/consultations/{id}
{}
  -> 422, {"error": "..."} (neither "note" nor "tag" present - nothing to do)

PATCH /api/consultations/does-not-exist
{"tag": "career"}
  -> 404, {"error": "Not Found"}

On /consultations/{id}:
  -> "Add a note" form (label, text, submit) and "Add a tag" form (text, submit), both below
     the existing notes/tags display
  -> submitting either calls PATCH and updates the page's notes/tags list immediately, without
     a full page reload
```

## Functional requirements

- **REQ-EDIT-001** — `PATCH /api/consultations/{id}` MUST accept an optional `note` object
  (`label`: one of `before`/`after`/`later`, `text`: string) and/or an optional `tag` string,
  and MUST respond `422` if neither is present.
- **REQ-EDIT-002** — A valid `note` MUST be appended via `Consultation::withAddedNote()` (which
  already enforces `ConsultationNote`'s non-empty/max-5000-character validation, SPEC-005
  REQ-READ-006) — invalid `note.label` or a `note.text` failing that validation MUST respond
  `422` with the underlying validation message, not a generic error.
- **REQ-EDIT-003** — A valid `tag` MUST be appended via `Consultation::withAddedTag()`, which
  is already idempotent for a duplicate (SPEC-005 REQ-READ-004) — adding an existing tag again
  MUST NOT error and MUST NOT duplicate it.
- **REQ-EDIT-004** — Both a `note` and a `tag` MAY be provided in the same request; both are
  applied (in that order — note, then tag) before a single `save()`.
- **REQ-EDIT-005** — `PATCH /api/consultations/{id}` for an unknown `id` MUST respond `404`
  before attempting to parse/validate the body's `note`/`tag` — same lookup-first order every
  other single-consultation endpoint already uses.
- **REQ-EDIT-006** — On success, the response MUST be the complete, updated `Consultation` in
  the exact JSON shape `POST`/`GET /api/consultations/{id}` already return — not a partial
  "note added" acknowledgement — so the frontend can replace its rendered state wholesale
  without a follow-up `GET`.
- **REQ-EDIT-007** — `ConsultationPage`'s note form MUST clear on successful submission and
  MUST show the new note in the page's notes list immediately, without a full page
  reload/re-fetch. Same for the tag form.
- **REQ-EDIT-008** — A failed submission (validation error, network error) MUST show an inline
  error scoped to the form that failed, leaving the rest of the already-rendered page (and the
  other form) unaffected — matches the established pattern from SPEC-010's interpretation
  section.

## Non-functional requirements

- **REQ-EDIT-009** — `ConsultationController::update()` MUST contain no validation logic of
  its own beyond parsing the request shape (which keys are present) — `Consultation`/
  `ConsultationNote`'s existing validation (SPEC-005) is what's authoritative, not duplicated
  here.
- **REQ-EDIT-010** — No component outside `entities/consultation` may call `apiPatch`/`fetch`
  directly for this (mirrors every prior frontend spec's layering rule).

## Data requirements

None — no schema change; `ConsultationRepository::save()`'s existing upsert already persists
notes/tags fully (SPEC-005).

## API requirements

`PATCH /api/consultations/{id}` — see "User behavior"/"Functional requirements" above. All
other `POST /api/consultations`/`GET /api/consultations`/`GET /api/consultations/{id}` behavior
is unchanged.

## Edge cases

- `note.text` over 5000 characters or empty → `422`, the exact message
  `ConsultationNote`'s constructor already throws (SPEC-005) — not reworded here.
- `tag` an empty string → `422` (no validation currently exists on tag content beyond
  `withAddedTag()`'s dedup; an empty-string tag would need its own guard — added here since
  nothing upstream currently rejects it and a blank tag is never useful).
- Adding the same tag twice (two separate `PATCH` calls) → second call succeeds, `200`, tag
  list unchanged (already idempotent, SPEC-005).
- `note.label` missing or not one of the three valid values → `422`.

## Acceptance criteria

- [x] `PATCH /api/consultations/{id}` with a valid note appends it and returns the full updated
      consultation.
- [x] `PATCH /api/consultations/{id}` with a valid tag appends it (deduped on repeat).
- [x] Both `note` and `tag` in one request apply both.
- [x] Neither `note` nor `tag` present → `422`.
- [x] Unknown `id` → `404`, checked before body validation.
- [x] Invalid note (empty/over-length text, bad label) → `422` with the domain's own message.
- [x] `ConsultationPage` has working "add a note" and "add a tag" forms, each with its own
      loading/error state, updating the page in place on success.
- [x] Feature tests run against the real `Kernel`/routing stack (backend) and mock `fetch`
      (frontend), matching every prior spec's established pattern.
- [x] `npm run verify` passes end to end.
- [x] Manually verified against the real running API/UI: add a note, add a tag, see both
      reflected immediately.
