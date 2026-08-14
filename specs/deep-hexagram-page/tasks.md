# Tasks — Deep Hexagram Page (SPEC-018)

Each task references the requirement(s) it fulfills. Mark done only once the linked test passes.

| Task ID           | Description                                                          | Requirement(s)        | Test(s)                       | Status  |
| ------------------ | ------------------------------------------------------------------------ | ---------------------- | -------------------------------- | ------- |
| TASK-DEEPHEX-001 | Add `Hexagram::symbol()` to `yijing-core`                                | REQ-DEEPHEX-001, 006  | `HexagramTest`                  | done |
| TASK-DEEPHEX-002 | Add `symbol` to `HexagramController::toJson()`                           | REQ-DEEPHEX-001       | `HexagramControllerTest`        | done |
| TASK-DEEPHEX-003 | Render symbol, line texts, and source attribution on `HexagramDetailPage.vue` | REQ-DEEPHEX-002..005 | `HexagramDetailPage.spec.ts`   | done |
| TASK-DEEPHEX-004 | Update all other hand-built `Hexagram` test fixtures with `symbol`        | (mechanical)           | full frontend suite             | done |
| TASK-DEEPHEX-005 | Manually verify against the real running API/UI, then verify + commit    | acceptance criteria    | `npm run verify`, manual        | done |
