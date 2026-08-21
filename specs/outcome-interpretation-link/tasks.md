# Tasks — Outcome-Interpretation Link (SPEC-036)

Each task references the requirement(s) it fulfills. Mark done only once the linked test passes.

| Task ID           | Description                                                              | Requirement(s)              | Test(s)                             | Status |
| -------------------- | -------------------------------------------------------------------------- | -------------------------------- | ---------------------------------------- | ------ |
| TASK-OUTLINK-001 | Add migration + extend `ConsultationOutcome`/`Consultation::withUpdatedOutcome()` | REQ-OUTLINK-001..004, 008 | `ConsultationTest`                | done   |
| TASK-OUTLINK-002 | Extend `SqliteConsultationRepository` column handling                       | REQ-OUTLINK-005                  | `SqliteConsultationRepositoryTest`      | done   |
| TASK-OUTLINK-003 | Extend `ConsultationController` outcome-field handling + `toJson()`         | REQ-OUTLINK-001, 002, 003, 005   | `ConsultationControllerTest`            | done   |
| TASK-OUTLINK-004 | Extend `entities/consultation` types                                        | (supports 006/007)               | (typechecked via page test)             | done   |
| TASK-OUTLINK-005 | Add "Link to Outcome"/"Unlink" + linked-interpretation display to `ConsultationPage.vue` | REQ-OUTLINK-006, 007 | `ConsultationPage.spec.ts`         | done   |
| TASK-OUTLINK-006 | Run `npm run verify`, manually verify (incl. real Gemini interpretation linked and surviving reload), update README + specs/README | acceptance criteria | `npm run verify`, manual | done |
