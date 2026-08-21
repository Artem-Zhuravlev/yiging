# Tasks — Consultation Favorites (SPEC-025)

Each task references the requirement(s) it fulfills. Mark done only once the linked test passes.

| Task ID            | Description                                                                  | Requirement(s)                | Test(s)                             | Status |
| --------------------- | ------------------------------------------------------------------------------- | --------------------------------- | ---------------------------------------- | ------ |
| TASK-FAVORITE-001 | Add migration + extend `Consultation` (constructor, witherers, `withFavorite()`) | REQ-FAVORITE-004, 005, 008        | `ConsultationTest`                      | done   |
| TASK-FAVORITE-002 | Extend `SqliteConsultationRepository` column handling                          | REQ-FAVORITE-008                  | `SqliteConsultationRepositoryTest`      | done   |
| TASK-FAVORITE-003 | Extend `ConsultationController::update()`/`toJson()`                           | REQ-FAVORITE-001, 002, 003        | `ConsultationControllerTest`            | done   |
| TASK-FAVORITE-004 | Extend `entities/consultation` types                                           | REQ-FAVORITE-010                  | `api.spec.ts`                           | done   |
| TASK-FAVORITE-005 | Add favorite toggle button to `ConsultationPage.vue`                           | REQ-FAVORITE-006                  | `ConsultationPage.spec.ts`              | done   |
| TASK-FAVORITE-006 | Add "Favorites only" toggle to `ConsultationHistoryPage.vue`                   | REQ-FAVORITE-007, 009             | `ConsultationHistoryPage.spec.ts`       | done   |
| TASK-FAVORITE-007 | Run `npm run verify`, manually verify against real running API/UI, update README + specs/README | acceptance criteria | `npm run verify`, manual | done   |
