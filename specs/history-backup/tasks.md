# Tasks — Consultation History Backup (SPEC-028)

Each task references the requirement(s) it fulfills. Mark done only once the linked test passes.

| Task ID          | Description                                                                 | Requirement(s)                    | Test(s)                             | Status |
| ------------------ | -------------------------------------------------------------------------------- | -------------------------------------- | ---------------------------------------- | ------ |
| TASK-BACKUP-001 | Add `existsById()`/`updateFollowUpLink()` to repository interface + SQLite impl  | REQ-BACKUP-003, 005, 008              | `SqliteConsultationRepositoryTest`      | done   |
| TASK-BACKUP-002 | Add `ConsultationController::import()` + route                                   | REQ-BACKUP-002..006, 008              | `ConsultationControllerTest`            | done   |
| TASK-BACKUP-003 | Add `exportConsultationsBackup()`/`importConsultationsBackup()` to `entities/consultation/api.ts` | REQ-BACKUP-001, 009 | `api.spec.ts`                    | done   |
| TASK-BACKUP-004 | Add export button + import file picker to `ConsultationHistoryPage.vue`          | REQ-BACKUP-007                        | `ConsultationHistoryPage.spec.ts`       | done   |
| TASK-BACKUP-005 | Run `npm run verify`, manually verify export→import round-trip against real running API/UI, update README + specs/README | acceptance criteria | `npm run verify`, manual | done   |
