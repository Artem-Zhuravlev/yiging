# Tasks — Follow-up Consultations (SPEC-021)

Each task references the requirement(s) it fulfills. Mark done only once the linked test passes.

| Task ID             | Description                                                                   | Requirement(s)          | Test(s)                          | Status  |
| --------------------- | ---------------------------------------------------------------------------------- | ------------------------ | ------------------------------------ | ------- |
| TASK-FOLLOWUP-001 | Add migration + `ConsultationSummary` value object                                 | REQ-FOLLOWUP-007        | (applied via test setUp)            | done |
| TASK-FOLLOWUP-002 | Extend `Consultation`: constructor, `create()`, `reconstitute()`, `withFollowUpTo()`, audit all witherers | REQ-FOLLOWUP-002, 006 | `ConsultationTest`             | done |
| TASK-FOLLOWUP-003 | Extend `ConsultationRepository`/`SqliteConsultationRepository`: column + summary queries | REQ-FOLLOWUP-007        | `SqliteConsultationRepositoryTest`  | done |
| TASK-FOLLOWUP-004 | Extend `ConsultationController`: `create()`, `update()`, `toJson()` existence validation + resolution | REQ-FOLLOWUP-001, 003..005 | `ConsultationControllerTest` | done |
| TASK-FOLLOWUP-005 | Extend `entities/consultation` types                                               | REQ-FOLLOWUP-010        | `api.spec.ts`                      | done |
| TASK-FOLLOWUP-006 | Add `?followUpTo=` handling to `NewConsultationPage.vue`                           | REQ-FOLLOWUP-008        | `NewConsultationPage.spec.ts`     | done |
| TASK-FOLLOWUP-007 | Add follow-up-to/follow-ups display + "Create Follow-up" link to `ConsultationPage.vue` | acceptance criteria | `ConsultationPage.spec.ts`      | done |
| TASK-FOLLOWUP-008 | Manually verify against the real running API/UI (apply migration to dev DB first), then verify + commit | acceptance criteria | `npm run verify`, manual | done |
