# Plan — Follow-up Consultations (SPEC-021)

**Depends on spec status:** `approved`

## Technical approach

- Migration `2026_08_15_000003_add_consultation_follow_up_link.php`:
  `ALTER TABLE consultations ADD COLUMN follow_up_to_consultation_id TEXT REFERENCES
  consultations(id) ON DELETE SET NULL`. SQLite enforces this FK only with `PRAGMA foreign_keys
  = ON`, already set by every test's connection setup and by `Database::connect()`.
- `apps/api/src/Readings/ConsultationSummary.php` — new, minimal readonly value object:
  `id: string`, `question: string`. No behavior, purely a display shape.
- `Consultation`:
  - Constructor gains `public ?string $followUpToConsultationId = null` (last positional slot,
    after `outcome`).
  - `create()` gains `?string $followUpToConsultationId = null`; `reconstitute()` gains it too.
  - New `withFollowUpTo(?string $followUpToConsultationId): self` — the only validation the
    domain layer can do without a repository is self-reference (`$followUpToConsultationId ===
    $this->id`); existence-of-target is a repository-dependent check, so it lives in the
    controller, not here.
  - `withAddedNote`/`withAddedTag`/`withUpdatedContext`/`withUpdatedOutcome` each gain
    `$this->followUpToConsultationId` as an explicit trailing argument — continuing the
    SPEC-019/020 checklist discipline for every wither, every time the constructor grows.
- `ConsultationRepository` interface gains:
  - `findSummaryById(string $id): ?ConsultationSummary` — `SELECT id, question FROM
    consultations WHERE id = :id`, used both to validate `followUpToConsultationId` exists
    (controller, on create/update) and to resolve `followUpTo` for the response.
  - `findFollowUpSummaries(string $consultationId): list<ConsultationSummary>` — `SELECT id,
    question FROM consultations WHERE follow_up_to_consultation_id = :id ORDER BY created_at
    ASC`.
- `SqliteConsultationRepository`:
  - `upsertConsultation()`/`hydrate()` gain the one new column, same nullable-string pattern
    already used for the five SPEC-019 context fields.
  - Implements the two new interface methods directly (simple, single-table SELECTs — no
    hydration through `Consultation::reconstitute()` needed, since `ConsultationSummary` is a
    separate, simpler shape).
- `ConsultationController`:
  - `create()`: if `followUpToConsultationId` present and non-null, resolve it via
    `$this->repository->findSummaryById()` first — `null` result → `422` ("references a
    consultation that doesn't exist") — before calling `Consultation::create()` (which only
    checks self-reference, moot at creation time since the new consultation's `id` doesn't exist
    yet when the check would run — self-reference at creation is structurally impossible, so
    `create()` doesn't need the check at all; it's relevant only for `update()`, where the
    consultation already has an `id` to compare against).
  - `update()`: extends the existing `resolveContextField()`-style key handling to
    `followUpToConsultationId`, added to the "at least one" check; existence validated the same
    way as `create()` when the resolved value is a non-null string.
  - `toJson()`: resolves `followUpTo` (one `findSummaryById()` call, only if
    `$consultation->followUpToConsultationId !== null`) and `followUps` (one
    `findFollowUpSummaries()` call, always) into the response.

## Architecture decisions

- **Existence validation lives in the controller, not `Consultation::create()`.** The domain
  aggregate has no repository access (matches this codebase's existing layering — `Consultation`
  never touches PDO); only the controller can check "does this ID exist," so that's where it
  goes, same as how `ConsultationController::update()` already resolves `findById()` before
  doing anything else.
- **`ConsultationSummary`, not a full `Consultation`, for resolved links.** Loading two full
  `Consultation` objects (with their own hexagram reconstruction, notes, tags, context, outcome,
  and — recursively — their own follow-up links) just to show a question string in a link would
  be wasteful and risks infinite recursion if two consultations' summaries tried to resolve each
  other's full follow-up chains. A flat `{id, question}` breaks that recursion by construction.
- **Self-reference check only, no cross-consultation cycle detection.** Matches the spec's own
  "Out of scope" reasoning — the UI flow never produces a cycle, and guarding against a
  hand-crafted one via repeated `PATCH` calls isn't asked for.

## Affected areas

- `apps/api/database/migrations/2026_08_15_000003_add_consultation_follow_up_link.php` (new)
- `apps/api/src/Readings/ConsultationSummary.php` (new)
- `apps/api/src/Readings/Consultation.php`
- `apps/api/src/Readings/ConsultationRepository.php`
- `apps/api/src/Readings/SqliteConsultationRepository.php`
- `apps/api/src/Readings/ConsultationController.php`
- `apps/api/tests/Readings/ConsultationTest.php`
- `apps/api/tests/Readings/SqliteConsultationRepositoryTest.php`
- `apps/api/tests/Readings/ConsultationControllerTest.php`
- `apps/web/src/entities/consultation/model.ts`
- `apps/web/src/entities/consultation/api.spec.ts`
- `apps/web/src/pages/consultations/NewConsultationPage.vue`
- `apps/web/src/pages/consultations/NewConsultationPage.spec.ts`
- `apps/web/src/pages/consultations/ConsultationPage.vue`
- `apps/web/src/pages/consultations/ConsultationPage.spec.ts`
- `apps/web/src/pages/consultations/ConsultationHistoryPage.spec.ts` (fixture update only)

## Data / schema changes

One new nullable `TEXT` column, `follow_up_to_consultation_id`, self-referential FK. Additive.

## Risks / open questions

- None currently open.
