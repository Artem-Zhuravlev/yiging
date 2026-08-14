# Tasks — Consultation Notes & Tags Editing (SPEC-013)

Each task references the requirement(s) it fulfills. Mark done only once the linked test passes.

| Task ID        | Description                                                        | Requirement(s)               | Test(s)                        | Status  |
| --------------- | ------------------------------------------------------------------- | ----------------------------------- | -------------------------------- | ------- |
| TASK-EDIT-001 | Implement `ConsultationController::update()` (note + tag, both optional) | REQ-EDIT-001..006             | `ConsultationControllerTest`     | done |
| TASK-EDIT-002 | Wire `PATCH /api/consultations/{id}` route                            | REQ-EDIT-001                       | `ConsultationControllerTest`     | done |
| TASK-EDIT-003 | Add `apiPatch<T>()` to `shared/api/http.ts`                           | (foundation for REQ-EDIT-010)      | `http.spec.ts`                   | done |
| TASK-EDIT-004 | Add `updateConsultation()` to `entities/consultation/api.ts`          | REQ-EDIT-010                       | `api.spec.ts`                    | done |
| TASK-EDIT-005 | Add note form + tag form to `ConsultationPage.vue`                    | REQ-EDIT-007, REQ-EDIT-008         | `ConsultationPage.spec.ts`       | done |
| TASK-EDIT-006 | Manually verify against the real running API + UI, then verify + commit | acceptance criteria             | `npm run verify`, manual         | done |
