# Tasks — Consultation API (SPEC-006)

Each task references the requirement(s) it fulfills. Mark done only once the linked test passes.

| Task ID       | Description                                                     | Requirement(s)              | Test(s)                     | Status  |
| ------------- | -------------------------------------------------------------------- | -------------------------------- | ------------------------------ | ------- |
| TASK-CAPI-001 | Implement `ConsultationController::create()` for all 3 methods       | REQ-CAPI-001, REQ-CAPI-002       | `ConsultationControllerTest`   | done |
| TASK-CAPI-002 | Map validation failures (empty question, bad method, bad lines) to 422 | REQ-CAPI-003                   | `ConsultationControllerTest`   | done |
| TASK-CAPI-003 | Implement `ConsultationController::index()`                          | REQ-CAPI-004                     | `ConsultationControllerTest`   | done |
| TASK-CAPI-004 | Implement `ConsultationController::show()` incl. 404                 | REQ-CAPI-005                     | `ConsultationControllerTest`   | done |
| TASK-CAPI-005 | Define the JSON response shape (`toJson()`)                          | REQ-CAPI-006                     | `ConsultationControllerTest`   | done |
| TASK-CAPI-006 | Wire routes into `apps/api/config/routes.php`                        | REQ-CAPI-001, 004, 005           | `ConsultationControllerTest`   | done |
| TASK-CAPI-007 | Verify controller has no direct PDO/SQL usage                        | REQ-CAPI-007, REQ-CAPI-008       | code review / PHPStan          | done |
| TASK-CAPI-008 | Fix `SqliteConsultationRepository::findAll()` ordering (found via this spec's tests) | SPEC-005 REQ-READ-009 | `ConsultationControllerTest::testIndexReturnsAllConsultationsNewestFirst` | done |
