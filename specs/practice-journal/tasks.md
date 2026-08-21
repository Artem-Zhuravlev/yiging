# Tasks — Practice Journal (SPEC-030)

Each task references the requirement(s) it fulfills. Mark done only once the linked test passes.

| Task ID           | Description                                                              | Requirement(s)             | Test(s)                             | Status |
| -------------------- | -------------------------------------------------------------------------- | ------------------------------- | ---------------------------------------- | ------ |
| TASK-JOURNAL-001 | Add migration + `JournalEntry`/`Clock`/`SystemClock`/id generator          | REQ-JOURNAL-001, 006            | `JournalEntryTest`                      | done   |
| TASK-JOURNAL-002 | Add `JournalRepository`/`SqliteJournalRepository`                          | REQ-JOURNAL-002                 | `SqliteJournalRepositoryTest`           | done   |
| TASK-JOURNAL-003 | Add `JournalController` + routes                                           | REQ-JOURNAL-001, 002            | `JournalControllerTest`                 | done   |
| TASK-JOURNAL-004 | Add `entities/journal` (model + api)                                       | REQ-JOURNAL-007                 | (typechecked via page test)             | done   |
| TASK-JOURNAL-005 | Add `JournalPage.vue` + route + nav link                                   | REQ-JOURNAL-003, 004, 005       | `JournalPage.spec.ts`, `App.spec.ts`    | done   |
| TASK-JOURNAL-006 | Run `npm run verify`, manually verify against real running API/UI, update README + specs/README | acceptance criteria | `npm run verify`, manual | done   |
