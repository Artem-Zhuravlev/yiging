# SPEC-021 — Follow-up Consultations

**Status:** verified
**Owner:** unassigned
**Last updated:** 2026-08-15

## Problem

Feature 32 of the plan's next batch asks to let consultations be linked together (original →
follow-up → outcome, per the plan's own diagram) with the relationship stored explicitly and
navigable. Today every consultation is an island — `ConsultationHistoryPage` lists them newest-
first with no way to say "this reading was a follow-up to that one," even though the app's own
domain (returning to a question later, per SPEC-020's outcome feature) makes that a real,
recurring pattern. The plan's diagram's third node, "Outcome," isn't a new consultation — it's
already SPEC-020's per-consultation outcome record; the actual new relationship this spec adds is
just the "original → follow-up" link. A follow-up can (and typically will) later record its own
outcome via SPEC-020, composing the two features without new plumbing between them.

## Purpose

Add an explicit, optional `followUpToConsultationId` link from one consultation to an earlier
one, resolved into readable summaries (`{id, question}`) in both directions — `followUpTo` (the
consultation this one follows up on) and `followUps` (consultations that follow up on this one)
— so the UI can navigate the chain by question text, not raw IDs. Settable at creation (the
primary flow: a "Create Follow-up" link from an existing consultation) and editable via the
existing `PATCH /api/consultations/{id}` endpoint.

## Scope

- `consultations` gains a new nullable column `follow_up_to_consultation_id TEXT REFERENCES
  consultations(id) ON DELETE SET NULL` — a plain self-referential foreign key.
- `Consultation` gains `public ?string $followUpToConsultationId = null` and a new
  `withFollowUpTo(?string $followUpToConsultationId): self` wither, validating only that a
  non-null value isn't the consultation's own `id` (a direct self-link) — existence of the
  target and cross-consultation cycle prevention are explicitly out of scope (see below).
  Threaded through every existing wither (`withAddedNote`, `withAddedTag`,
  `withUpdatedContext`, `withUpdatedOutcome`) per the now-standard checklist SPEC-019/020
  established.
- New tiny value object `ConsultationSummary` (`App\Readings`): `id: string`, `question:
  string` — used only for the resolved-link display shape, not the full `Consultation`.
- `ConsultationRepository` gains two read methods: `findSummaryById(string $id):
  ?ConsultationSummary` (resolves `followUpToConsultationId`'s target for display) and
  `findFollowUpSummaries(string $consultationId): list<ConsultationSummary>` (reverse lookup —
  every consultation whose `followUpToConsultationId` points at this one, oldest-first).
- `POST /api/consultations` accepts an optional `followUpToConsultationId` — if present, MUST
  reference an existing consultation (`404`-equivalent `422` if not, checked before creating —
  see "Edge cases") — the primary way this link gets set, from a "Create Follow-up" flow.
- `PATCH /api/consultations/{id}` gains the same key with SPEC-019's now-standard present-
  string-sets/present-null-clears/absent-leaves-unchanged semantics, extending the existing "at
  least one field" `422` check.
- `GET`/`POST`/`PATCH` responses gain `followUpTo: {id, question} | null` and `followUps:
  {id, question}[]` (empty array, not omitted, when there are none) — both resolved server-side,
  no client-side joining.
- `ConsultationPage.vue`: shows "Follow-up to: {question}" (linking to that consultation) when
  set, a "Follow-ups" list (linking to each) when non-empty, and a "Create Follow-up" link to
  `/consultations/new?followUpTo={id}`.
- `NewConsultationPage.vue`: reads `?followUpTo=` from the route query; if present, fetches and
  shows the target consultation's question as context ("Follow-up to: ...") and includes
  `followUpToConsultationId` in the `POST` body.

## Out of scope

- **Cycle detection across multi-hop chains** (A→B→C→A). Only direct self-reference
  (`followUpToConsultationId === id`) is rejected. A user manually engineering a longer cycle via
  repeated `PATCH` calls is a real but obscure edge case nothing in the plan's own description
  asks to guard against; the UI's own flow (linking only ever happens forward, from an existing
  consultation to a brand-new one) never produces one naturally.
- **Multiple "kinds" of link** (e.g. distinguishing "follow-up" from "related to" from
  "supersedes"). The feature asks for one relationship, described one way ("follow-up") — not a
  general-purpose linking system.
- **Deleting the link by deleting either consultation.** No consultation-delete endpoint exists
  in this app at all; `ON DELETE SET NULL` is schema hygiene for a hypothetical future delete
  feature, not something this spec exercises.
- **A dedicated `/api/consultations/{id}/follow-ups` endpoint.** Reuses the existing `PATCH`/
  `GET` endpoints, matching SPEC-019/020's precedent — the relationship lives in the data model,
  not a separate URL.

## User behavior

```
POST /api/consultations
{"question": "Did I make the right call?", "method": "three_coins",
 "followUpToConsultationId": "abc-123"}
  -> 201, new consultation's JSON includes "followUpTo": {"id": "abc-123", "question": "Should I
     take the offer?"}

POST /api/consultations
{"question": "...", "method": "three_coins", "followUpToConsultationId": "does-not-exist"}
  -> 422 {"error": "..."}

PATCH /api/consultations/{id}
{"followUpToConsultationId": null}
  -> 200, link cleared, "followUpTo": null

GET /api/consultations/abc-123 (has one follow-up, "def-456")
  -> 200, "followUps": [{"id": "def-456", "question": "Did I make the right call?"}]

/consultations/abc-123
  -> "Create Follow-up" link -> /consultations/new?followUpTo=abc-123
  -> New Consultation page shows "Follow-up to: Should I take the offer?" and submits with the
     link already attached
```

## Functional requirements

- **REQ-FOLLOWUP-001** — `POST /api/consultations` MUST accept an optional
  `followUpToConsultationId`; if present and non-null, it MUST reference an existing
  consultation or the request MUST respond `422`.
- **REQ-FOLLOWUP-002** — `withFollowUpTo()` MUST reject a value equal to the consultation's own
  `id` (direct self-reference).
- **REQ-FOLLOWUP-003** — `PATCH /api/consultations/{id}` MUST accept `followUpToConsultationId`
  with the same present-string-sets/present-null-clears/absent-leaves-unchanged semantics as
  SPEC-019/020's other optional fields, extending the "at least one field" `422` check.
- **REQ-FOLLOWUP-004** — `GET`/`POST`/`PATCH` responses MUST include `followUpTo` (resolved
  `{id, question}` or `null`) and `followUps` (resolved `{id, question}[]`, `[]` when none).
- **REQ-FOLLOWUP-005** — Setting/clearing a follow-up link MUST NOT alter the target
  consultation's own data, or any other field on the consultation being linked.
- **REQ-FOLLOWUP-006** — Every existing `Consultation` wither (`withAddedNote`, `withAddedTag`,
  `withUpdatedContext`, `withUpdatedOutcome`) MUST preserve an already-set
  `followUpToConsultationId` unchanged.
- **REQ-FOLLOWUP-007** — Existing (pre-SPEC-021) consultations MUST continue to load with
  `followUpTo: null` and `followUps: []`, no error.
- **REQ-FOLLOWUP-008** — `NewConsultationPage` with a `?followUpTo=` query param MUST show the
  target consultation's question and submit `followUpToConsultationId` with the new consultation.

## Non-functional requirements

- **REQ-FOLLOWUP-009** — `followUpTo`/`followUps` resolution happens via two small, indexed-by-
  primary-key repository queries per consultation response — acceptable at this app's scale
  (matches the N+1-per-list-response precedent already accepted for SPEC-014's relationship
  fields on `GET /api/hexagrams`).
- **REQ-FOLLOWUP-010** — No component outside `entities/consultation` may call `apiPost`/
  `apiPatch` directly for this.

## Data requirements

`consultations` gains one nullable `TEXT` column, `follow_up_to_consultation_id`, a
self-referential foreign key (`ON DELETE SET NULL`).

## API requirements

`POST /api/consultations` and `PATCH /api/consultations/{id}` — request bodies gain
`followUpToConsultationId`. `GET`/`POST`/`PATCH` responses gain `followUpTo`/`followUps`. No
endpoint's URL or method changes.

## Edge cases

- `followUpToConsultationId` pointing at a real but different consultation that already has its
  own `followUpToConsultationId` set (a chain of 3+) → works transparently; each consultation
  only stores/resolves its own immediate link, not the whole chain — walking further than one
  hop is a client-side "click through" action (REQ-FOLLOWUP-004's per-consultation resolution is
  sufficient for this; no transitive-closure endpoint is needed).
- A consultation with multiple follow-ups (several consultations all pointing back at the same
  original) → `followUps` lists all of them, oldest-first.

## Acceptance criteria

- [x] `POST /api/consultations` with a valid `followUpToConsultationId` creates the link;
      response includes the resolved `followUpTo` summary.
- [x] `POST /api/consultations` with a `followUpToConsultationId` pointing at nothing → `422`.
- [x] `PATCH` sets, clears (`null`), and leaves unchanged (absent) the link, per the standard
      semantics.
- [x] A consultation's own `id` as its `followUpToConsultationId` → rejected.
- [x] `GET` on a consultation with one or more follow-ups lists them all, oldest-first.
- [x] An existing (pre-migration) consultation loads with `followUpTo: null`, `followUps: []`.
- [x] Every wither method preserves an already-set `followUpToConsultationId`.
- [x] `NewConsultationPage` with `?followUpTo=` shows the target's question and submits the
      link.
- [x] `ConsultationPage` shows the follow-up-to link (if set), the follow-ups list (if any), and
      a working "Create Follow-up" link.
- [x] `npm run verify` passes end to end.
- [x] Manually verified against the real running API/UI: create a follow-up from an existing
      consultation, confirm both directions navigate correctly, confirm an existing
      pre-migration consultation still loads.
