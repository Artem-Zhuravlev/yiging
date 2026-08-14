# Tasks — Readings (SPEC-005)

Each task references the requirement(s) it fulfills. Mark done only once the linked test passes.

| Task ID       | Description                                                          | Requirement(s)                        | Test(s)                          | Status  |
| ------------- | ----------------------------------------------------------------------- | ----------------------------------------- | ----------------------------------- | ------- |
| TASK-READ-001 | Implement `CastingMethodName`, `NoteLabel`, `ConsultationNote` enums/VO | REQ-READ-006                              | `ConsultationTest`                  | done |
| TASK-READ-002 | Implement `Consultation` aggregate (`create`, `withAddedNote`, `withAddedTag`, `changingLinePositions`) | REQ-READ-001..005, REQ-READ-012 | `ConsultationTest`  | done |
| TASK-READ-003 | Implement `Clock`/`SystemClock` and `ConsultationIdGenerator`/`UuidV4ConsultationIdGenerator` | REQ-READ-013 | `UuidV4ConsultationIdGeneratorTest` | done |
| TASK-READ-004 | Write migration for `consultations`/`consultation_notes`/`tags`/`consultation_tags` | REQ-READ data requirements, REQ-READ-011 | manual `php scripts/migrate.php` run | done |
| TASK-READ-005 | Implement `ConsultationRepository` interface + `SqliteConsultationRepository` | REQ-READ-007..010, REQ-READ-014 | `SqliteConsultationRepositoryTest` | done |
| TASK-READ-006 | Verify zero dependency on `App\Casting` in `Readings`                | REQ-READ-012                              | code review / PHPStan               | done |
