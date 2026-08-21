# Tasks — Repeated Pattern Detection (SPEC-023)

Each task references the requirement(s) it fulfills. Mark done only once the linked test passes.

| Task ID          | Description                                                                 | Requirement(s)                  | Test(s)                             | Status |
| ------------------ | ------------------------------------------------------------------------------ | ---------------------------------- | ---------------------------------------- | ------ |
| TASK-REPEAT-001 | Add three repository methods to interface + SQLite implementation              | REQ-REPEAT-002, 003, 004, 007      | `SqliteConsultationRepositoryTest`      | done   |
| TASK-REPEAT-002 | Add `toJsonWithRepeats()` + wire into `show()` only                            | REQ-REPEAT-001, 004, 005           | `ConsultationControllerTest`            | done   |
| TASK-REPEAT-003 | Add `ConsultationRepeats`/`ConsultationDetail` types, update `fetchConsultation()` | REQ-REPEAT-008                  | `api.spec.ts`                           | done   |
| TASK-REPEAT-004 | Render the three repeats sections on `ConsultationPage.vue`                    | REQ-REPEAT-006                     | `ConsultationPage.spec.ts`              | done   |
| TASK-REPEAT-005 | Run `npm run verify`, manually verify against real running API/UI, update README + specs/README | acceptance criteria | `npm run verify`, manual | done   |
