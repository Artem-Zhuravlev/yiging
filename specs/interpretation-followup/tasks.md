# Tasks — Interpretation Follow-Up Questions (SPEC-034)

Each task references the requirement(s) it fulfills. Mark done only once the linked test passes.

| Task ID              | Description                                                         | Requirement(s)              | Test(s)                             | Status |
| ----------------------- | ---------------------------------------------------------------------- | -------------------------------- | ---------------------------------------- | ------ |
| TASK-FOLLOWUP-AI-001 | Add `ConversationExchange`/`FollowUpAnswer`, extend `InterpretationProvider` | REQ-FOLLOWUP-001, 003     | —                                        | done   |
| TASK-FOLLOWUP-AI-002 | Implement `GeminiInterpretationProvider::answerFollowUp()`, factor shared prompt block | REQ-FOLLOWUP-003, 004 | `GeminiInterpretationProviderTest` | done   |
| TASK-FOLLOWUP-AI-003 | Implement `MockInterpretationProvider::answerFollowUp()`               | REQ-FOLLOWUP-005                 | `MockInterpretationProviderTest`        | done   |
| TASK-FOLLOWUP-AI-004 | Add `InterpretationController::followUp()` + route                     | REQ-FOLLOWUP-001, 002, 006, 008  | `InterpretationControllerTest`          | done   |
| TASK-FOLLOWUP-AI-005 | Extend `entities/interpretation` types + api                           | REQ-FOLLOWUP-009                 | `api.spec.ts`                           | done   |
| TASK-FOLLOWUP-AI-006 | Add follow-up form + per-lens thread to `ConsultationPage.vue`         | REQ-FOLLOWUP-007                 | `ConsultationPage.spec.ts`              | done   |
| TASK-FOLLOWUP-AI-007 | Run `npm run verify`, manually verify (incl. real Gemini follow-up call referencing prior exchange), update README + specs/README | acceptance criteria | `npm run verify`, manual | done |
