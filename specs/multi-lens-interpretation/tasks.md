# Tasks — Multi-Lens Interpretation (SPEC-033)

Each task references the requirement(s) it fulfills. Mark done only once the linked test passes.

| Task ID          | Description                                                              | Requirement(s)                | Test(s)                             | Status |
| ------------------ | -------------------------------------------------------------------------- | ---------------------------------- | ---------------------------------------- | ------ |
| TASK-LENS-001 | Add `InterpretationLens` enum, update `InterpretationProvider` interface    | REQ-LENS-001..006                  | —                                        | done   |
| TASK-LENS-002 | Update `GeminiInterpretationProvider` prompt building per lens              | REQ-LENS-003, 004                  | `GeminiInterpretationProviderTest`      | done   |
| TASK-LENS-003 | Update `MockInterpretationProvider` to disclose lens honestly               | REQ-LENS-005                       | `MockInterpretationProviderTest`        | done   |
| TASK-LENS-004 | Update `InterpretationController` to parse/validate/echo `lens`             | REQ-LENS-001, 002, 006, 008        | `InterpretationControllerTest`          | done   |
| TASK-LENS-005 | Extend `entities/interpretation` types + api                                | REQ-LENS-009                       | `api.spec.ts`                           | done   |
| TASK-LENS-006 | Add lens selector + per-lens cache to `ConsultationPage.vue`                | REQ-LENS-007                       | `ConsultationPage.spec.ts`              | done   |
| TASK-LENS-007 | Run `npm run verify`, manually verify (incl. one real Gemini call for a non-general lens), update README + specs/README | acceptance criteria | `npm run verify`, manual | done   |
