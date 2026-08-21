# Tasks — Personal Statistics (SPEC-024)

Each task references the requirement(s) it fulfills. Mark done only once the linked test passes.

| Task ID          | Description                                                              | Requirement(s)                    | Test(s)                             | Status |
| ------------------ | -------------------------------------------------------------------------- | ------------------------------------ | ---------------------------------------- | ------ |
| TASK-STATS-001 | Add value objects + `StatisticsRepository`/`SqliteStatisticsRepository`    | REQ-STATS-002, 003, 004, 005, 009    | `SqliteStatisticsRepositoryTest`        | done   |
| TASK-STATS-002 | Add `StatisticsController` + route                                         | REQ-STATS-001, 005                   | `StatisticsControllerTest`              | done   |
| TASK-STATS-003 | Add `entities/statistics` (model + api)                                    | REQ-STATS-010                        | (typechecked via page test)             | done   |
| TASK-STATS-004 | Add `StatisticsPage.vue` + route + nav link                                | REQ-STATS-006, 007, 008              | `StatisticsPage.spec.ts`                | done   |
| TASK-STATS-005 | Run `npm run verify`, manually verify against real running API/UI, update README + specs/README | acceptance criteria | `npm run verify`, manual | done   |
