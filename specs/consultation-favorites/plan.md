# Plan — Consultation Favorites (SPEC-025)

**Depends on spec status:** `approved`

## Technical approach

- Migration `2026_08_21_000001_add_consultation_favorite_flag.php`: `ALTER TABLE consultations
  ADD COLUMN is_favorite INTEGER NOT NULL DEFAULT 0`.
- `Consultation`: constructor gains `public bool $favorite = false` (last positional slot, after
  `followUpToConsultationId`); `create()`/`reconstitute()` gain it; new `withFavorite(bool
  $favorite): self`; every existing wither threads `$this->favorite` through explicitly —
  continuing the SPEC-019/020/021 checklist.
- `SqliteConsultationRepository`: `upsertConsultation()`/`hydrate()` gain the column, `(bool)
  $row['is_favorite']` / `$consultation->favorite ? 1 : 0`.
- `ConsultationController::update()`: extends the existing key-touching checks with `favorite`
  (present + not boolean → `422`; present + boolean → `withFavorite()`; absent → unchanged).
  `toJson()` gains `'favorite' => $consultation->favorite`.
- Frontend: `entities/consultation/model.ts`'s `Consultation` gains `favorite: boolean`;
  `ConsultationPatch` gains `favorite?: boolean`.
- `ConsultationPage.vue`: a button calling `updateConsultation(id, { favorite: !current })`,
  label/state driven by `state.consultation.favorite`.
- `ConsultationHistoryPage.vue`: a `favoritesOnly = ref(false)` toggle; `filteredConsultations`
  (already filtering by `selectedTags`, SPEC-022) gains a second `.filter(c => !favoritesOnly ||
  c.favorite)` stage before grouping.

## Architecture decisions

- **`favorite` is a plain boolean with no null/clear semantics**, unlike the SPEC-019 optional
  text fields — there's no meaningful "unset" state distinct from `false`, so `PATCH` simply
  requires a boolean when the key is present and rejects anything else, rather than reusing
  `resolveContextField()`'s present-string/present-null/absent three-way logic.
- **`POST` silently ignores a `favorite` key if sent**, rather than validating and rejecting it.
  Consistent with how this API already ignores unrecognized keys elsewhere in create bodies
  (`Consultation::create()` never accepts it as a parameter, so it's structurally impossible for
  it to reach the domain layer either way) — no explicit rejection code needed.
- **Favorites filtering composes with the tag filter as an additional `.filter()` stage**, not a
  separate branch — matches how the spec describes the two as combinable (AND), and keeps
  `ConsultationHistoryPage`'s existing SPEC-022 computed-chain shape (filter, then group) intact.

## Affected areas

- `apps/api/database/migrations/2026_08_21_000001_add_consultation_favorite_flag.php` (new)
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
- `apps/web/src/pages/consultations/ConsultationHistoryPage.vue`
- `apps/web/src/pages/consultations/ConsultationHistoryPage.spec.ts`
- `apps/web/src/pages/consultations/NewConsultationPage.spec.ts` (fixture update only)

## Data / schema changes

One new `NOT NULL INTEGER` column, `is_favorite`, default `0`. Additive.

## Risks / open questions

- None currently open.
