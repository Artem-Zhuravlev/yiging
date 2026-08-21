# Plan — Outcome-Interpretation Link (SPEC-036)

**Depends on spec status:** `approved`

## Technical approach

- Migration `2026_08_21_000005_add_outcome_interpretation_link.php`: `ALTER TABLE
  consultation_outcomes ADD COLUMN interpretation_lens TEXT; ALTER TABLE consultation_outcomes ADD
  COLUMN interpretation_summary TEXT;`.
- `ConsultationOutcome`: constructor gains `?string $interpretationLens = null, ?string
  $interpretationSummary = null`; validates `interpretationSummary` with the existing
  `MAX_FIELD_LENGTH` check (same private `validate()` helper every other field already uses) and
  `interpretationLens` against a local `private const VALID_LENSES = ['general', 'psychological',
  'practical', 'symbolic']` (a plain string list, not `App\AI\InterpretationLens` — REQ-OUTLINK-008).
- `Consultation::withUpdatedOutcome()` gains the same two parameters, passed straight into the new
  `ConsultationOutcome`.
- `ConsultationController::update()`: `$outcomeKeys` gains `'interpretationLens'`,
  `'interpretationSummary'`; both resolved via the existing `resolveContextField()` helper
  (already generic over any string|null field) alongside the other three outcome fields, in the
  same `withUpdatedOutcome()` call.
- `SqliteConsultationRepository`: `replaceOutcome()`/`loadOutcome()` gain the two columns, same
  nullable-string pattern already used for every other outcome field.
- `ConsultationController::toJson()`'s `outcome` block gains the two new keys.
- `apps/web/src/entities/consultation/model.ts`: `ConsultationOutcome` gains
  `interpretationLens: InterpretationLens | null`, `interpretationSummary: string | null`;
  `ConsultationPatch` gains the same two optional keys.
- `ConsultationPage.vue`: `outcomeForm` gains `interpretationLens`/`interpretationSummary` (both
  pre-filled from `consultation.outcome` in `outcomeFormFrom()`, matching every other outcome
  field's existing pattern exactly); a "Link to Outcome" button (visible when
  `interpretationState.status === 'loaded'`) sets
  `outcomeForm.value.interpretationLens = selectedLens.value` and
  `.interpretationSummary = interpretationState.value.interpretation.summary`; an "Unlink" button
  clears both; the Outcome section shows the currently-saved link (from `consultation.outcome`,
  not the unsaved form) when present.

## Architecture decisions

- **`interpretationLens` stored as a plain validated string in `Readings`, never
  `App\AI\InterpretationLens`.** Keeps `App\Readings` free of any dependency on `App\AI` — this
  codebase has never had one domain module import another's types (Hexagrams/Trigrams/AI/Journal
  are all self-contained), and this spec doesn't need to be the first.
- **Linking is a form-state action, not a network call of its own.** "Link to Outcome" only
  populates already-existing local component state; the existing "Save Outcome" button (and its
  existing `PATCH` call) is what actually persists it — no new endpoint, no new request type,
  reusing SPEC-019/020's whole update mechanism unchanged.
- **Only `summary`, not the full `Interpretation`, is captured.** Matches the spec's own
  reasoning: a short, genuinely comparable snapshot, not a wholesale reversal of "AI output isn't
  persisted."

## Affected areas

- `apps/api/database/migrations/2026_08_21_000005_add_outcome_interpretation_link.php` (new)
- `apps/api/src/Readings/ConsultationOutcome.php`
- `apps/api/src/Readings/Consultation.php`
- `apps/api/src/Readings/ConsultationController.php`
- `apps/api/src/Readings/SqliteConsultationRepository.php`
- `apps/api/tests/Readings/ConsultationTest.php`
- `apps/api/tests/Readings/SqliteConsultationRepositoryTest.php`
- `apps/api/tests/Readings/ConsultationControllerTest.php`
- `apps/web/src/entities/consultation/model.ts`
- `apps/web/src/pages/consultations/ConsultationPage.vue`
- `apps/web/src/pages/consultations/ConsultationPage.spec.ts`

## Data / schema changes

`consultation_outcomes` gains two nullable `TEXT` columns. Additive, backward-compatible.

## Risks / open questions

- None currently open.
