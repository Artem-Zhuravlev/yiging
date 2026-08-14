# Tasks — Rich Consultation Context (SPEC-019)

Each task references the requirement(s) it fulfills. Mark done only once the linked test passes.

| Task ID       | Description                                                                | Requirement(s)          | Test(s)                          | Status  |
| -------------- | ----------------------------------------------------------------------------- | ------------------------ | ------------------------------------ | ------- |
| TASK-CTX-001 | Add migration for five new nullable columns                                   | REQ-CTX-008             | (applied via test setUp)            | done |
| TASK-CTX-002 | Extend `Consultation`: `create()`, `reconstitute()`, `withUpdatedContext()`, validation | REQ-CTX-001..002, 007 | `ConsultationTest`                | done |
| TASK-CTX-003 | Extend `SqliteConsultationRepository`: upsert + hydrate                       | REQ-CTX-007             | `SqliteConsultationRepositoryTest` | done |
| TASK-CTX-004 | Extend `ConsultationController`: `create()`, `update()`, `toJson()`           | REQ-CTX-003..006        | `ConsultationControllerTest`      | done |
| TASK-CTX-005 | Extend `entities/consultation` types                                          | REQ-CTX-009             | `api.spec.ts`                      | done |
| TASK-CTX-006 | Add collapsed optional context inputs to `NewConsultationPage.vue`            | acceptance criteria      | `NewConsultationPage.spec.ts`     | done |
| TASK-CTX-007 | Add context display + edit form to `ConsultationPage.vue`                     | acceptance criteria      | `ConsultationPage.spec.ts`        | done |
| TASK-CTX-008 | Manually verify against the real running API/UI (incl. an existing pre-migration consultation), then verify + commit | acceptance criteria | `npm run verify`, manual | done |
