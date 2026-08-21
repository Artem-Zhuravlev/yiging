# SPEC-036 — Outcome-Interpretation Link

**Status:** verified
**Owner:** unassigned
**Last updated:** 2026-08-21

## Problem

Feature 10 of the plan's next batch asks for an explicit link between an AI interpretation and
the outcome later recorded for that consultation ([SPEC-020](../consultation-outcome/spec.md)) —
so a user can genuinely compare "what the AI said would happen" against "what actually happened."
Every AI interpretation in this app is deliberately **not persisted**
([SPEC-008](../ai-interpretation/spec.md)'s stance, reaffirmed by
[SPEC-033](../multi-lens-interpretation/spec.md)/[034](../interpretation-followup/spec.md)) — so
there is normally nothing left to compare against once the page is reloaded. This spec is a
narrow, deliberate exception: when a user explicitly chooses to link an interpretation to their
outcome, a small snapshot (which lens, and its summary sentence — not the full interpretation) is
saved as part of the outcome record itself, because capturing that comparison is the entire point
of this feature.

## Purpose

Let a user, while an interpretation is loaded, explicitly attach it (lens + summary) to the
consultation's outcome record, so the recorded outcome carries its own memory of what was
predicted.

## Scope

- `App\Readings\ConsultationOutcome` gains two optional fields: `interpretationLens: ?string`
  (one of `general`/`psychological`/`practical`/`symbolic`, plain string — not
  `App\AI\InterpretationLens` itself, since `Readings` doesn't depend on `AI`, matching this
  codebase's established no-cross-module-domain-imports convention, e.g. `App\Journal`'s
  self-contained `Clock`/id-generator), `interpretationSummary: ?string` (≤5000 characters, same
  limit as every other outcome field).
- `consultation_outcomes` gains two nullable columns, `interpretation_lens`,
  `interpretation_summary`.
- `Consultation::withUpdatedOutcome()` gains the same two optional parameters, threaded through
  the same present-string-sets/present-null-clears/absent-leaves-unchanged semantics
  [SPEC-019](../consultation-context/spec.md)/[020](../consultation-outcome/spec.md) already
  established for every other outcome field.
- `PATCH /api/consultations/{id}` accepts `interpretationLens`/`interpretationSummary` as two more
  keys in the outcome-field group (present/absent/null handled exactly like
  `whatActuallyHappened`/`outcome`/`reflection` already are); an `interpretationLens` value
  outside the four known lenses is rejected `422`.
- The `outcome` object in every consultation response gains `interpretationLens`/
  `interpretationSummary`.
- `apps/web`: `ConsultationPage.vue` — while an interpretation is loaded for the currently
  selected lens, a "Link to Outcome" button copies that lens and its `summary` into the Outcome
  form's local state (not yet saved — the existing "Save Outcome" button persists it, same as
  every other outcome field already works). The Outcome section shows the currently-linked
  interpretation (if any) with an "Unlink" action that clears both fields on the next save.

## Out of scope

- **Persisting the full interpretation** (all seven fields, not just `summary`). A short
  snapshot is what "compare what was predicted against what happened" actually needs; persisting
  the whole thing would be a much larger, unrequested departure from SPEC-008's "not persisted"
  stance than this feature calls for.
- **Automatically linking the most recently viewed interpretation.** Explicit only — the user
  clicks "Link to Outcome"; nothing links itself, matching how every other outcome field already
  requires an explicit save.
- **Linking a follow-up conversation** ([SPEC-034](../interpretation-followup/spec.md)) to the
  outcome. Only the top-level interpretation (lens + summary), not the Q&A thread underneath it —
  the plan's feature 10 describes linking "the interpretation," not a conversation.
- **Multiple linked interpretations per outcome** (e.g. linking both the General and Practical
  lens's summaries). One link, matching `ConsultationOutcome`'s own "one record per consultation"
  shape ([SPEC-020](../consultation-outcome/spec.md)).
- **`App\Readings` importing `App\AI\InterpretationLens`.** Deliberately kept a plain validated
  string instead, preserving the no-cross-module-domain-imports convention this codebase has
  followed consistently (see `App\Journal`'s self-contained `Clock` rather than reusing
  `App\Readings`'s).

## User behavior

```
/consultations/{id}, after fetching an interpretation for lens "psychological"
  -> "Link to Outcome" button appears
  -> click -> Outcome form's (not-yet-saved) state now shows "Linked: psychological — <summary
     sentence>"
  -> user fills in what actually happened, clicks "Save Outcome"

PATCH /api/consultations/{id}
{"interpretationLens": "psychological", "interpretationSummary": "..."}
  -> 200, outcome object now includes both fields

GET /api/consultations/{id}  (later, any time)
  -> outcome.interpretationLens / outcome.interpretationSummary still there — the recorded
     outcome carries what was predicted, permanently, unlike the interpretation itself which is
     gone as soon as the page reloads

PATCH /api/consultations/{id} {"interpretationLens": null, "interpretationSummary": null}
  -> "Unlink" -> both cleared, rest of the outcome untouched
```

## Functional requirements

- **REQ-OUTLINK-001** — `PATCH /api/consultations/{id}` MUST accept `interpretationLens`/
  `interpretationSummary` with the same present-sets/present-null-clears/absent-leaves-unchanged
  semantics as the other outcome fields.
- **REQ-OUTLINK-002** — A present, non-null `interpretationLens` outside
  `general`/`psychological`/`practical`/`symbolic` MUST return `422`.
- **REQ-OUTLINK-003** — `interpretationSummary` over 5000 characters MUST return `422`.
- **REQ-OUTLINK-004** — Setting `interpretationLens`/`interpretationSummary` MUST NOT alter
  `whatActuallyHappened`/`outcome`/`reflection`/`recordedAt`, or any other consultation field.
- **REQ-OUTLINK-005** — The `outcome` object in every response MUST include
  `interpretationLens`/`interpretationSummary` (`null` when never linked).
- **REQ-OUTLINK-006** — `ConsultationPage` MUST render a "Link to Outcome" control while an
  interpretation is loaded, which populates the (unsaved) outcome form's linked lens/summary —
  persisting it MUST still require the existing "Save Outcome" action.
- **REQ-OUTLINK-007** — `ConsultationPage` MUST show the currently-linked interpretation (if any)
  in the Outcome section, with an unlink action.

## Non-functional requirements

- **REQ-OUTLINK-008** — `App\Readings` MUST NOT import anything from `App\AI` — the lens value
  is validated as a plain string against the four known values, not `App\AI\InterpretationLens`.

## Data requirements

`consultation_outcomes` gains two nullable `TEXT` columns: `interpretation_lens`,
`interpretation_summary`. No relation to any other table.

## API requirements

`PATCH /api/consultations/{id}` request body gains `interpretationLens`/`interpretationSummary`.
Every response's `outcome` object gains the same two keys. No other endpoint changes.

## Edge cases

- Linking an interpretation, then later fetching a *different* lens and linking that instead
  (before saving) → the form simply shows the newest link chosen; nothing is persisted until
  "Save Outcome" is clicked, matching every other outcome field's already-established behavior.
- `interpretationSummary` set but `interpretationLens` left absent (or vice versa) on a `PATCH` →
  allowed structurally (each field has its own independent present/absent/null resolution,
  matching every other outcome field pair) — the UI's own "Link to Outcome" always sets both
  together, but the API itself doesn't require that pairing.
- A consultation whose outcome was recorded before this spec existed → loads with both new fields
  `null`, no error (same backward-compatibility pattern every prior schema addition in this app
  has already proven).

## Acceptance criteria

- [x] `PATCH` sets, clears, and leaves unchanged `interpretationLens`/`interpretationSummary`
      independently, per the standard semantics.
- [x] An invalid `interpretationLens` value returns `422`; over-length `interpretationSummary`
      returns `422`.
- [x] Setting the link doesn't disturb any other outcome or consultation field.
- [x] Every response's `outcome` includes both new fields, `null` by default.
- [x] `ConsultationPage`'s "Link to Outcome" control populates the form; "Save Outcome" persists
      it; "Unlink" clears it.
- [x] A pre-existing (pre-migration) outcome loads with both fields `null`.
- [x] `npm run verify` passes end to end.
- [x] Manually verified against the real running API/UI, including linking a real Gemini-
      generated interpretation to an outcome and confirming it survives a page reload.
