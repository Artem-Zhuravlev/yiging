# Plan — Consultation Outcome (SPEC-020)

**Depends on spec status:** `approved`

## Technical approach

- `apps/api/src/Readings/ConsultationOutcome.php` — new readonly value object, mirrors
  `ConsultationNote`'s style: `whatActuallyHappened`, `outcome`, `reflection` (all `?string`),
  `recordedAt` (`\DateTimeImmutable`). Constructor validates each non-null field against a
  `MAX_FIELD_LENGTH = 5000` constant (same limit as notes/context fields).
- Migration `2026_08_15_000002_create_consultation_outcomes.php`: `CREATE TABLE
  consultation_outcomes (consultation_id TEXT PRIMARY KEY REFERENCES consultations(id) ON DELETE
  CASCADE, what_actually_happened TEXT, outcome TEXT, reflection TEXT, recorded_at TEXT NOT
  NULL)`. A genuinely new table — no `ALTER TABLE consultations`.
- `Consultation`:
  - New constructor param `public ?ConsultationOutcome $outcome = null` (after the SPEC-019
    context fields).
  - `create()`/`reconstitute()` gain `?ConsultationOutcome $outcome = null` params, passed
    straight through (repository always has a value — `null` or a hydrated object).
  - `withAddedNote()`/`withAddedTag()`/`withUpdatedContext()` each gain `$this->outcome` as an
    explicit trailing argument in their `new self(...)` calls — this is the exact bug class
    SPEC-019 found (a wither silently dropping a field the constructor gained after that wither
    was written), so every existing wither gets audited and fixed in the same change, not just
    the ones that happen to be touched by new tests.
  - New `withUpdatedOutcome(?string $whatActuallyHappened, ?string $outcome, ?string $reflection,
    \DateTimeImmutable $recordedAt): self` — validates the three fields (reusing
    `ConsultationOutcome`'s own constructor validation by just constructing one), rebuilds via
    `new self(...)` with every other field preserved and `outcome` replaced.
- `SqliteConsultationRepository`:
  - `save()` gains a `replaceOutcome()` step (named to match `replaceNotes()`/`replaceTags()`'s
    existing naming, even though this is an upsert not a delete-and-reinsert, since it's called
    from the same place in the same transaction): `INSERT INTO consultation_outcomes (...) VALUES
    (...) ON CONFLICT(consultation_id) DO UPDATE SET ...` — only executed when
    `$consultation->outcome !== null` (an untouched outcome writes nothing, leaving no row,
    consistent with `outcome: null` in the API meaning "never recorded" not "recorded as empty").
  - `hydrate()` gains a `loadOutcome(string $consultationId): ?ConsultationOutcome` query
    (`SELECT * FROM consultation_outcomes WHERE consultation_id = :id`) — `null` if no row
    (covers both "never recorded" and "pre-migration consultation" identically, since both cases
    are simply "no row").
- `ConsultationController`:
  - `update()` gains the same `array_key_exists()`-based resolution SPEC-019 built for the five
    context keys, applied to `whatActuallyHappened`/`outcome`/`reflection`, reading fallback
    values from `$consultation->outcome?->whatActuallyHappened` etc. (null-safe operator, since
    `$consultation->outcome` may itself be `null` before the first PATCH touches it).
  - Only calls `withUpdatedOutcome()` if at least one of the three keys was present in the body
    (mirrors the existing `$touchesAnyContextField` pattern — extend it to
    `$touchesAnyOutcomeField`, and extend the "at least one" `422` check to include it).
  - `toJson()` serializes `$consultation->outcome` as `{whatActuallyHappened, outcome,
    reflection, recordedAt}` or `null`.
- Frontend:
  - `entities/consultation/model.ts`: new `ConsultationOutcome` interface (`whatActuallyHappened:
    string | null`, `outcome: string | null`, `reflection: string | null`, `recordedAt: string`);
    `Consultation` gains `outcome: ConsultationOutcome | null`; `ConsultationPatch` gains the
    three fields as `Partial<{whatActuallyHappened, outcome, reflection}>`-shaped optional
    `string | null` keys (same shape SPEC-019 used for context fields, not nested).
  - `ConsultationPage.vue`: an "Outcome" section mirroring SPEC-019's "Context" section exactly
    (own `contextForm`-equivalent `ref`, own `FormState`, pre-filled from
    `state.consultation.outcome` on load, submit sends all three current values with blank →
    `null`, re-synced from the server response after save) — same structure, different fields.

## Architecture decisions

- **A genuinely separate table, not more `consultations` columns.** Explicitly what the feature
  text asks for ("a separate historical record") — also sets up better for later features that
  reference "outcome status" directly (plan features 32 Follow-up Consultations, 33 Timeline, 34
  Repeated Hexagrams all mention outcomes as something to query/aggregate, which a linked entity
  supports more naturally than more flat columns on an already-wide `consultations` row).
- **Reuses the existing `PATCH /api/consultations/{id}` endpoint, not a new one.** The
  "separate record" requirement is about the data model; a new endpoint isn't needed to satisfy
  it, and adding one would fragment "update this consultation" across two endpoints for no
  behavioral gain.
- **Single record, not a list.** "Record... the outcome" (singular) in the feature text, and
  `consultation_id` as the primary key enforces this at the schema level rather than relying on
  application logic alone.
- **Every existing wither method gets explicitly audited for the new field**, not just the ones
  a new test happens to exercise — SPEC-019's bug was found by luck (a test that happened to
  combine note-adding with a context field); this spec treats "does this wither preserve every
  field" as a checklist item for every wither, every time the constructor grows.

## Affected areas

- `apps/api/database/migrations/2026_08_15_000002_create_consultation_outcomes.php` (new)
- `apps/api/src/Readings/ConsultationOutcome.php` (new)
- `apps/api/src/Readings/Consultation.php`
- `apps/api/src/Readings/SqliteConsultationRepository.php`
- `apps/api/src/Readings/ConsultationController.php`
- `apps/api/tests/Readings/ConsultationTest.php`
- `apps/api/tests/Readings/SqliteConsultationRepositoryTest.php`
- `apps/api/tests/Readings/ConsultationControllerTest.php`
- `apps/web/src/entities/consultation/model.ts`
- `apps/web/src/entities/consultation/api.spec.ts`
- `apps/web/src/pages/consultations/ConsultationPage.vue`
- `apps/web/src/pages/consultations/ConsultationPage.spec.ts`
- `apps/web/src/pages/consultations/ConsultationHistoryPage.spec.ts` (fixture update only)
- `apps/web/src/pages/consultations/NewConsultationPage.spec.ts` (fixture update only)

## Data / schema changes

New table `consultation_outcomes` — see "Data requirements" in spec.md. Additive migration
(new table only), no changes to `consultations`.

## Risks / open questions

- None currently open — the withAddedNote/withAddedTag/withUpdatedContext preservation bug class
  is understood from SPEC-019 and explicitly checklisted here rather than left to be
  rediscovered.
