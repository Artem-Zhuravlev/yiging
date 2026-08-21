# Tasks — Consultation Public Share Link (SPEC-029)

Each task references the requirement(s) it fulfills. Mark done only once the linked test passes.

| Task ID         | Description                                                              | Requirement(s)                | Test(s)                             | Status |
| ----------------- | -------------------------------------------------------------------------- | ---------------------------------- | ---------------------------------------- | ------ |
| TASK-SHARE-001 | Add `/share/consultations/:id` route with `meta: { public: true }`         | REQ-SHARE-001                      | (exercised via page test)               | done   |
| TASK-SHARE-002 | Hide main nav on public routes in `App.vue`                                | REQ-SHARE-005                      | `App.spec.ts`                           | done   |
| TASK-SHARE-003 | Build `SharedConsultationPage.vue`                                         | REQ-SHARE-001, 002, 003, 004, 007  | `SharedConsultationPage.spec.ts`        | done   |
| TASK-SHARE-004 | Add "Copy Share Link"/"View Public Share Page" to `ConsultationPage.vue`   | REQ-SHARE-006                      | `ConsultationPage.spec.ts`              | done   |
| TASK-SHARE-005 | Run `npm run verify`, manually verify against real running API/UI, update README + specs/README | acceptance criteria | `npm run verify`, manual | done   |
