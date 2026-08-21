# Plan — Interpretation Profile (SPEC-035)

**Depends on spec status:** `approved`

## Technical approach

- Migration `2026_08_21_000004_create_interpretation_profile.php`: `CREATE TABLE
  interpretation_profile (id INTEGER PRIMARY KEY CHECK (id = 1), tone TEXT NOT NULL DEFAULT
  'neutral', length TEXT NOT NULL DEFAULT 'standard', notes TEXT)`.
- `apps/api/src/AI/Tone.php` (string-backed enum), `apps/api/src/AI/ResponseLength.php`
  (string-backed enum).
- `apps/api/src/AI/InterpretationProfile.php` (readonly): validates `notes` ≤1000 chars, same
  pattern as every other free-text field's constructor validation in this app.
- `apps/api/src/AI/InterpretationProfileRepository.php` (interface) /
  `SqliteInterpretationProfileRepository.php`: `get()` (`SELECT ... WHERE id = 1`; no row →
  `InterpretationProfile::default()`), `save()` (`INSERT ... ON CONFLICT(id) DO UPDATE SET ...`,
  same singleton-upsert shape).
- `InterpretationProvider::interpret()`/`answerFollowUp()` gain a third parameter,
  `InterpretationProfile $profile`.
- `GeminiInterpretationProvider`: a `profileInstructionLines(InterpretationProfile $profile):
  list<string>` helper — empty list when the profile is all-default, otherwise one "Personal
  preferences:" line plus one line per non-default field, appended to the prompt the same way
  `lensInstruction()` already appends its one sentence (REQ-PROFILE-003/004).
- `MockInterpretationProvider`: same disclosure pattern already used for lens, extended with a
  profile disclosure line when non-default.
- `apps/api/src/AI/InterpretationProfileController.php` (new): `show()` (`GET`), `update()`
  (`PATCH`) — same validate-then-persist shape as every other settings-style endpoint in this
  app.
- `InterpretationController`: constructor gains `$this->profileRepository`; `create()`/
  `followUp()` call `$this->profileRepository->get()` once and pass the result into
  `interpret()`/`answerFollowUp()`.
- `config/routes.php` gains `GET`/`PATCH /api/interpretation-profile`.
- `apps/web/src/entities/interpretation-profile/model.ts` — `Tone`/`ResponseLength` unions,
  `InterpretationProfile { tone, length, notes }`.
- `apps/web/src/entities/interpretation-profile/api.ts` — `fetchInterpretationProfile()`,
  `updateInterpretationProfile(patch)`.
- `apps/web/src/pages/settings/InterpretationSettingsPage.vue` — loading/error/loaded states
  (this app's standard `State` union), a form with a tone `<select>`, a length `<select>`, a
  notes `<textarea>`, and a "Save" button (`FormState`-style submitting/error handling, matching
  every other form in this app).
- `router/index.ts` gains `/settings`; `App.vue`'s nav gains a "Settings" link.

## Architecture decisions

- **A singleton row, not a key-value settings table.** This app has exactly one setting group to
  store (three fields); a generic key-value table would be premature generality for something
  with a fixed, known shape.
- **Profile is loaded server-side per request, not sent by the client.** Unlike lens (a
  per-request choice the client actively picks) and conversation history (client-held state since
  it's not persisted), the profile is a standing server-side preference — loading it inside
  `InterpretationController` keeps the request/response shapes of `POST
  /api/interpretations/{id}` and its `/followup` sibling completely unchanged, so SPEC-033/034's
  existing contracts don't need touching.
- **`profileInstructionLines()` returns an empty list for an all-default profile, not a list with
  empty-string placeholders.** Keeps the "byte-identical when nothing is customized" guarantee
  simple to verify — an empty list appends nothing, full stop, rather than needing to check that
  appended-but-empty lines don't change the joined string.

## Affected areas

- `apps/api/database/migrations/2026_08_21_000004_create_interpretation_profile.php` (new)
- `apps/api/src/AI/Tone.php` (new)
- `apps/api/src/AI/ResponseLength.php` (new)
- `apps/api/src/AI/InterpretationProfile.php` (new)
- `apps/api/src/AI/InterpretationProfileRepository.php` (new)
- `apps/api/src/AI/SqliteInterpretationProfileRepository.php` (new)
- `apps/api/src/AI/InterpretationProfileController.php` (new)
- `apps/api/src/AI/InterpretationProvider.php`
- `apps/api/src/AI/GeminiInterpretationProvider.php`
- `apps/api/src/AI/MockInterpretationProvider.php`
- `apps/api/src/AI/InterpretationController.php`
- `apps/api/config/routes.php`
- `apps/api/tests/AI/*`
- `apps/web/src/entities/interpretation-profile/model.ts` (new)
- `apps/web/src/entities/interpretation-profile/api.ts` (new)
- `apps/web/src/pages/settings/InterpretationSettingsPage.vue` (new)
- `apps/web/src/pages/settings/InterpretationSettingsPage.spec.ts` (new)
- `apps/web/src/router/index.ts`
- `apps/web/src/App.vue`
- `apps/web/src/App.spec.ts`

## Data / schema changes

New table `interpretation_profile`, a singleton row, no relation to any existing table.

## Risks / open questions

- None currently open.
