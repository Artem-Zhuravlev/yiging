# Plan — Repeated Pattern Detection (SPEC-023)

**Depends on spec status:** `approved`

## Technical approach

- `ConsultationRepository` interface gains three methods (see spec's Scope section).
  `SqliteConsultationRepository` implements them as three simple, single-table `SELECT id,
  question FROM consultations WHERE <column> = :value AND id != :excludeId ORDER BY created_at
  DESC` queries — same shape as the existing `findFollowUpSummaries()`, reusing
  `ConsultationSummary` as the return element.
- `findByChangingLinePositions()` takes `list<int> $positions`, encodes them with `json_encode()`
  (same call already used by `upsertConsultation()`) and compares the encoded string directly
  against the `changing_line_positions` column — no decode-and-set-compare needed, per
  REQ-REPEAT-007's ordering guarantee.
- `ConsultationController::show()` (only) builds the `repeats` object after loading the
  consultation, calling the three new repository methods (skipping the changing-lines query
  entirely when `$consultation->changingLinePositions() === []`), and merges it into the existing
  `toJson()` output. `toJson()` itself stays unchanged (used by `create()`/`index()`/`update()`,
  none of which should gain `repeats` per REQ-REPEAT-005) — `show()` calls a new
  `toJsonWithRepeats()` that wraps `toJson()`'s array and adds the one extra key.
- Frontend: `entities/consultation/model.ts` gains `ConsultationRepeats` and `ConsultationDetail
  extends Consultation { repeats: ConsultationRepeats }`. `fetchConsultation()`'s return type
  changes from `Consultation` to `ConsultationDetail`.
- `ConsultationPage.vue`: `repeats` is a separate local `ref<ConsultationRepeats | null>`, set
  once in `onMounted()` from the fetched detail's `repeats` field — kept independent of
  `state.consultation` (which stays typed `Consultation`, matching what `updateConsultation()`
  already returns) so that adding a note/tag/context/outcome via `PATCH` never has to reconcile a
  `repeats` field the update response doesn't carry. This mirrors how `interpretationState` is
  already kept independent of `state` in this file for the same reason (a PATCH must never
  disturb unrelated already-loaded data).

## Architecture decisions

- **`repeats` lives only on `show()`, via a wrapper method, not a parameter threaded through the
  shared `toJson()`.** Keeps `create()`/`index()`/`update()` mechanically incapable of
  accidentally picking it up later (no boolean flag to forget to pass `false`), and keeps the
  O(n²)-risk computation physically absent from the list code path rather than merely
  "not currently called."
- **Exact JSON-string equality for changing lines, not per-row PHP decode+compare.** Verified
  `Consultation::changingLinePositions()` always returns positions in ascending order (derived
  from the hexagram's own position-ordered `lines` array), so the stored JSON is already
  canonical — this turns what would otherwise be an O(n) PHP-side comparison per candidate row
  into a plain indexed-comparable SQL equality check.
- **`repeats` kept off `Consultation`/state merging in the frontend, as a sibling ref instead.**
  Avoids widening the type every PATCH response must satisfy; matches this file's own existing
  `interpretationState`-is-independent-of-`state` precedent.

## Affected areas

- `apps/api/src/Readings/ConsultationRepository.php`
- `apps/api/src/Readings/SqliteConsultationRepository.php`
- `apps/api/src/Readings/ConsultationController.php`
- `apps/api/tests/Readings/SqliteConsultationRepositoryTest.php`
- `apps/api/tests/Readings/ConsultationControllerTest.php`
- `apps/web/src/entities/consultation/model.ts`
- `apps/web/src/entities/consultation/api.ts`
- `apps/web/src/entities/consultation/api.spec.ts`
- `apps/web/src/pages/consultations/ConsultationPage.vue`
- `apps/web/src/pages/consultations/ConsultationPage.spec.ts`

## Data / schema changes

None.

## Risks / open questions

- None currently open.
