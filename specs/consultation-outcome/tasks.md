# Tasks — Consultation Outcome (SPEC-020)

Each task references the requirement(s) it fulfills. Mark done only once the linked test passes.

| Task ID           | Description                                                                     | Requirement(s)          | Test(s)                            | Status  |
| ------------------ | ------------------------------------------------------------------------------------ | ------------------------ | -------------------------------------- | ------- |
| TASK-OUTCOME-001 | Add `ConsultationOutcome` value object + migration for `consultation_outcomes`        | REQ-OUTCOME-004, 009    | `ConsultationTest`                    | done |
| TASK-OUTCOME-002 | Extend `Consultation`: constructor, `create()`, `reconstitute()`, `withUpdatedOutcome()`, audit all witherers | REQ-OUTCOME-003, 006, 008 | `ConsultationTest`         | done |
| TASK-OUTCOME-003 | Extend `SqliteConsultationRepository`: upsert + hydrate outcome                        | REQ-OUTCOME-007         | `SqliteConsultationRepositoryTest`   | done |
| TASK-OUTCOME-004 | Extend `ConsultationController`: `update()`, `toJson()`                                | REQ-OUTCOME-001..002, 005 | `ConsultationControllerTest`       | done |
| TASK-OUTCOME-005 | Extend `entities/consultation` types                                                   | REQ-OUTCOME-010         | `api.spec.ts`                        | done |
| TASK-OUTCOME-006 | Add outcome display + edit form to `ConsultationPage.vue`                              | acceptance criteria      | `ConsultationPage.spec.ts`          | done |
| TASK-OUTCOME-007 | Manually verify against the real running API/UI (apply migration to dev DB first, confirm an existing consultation still loads), then verify + commit | acceptance criteria | `npm run verify`, manual | done |
