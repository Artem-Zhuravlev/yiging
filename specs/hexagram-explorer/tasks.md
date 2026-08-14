# Tasks — Hexagram Explorer (SPEC-003)

Each task references the requirement(s) it fulfills. Mark done only once the linked test passes.

| Task ID         | Description                                                    | Requirement(s)              | Test(s)                      | Status  |
| --------------- | ------------------------------------------------------------------- | -------------------------------- | -------------------------------- | ------- |
| TASK-HEXAPI-001 | Implement `HexagramController::index()`                             | REQ-HEXAPI-001, REQ-HEXAPI-003   | `HexagramControllerTest`         | done |
| TASK-HEXAPI-002 | Implement `HexagramController::show()` incl. 404 handling           | REQ-HEXAPI-002                   | `HexagramControllerTest`         | done |
| TASK-HEXAPI-003 | Implement `TrigramController::index()`                               | REQ-HEXAPI-004                   | `TrigramControllerTest`          | done |
| TASK-HEXAPI-004 | Wire routes into `apps/api/config/routes.php`                        | REQ-HEXAPI-001, 002, 004         | both test files                  | done |
| TASK-HEXAPI-005 | Verify no PDO/database/App\\Readings/App\\Casting dependency          | REQ-HEXAPI-005, REQ-HEXAPI-006   | code review / PHPStan            | done |
