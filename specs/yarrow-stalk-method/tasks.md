# Tasks — Yarrow-Stalk Casting Method (SPEC-055)

## API

- [x] **TASK-YS-001** — `App\Casting\RandomSource` interface + `SystemRandomSource`
      (`random_int`). → REQ-YS-020
- [x] **TASK-YS-002** — `App\Casting\YarrowStalkMethod` — 6 lines, per-line `intBetween(1,16)`
      → 1/5/7/3-in-16 mapping; docblock stating the Zhu Xi probabilities vs three-coin.
      → REQ-YS-001, 002
- [x] **TASK-YS-003** — `CastingMethodName::Yarrow = 'yarrow'`; `ConsultationController`
      `resolveDivinationMethod()` arm. → REQ-YS-003
- [x] **TASK-YS-004** — `tests/Casting/Support/FakeRandomSource` + `YarrowStalkMethodTest`
      (four buckets + boundaries, position order, exhausted-source raise). → REQ-YS-020
- [x] **TASK-YS-005** — `ConsultationControllerTest`: `POST {"method":"yarrow"}` → 201 +
      `method` echoed on create and on `GET /{id}`. → REQ-YS-003

## Frontend

- [x] **TASK-YS-006** — `entities/consultation/model.ts`: `SelectableCastingMethod` +
      `NewConsultationRequest` gain `'yarrow'`. → REQ-YS-004
- [x] **TASK-YS-007** — `NewConsultationPage.vue`: Yarrow stalk radio + selected-only hint;
      submit sends `method: 'yarrow'`. → REQ-YS-004
- [x] **TASK-YS-008** — i18n `newConsultation.yarrow` + `newConsultation.yarrowHint` (en + uk).
      → REQ-YS-022
- [x] **TASK-YS-009** — `NewConsultationPage.spec.ts`: a yarrow submit test. → REQ-YS-021

## Close-out

- [x] **TASK-YS-010** — `composer test`/`stan`/`lint` + `npm run verify` green; browser +
      curl pass; fill `plan.md` note; flip `spec.md` → `implemented`; add SPEC-055 to both
      README tables. → REQ-YS-021
