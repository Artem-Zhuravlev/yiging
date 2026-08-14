# Tasks — Consultation Flow UI (SPEC-009)

Each task references the requirement(s) it fulfills. Mark done only once the linked test passes.

| Task ID         | Description                                                       | Requirement(s)                | Test(s)                          | Status  |
| ---------------- | ------------------------------------------------------------------- | ---------------------------------- | ------------------------------------- | ------- |
| TASK-CONSUI-001 | Add `apiPost<T>()` to `shared/api/http.ts`                          | (foundation for REQ-CONSUI-003)    | `http.spec.ts`                        | done |
| TASK-CONSUI-002 | Implement `entities/consultation` (model, api)                      | REQ-CONSUI-010                     | `api.spec.ts`                         | done |
| TASK-CONSUI-003 | Implement `NewConsultationPage.vue` (question, method, manual lines) | REQ-CONSUI-001, 002                | `NewConsultationPage.spec.ts`         | done |
| TASK-CONSUI-004 | Wire submit -> create -> navigate, and 422 inline error handling     | REQ-CONSUI-003, 004                | `NewConsultationPage.spec.ts`         | done |
| TASK-CONSUI-005 | Implement `ConsultationPage.vue` incl. no-changing-lines state       | REQ-CONSUI-005, 006, 009           | `ConsultationPage.spec.ts`            | done |
| TASK-CONSUI-006 | Implement `ConsultationHistoryPage.vue` incl. empty state            | REQ-CONSUI-007, 009                | `ConsultationHistoryPage.spec.ts`     | done |
| TASK-CONSUI-007 | Wire routes + nav links + home page links                            | (routing)                          | manual                                | done |
| TASK-CONSUI-008 | Manually verify against the real running API (both methods)          | acceptance criteria                | manual (`npm run dev` + API)          | done |
