# SPEC-035 — Interpretation Profile

**Status:** verified
**Owner:** unassigned
**Last updated:** 2026-08-21

## Problem

Feature 9 of the plan's next batch asks for a personal interpretation profile — saved preferences
(tone, length, a free-text note about what the user wants) that shape every interpretation and
follow-up answer, instead of the fixed voice this app has always used. Unlike lens
([SPEC-033](../multi-lens-interpretation/spec.md), *what to focus on*, chosen per-request) or the
conversation thread ([SPEC-034](../interpretation-followup/spec.md), *not persisted*), a profile
is a standing preference — set once, applied to every future request until changed. This app has
no accounts (see [SPEC-001](../project-architecture/spec.md)), so the profile is a single global
setting, not per-user.

## Purpose

Let the user set a tone, a length preference, and a free-text note once; every subsequent
`interpret()` and `answerFollowUp()` call is shaped by it, until changed again.

## Scope

- New table `interpretation_profile` — a singleton row (`id INTEGER PRIMARY KEY CHECK (id = 1)`,
  `tone TEXT NOT NULL DEFAULT 'neutral'`, `length TEXT NOT NULL DEFAULT 'standard'`, `notes
  TEXT`), matching the "one global setting, not a table of many" shape the feature actually
  needs.
- `App\AI\Tone` enum: `Neutral` (default) / `Formal` / `Casual` / `Poetic`.
- `App\AI\ResponseLength` enum: `Standard` (default) / `Brief` / `Detailed`.
- `App\AI\InterpretationProfile` (readonly): `tone: Tone`, `length: ResponseLength`, `notes:
  ?string` (≤1000 characters).
- `App\AI\InterpretationProfileRepository` (interface) / `SqliteInterpretationProfileRepository`:
  `get(): InterpretationProfile` (returns the all-defaults profile if no row has ever been
  saved — matches every other "no explicit setting yet" case in this app resolving to a sensible
  default, never an error), `save(InterpretationProfile): void` (upsert the singleton row).
- `GET /api/interpretation-profile` / `PATCH /api/interpretation-profile` (new endpoints,
  `InterpretationProfileController`).
- `InterpretationProvider::interpret()` and `answerFollowUp()` each gain a third parameter,
  `InterpretationProfile $profile`.
- `GeminiInterpretationProvider`: when the profile is entirely default (`Neutral`/`Standard`/no
  notes), the prompt is byte-identical to its pre-SPEC-035 form — same guarantee
  [SPEC-033](../multi-lens-interpretation/spec.md) already established for `general` lens. A
  non-default profile appends one "Personal preferences" block naming only the aspects that
  differ from default (e.g. just tone, if only tone was changed).
- `MockInterpretationProvider`: does not fabricate tone/length-differentiated content (same
  "disclose, don't fake" stance [SPEC-033](../multi-lens-interpretation/spec.md) already
  established for lens) — states in `uncertainties`/the follow-up answer when a non-default
  profile was in effect and that mock output doesn't vary by it.
- `InterpretationController::create()`/`followUp()` load the current profile once per request
  (`$this->profileRepository->get()`) and pass it to the provider call.
- `apps/web`: `entities/interpretation-profile/{model.ts,api.ts}`; new `/settings` page
  (`InterpretationSettingsPage.vue`) with a form (tone select, length select, notes textarea),
  linked from the main nav.

## Out of scope

- **Per-consultation or per-lens profile overrides.** One global profile, applied everywhere —
  matches the plan's own framing ("personal interpretation profile," singular, not "a profile per
  reading").
- **Multiple named profiles / profile switching.** This app has no accounts to scope multiple
  profiles to; a single standing preference set is what "personal" means here.
- **Applying the profile retroactively to already-fetched, cached interpretations
  (SPEC-033's per-lens client cache).** Changing the profile doesn't invalidate or re-fetch
  anything already shown; it only affects the *next* request made after the change — matches how
  changing the lens selector already doesn't retroactively alter a cached result.
- **Validating `notes` content beyond a length cap.** Free text, same as every other free-text
  field in this app (consultation notes, context fields) — no content moderation beyond the
  existing pattern.

## User behavior

```
GET /api/interpretation-profile
  -> {"tone": "neutral", "length": "standard", "notes": null}   (before ever being set)

PATCH /api/interpretation-profile {"tone": "poetic", "notes": "I appreciate vivid imagery."}
  -> 200 {"tone": "poetic", "length": "standard", "notes": "I appreciate vivid imagery."}

POST /api/interpretations/{id}  (after the above PATCH)
  -> a genuinely more poetic, imagery-rich interpretation (when AI_PROVIDER=gemini)

/settings
  -> tone select, length select, notes textarea, "Save" button
  -> saved settings apply to every interpretation/follow-up request from then on
```

## Functional requirements

- **REQ-PROFILE-001** — `GET /api/interpretation-profile` MUST return the current profile,
  defaulting to `{"tone": "neutral", "length": "standard", "notes": null}` when never set.
- **REQ-PROFILE-002** — `PATCH /api/interpretation-profile` MUST accept any subset of `tone`,
  `length`, `notes`; an invalid `tone`/`length` value MUST return `422`; `notes` over 1000
  characters MUST return `422`; `notes: null` MUST clear it.
- **REQ-PROFILE-003** — With an all-default profile, `GeminiInterpretationProvider`'s prompt
  (for both `interpret()` and `answerFollowUp()`) MUST be byte-identical to its pre-SPEC-035 form.
- **REQ-PROFILE-004** — With a non-default profile, the prompt MUST include exactly one
  "Personal preferences" block naming only the fields that differ from default.
- **REQ-PROFILE-005** — `MockInterpretationProvider` MUST NOT vary its canonical fields by
  profile; it MUST disclose (in `uncertainties` for `interpret()`, in the answer text for
  `answerFollowUp()`) when a non-default profile was in effect.
- **REQ-PROFILE-006** — `InterpretationController` MUST load the current profile once per
  request and pass it to whichever provider method it calls.
- **REQ-PROFILE-007** — `/settings` MUST render a form for tone, length, and notes, pre-filled
  from `GET /api/interpretation-profile`, and MUST persist changes via `PATCH`.

## Non-functional requirements

- **REQ-PROFILE-008** — No component outside `entities/interpretation-profile` may call
  `apiGet`/`apiPatch` directly for this data.

## Data requirements

New table `interpretation_profile`, a singleton row. No change to any existing table.

## API requirements

Two new endpoints: `GET`/`PATCH /api/interpretation-profile`. No change to any existing
endpoint's URL, method, or status codes (`interpret()`/`answerFollowUp()`'s own request/response
shapes are unchanged — the profile is read server-side, not sent by the client per-request).

## Edge cases

- `PATCH` with an empty body (`{}`) → no fields present, nothing changes; matches this app's
  "at least one field" pattern loosely, but since the whole point of a settings endpoint is
  idempotent partial updates, an empty `PATCH` is a harmless no-op here rather than a `422`
  (unlike `PATCH /api/consultations/{id}`, which requires at least one field because an empty
  request there is almost certainly a client bug — a settings `PATCH` with nothing to change is a
  legitimate, if unusual, request).
- Both `tone` and `length` default, but `notes` set → the "Personal preferences" block names only
  the note, not empty tone/length lines.
- A profile set, then cleared back to all-defaults via a second `PATCH` → prompts return to being
  byte-identical to the no-profile-ever-set case (no leftover artifact from having once been
  customized).

## Acceptance criteria

- [x] `GET` returns the default profile before anything is ever saved.
- [x] `PATCH` updates any subset of fields; invalid `tone`/`length` or over-length `notes`
      returns `422`.
- [x] An all-default profile produces a byte-identical Gemini prompt (both `interpret()` and
      `answerFollowUp()`), verified with a fake client.
- [x] A non-default profile adds exactly one preferences block naming only the changed fields.
- [x] `MockInterpretationProvider` discloses a non-default profile without varying its canonical
      output.
- [x] `/settings` loads, edits, and persists the profile correctly.
- [x] `npm run verify` passes end to end.
- [x] Manually verified against the real running API/UI, including a real Gemini call showing a
      genuinely different tone after changing the profile.
