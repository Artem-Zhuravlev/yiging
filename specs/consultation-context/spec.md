# SPEC-019 — Rich Consultation Context

**Status:** verified
**Owner:** unassigned
**Last updated:** 2026-08-15

## Problem

Feature 30 of the plan's next batch asks to extend consultations with optional context fields
beyond the question itself: context, what happened before, what the user wants to understand,
background information, and the user's own initial interpretation — so a consultation can carry
the fuller picture the user had in mind when they cast, not just the one-line question. Today
`Consultation` has exactly one free-text field (`question`, required, capped at 2000 characters);
nothing captures the surrounding situation.

## Purpose

Add five new, entirely optional, independently-settable text fields to `Consultation` —
`context`, `whatHappenedBefore`, `whatUserWantsToUnderstand`, `backgroundInformation`, and
`initialInterpretation` — settable at creation time (`POST /api/consultations`) and editable
afterward (`PATCH /api/consultations/{id}`, extending SPEC-013's existing partial-update
endpoint rather than adding a new one). Existing consultations, which have none of these fields,
must continue to load and behave exactly as before.

## Scope

- `Consultation` gains five new nullable, independently-set string properties (not a list like
  `notes`/`tags` — each is a single current value, overwritten on update, not appended to).
  Each capped at 5000 characters when non-null, matching `ConsultationNote`'s existing cap
  (SPEC-005) — these are free-form paragraphs, not short labels.
- `Consultation::create()` accepts all five as optional parameters (default `null`).
- A new `Consultation::withUpdatedContext(...)` wither (mirrors `withAddedNote`/`withAddedTag`'s
  existing immutable-rebuild style) taking the five fields' *final* values — the caller (the
  controller) resolves "keep existing value" vs. "apply new value" before calling it, so the
  domain method itself stays simple (no sentinel/"undefined" handling inside the aggregate).
- Migration: five new nullable `TEXT` columns on `consultations` (`ALTER TABLE ... ADD COLUMN`,
  additive and backward-compatible — existing rows get `NULL` automatically).
- `POST /api/consultations` accepts all five as optional top-level JSON keys.
- `PATCH /api/consultations/{id}` (SPEC-013) accepts the same five keys, alongside the existing
  `note`/`tag`. Each key, if present in the body, sets that field (a string value) or **clears**
  it (`null` value) — this is the first field in this API where "present but `null`" is a
  meaningful, distinct signal from "absent," so `update()` gains explicit `array_key_exists()`
  presence checks rather than reusing the `$body['x'] ?? null` shorthand `note`/`tag` use (that
  shorthand can't distinguish "absent" from "explicitly null," which doesn't matter for
  note/tag's append-only semantics but does matter here).
- `GET`/`POST`/`PATCH` responses (`ConsultationController::toJson()`) include all five fields
  (`null` when unset).
- `entities/consultation` (frontend): types, `NewConsultationRequest`/`ConsultationPatch`
  extended, `Consultation` type gains the five fields.
- `NewConsultationPage.vue`: five new optional textareas, collapsed under a single "Add more
  context (optional)" disclosure so the primary question-first flow isn't cluttered by default.
- `ConsultationPage.vue`: displays any set context fields, plus an edit form (mirroring SPEC-013's
  note/tag forms) to set or update them after the fact.

## Out of scope

- **Making any of the five fields required.** All optional, by the feature's own wording
  ("extend... with *optional* context fields").
- **Versioning/history of context field edits.** Unlike notes (an append-only journal by design,
  SPEC-005), these are single current-value fields — editing overwrites, no history kept. If a
  user wants a timestamped record of how their thinking evolved, that's what notes are for.
- **A generic key-value "custom fields" mechanism.** Five fixed, named, well-understood fields —
  not a schema-less extension point nothing has asked for.
- **Clearing note/tag to `null`.** Out of scope for this spec — note/tag's existing append-only
  `update()` behavior (SPEC-013) is unchanged; only the five new fields get explicit-clear
  semantics.

## User behavior

```
POST /api/consultations
{"question": "Should I take the offer?", "method": "three_coins",
 "context": "Been considering this for weeks.",
 "whatHappenedBefore": "Received the offer last Tuesday.",
 "whatUserWantsToUnderstand": "Whether the timing is right.",
 "backgroundInformation": "Currently employed, offer is from a competitor.",
 "initialInterpretation": "Feels like a yes, but I'm anxious."}
  -> 201, full Consultation JSON including all five fields as given

POST /api/consultations
{"question": "Quick one.", "method": "three_coins"}
  -> 201, all five context fields null (fully optional, no behavior change from today)

PATCH /api/consultations/{id}
{"context": "Updated context.", "whatHappenedBefore": null}
  -> 200, context set to the new string, whatHappenedBefore cleared to null, every other field
     (including the other three context fields) unchanged

GET /api/consultations/{id} (an existing, pre-SPEC-019 consultation)
  -> 200, all five new fields present as null — loads exactly as before, no error
```

## Functional requirements

- **REQ-CTX-001** — `Consultation::create()` MUST accept `context`, `whatHappenedBefore`,
  `whatUserWantsToUnderstand`, `backgroundInformation`, `initialInterpretation` as optional
  parameters, each defaulting to `null`.
- **REQ-CTX-002** — Each of the five fields, when non-null, MUST be capped at 5000 characters
  (mirroring `ConsultationNote`'s existing validation) — exceeding it throws
  `\InvalidArgumentException`, surfaced by the controller as `422`.
- **REQ-CTX-003** — `POST /api/consultations` MUST accept all five fields as optional top-level
  JSON keys; omitting all five MUST behave exactly as it did before this spec (all `null`).
- **REQ-CTX-004** — `PATCH /api/consultations/{id}` MUST accept all five fields as optional
  top-level JSON keys, alongside the existing `note`/`tag`. A key present with a string value
  MUST set that field; present with `null` MUST clear it; absent MUST leave it unchanged.
- **REQ-CTX-005** — `PATCH /api/consultations/{id}` with a body containing none of
  `note`/`tag`/the five context keys MUST still respond `422` (extends SPEC-013's REQ-EDIT-001
  "at least one" rule to cover the new keys too).
- **REQ-CTX-006** — `GET`/`POST`/`PATCH` responses MUST include all five fields (`null` when
  unset) in the consultation JSON shape, consistently across all three actions.
- **REQ-CTX-007** — Existing consultations (rows with no value in the five new columns) MUST
  continue to load via `GET /api/consultations/{id}`/`GET /api/consultations` without error,
  with all five fields `null`.

## Non-functional requirements

- **REQ-CTX-008** — The five new database columns MUST be added via an additive
  `ALTER TABLE ... ADD COLUMN` migration — no destructive schema change, no data migration
  needed for existing rows.
- **REQ-CTX-009** — No component outside `entities/consultation` may call `apiPost`/`apiPatch`
  directly for this (mirrors every prior frontend spec's layering rule, SPEC-006/013).

## Data requirements

`consultations` table gains five nullable `TEXT` columns: `context`, `what_happened_before`,
`what_user_wants_to_understand`, `background_information`, `initial_interpretation`.

## API requirements

`POST /api/consultations` and `PATCH /api/consultations/{id}` — request bodies extended as
described above. `GET /api/consultations`/`GET /api/consultations/{id}` — response shape
extended. No endpoint's URL, method, or unrelated behavior changes.

## Edge cases

- A field exceeding 5000 characters on `POST` → `422` with the domain's own message (mirrors
  `ConsultationNote`'s existing message pattern).
- `PATCH` with `{"context": ""}` (empty string, not `null`) → sets `context` to an empty string,
  not treated as "clear" — only an explicit JSON `null` clears a field. (An empty string is a
  slightly unusual thing to intentionally set, but not worth a special-cased rejection nothing
  asked for.)
- `PATCH` with a non-string, non-null value (e.g. a number) for any of the five keys → `422`.

## Acceptance criteria

- [x] `POST /api/consultations` with all five context fields creates a consultation whose
      `GET` response includes them exactly as submitted.
- [x] `POST /api/consultations` omitting all five behaves identically to pre-SPEC-019 behavior
      (all five `null` in the response).
- [x] `PATCH /api/consultations/{id}` sets a context field via a string value and clears one via
      an explicit `null`, leaving untouched fields (including the other four context fields and
      existing notes/tags) unchanged.
- [x] A field over 5000 characters → `422`.
- [x] An existing (pre-migration) consultation still loads correctly with all five fields `null`.
- [x] `NewConsultationPage` has working optional context inputs, collapsed by default.
- [x] `ConsultationPage` displays set context fields and has a working edit form for them.
- [x] `npm run verify` passes end to end.
- [x] Manually verified against the real running API/UI: create with context, edit context,
      clear a field, confirm an existing pre-migration consultation still loads.
