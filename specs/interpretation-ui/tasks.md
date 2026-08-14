# Tasks — Interpretation UI (SPEC-010)

Each task references the requirement(s) it fulfills. Mark done only once the linked test passes.

| Task ID         | Description                                                   | Requirement(s)             | Test(s)                     | Status  |
| ---------------- | ------------------------------------------------------------------ | -------------------------------- | ---------------------------------- | ------- |
| TASK-INTUI-001 | Implement `entities/interpretation` (model, api)                     | REQ-INTUI-006                    | `api.spec.ts`                       | done |
| TASK-INTUI-002 | Add "Get Interpretation" button + loading/error/loaded states        | REQ-INTUI-001, 002, 004          | `ConsultationPage.spec.ts`          | done |
| TASK-INTUI-003 | Render all fields, omitting null changingLineMeaning/transition      | REQ-INTUI-003                    | `ConsultationPage.spec.ts`          | done |
| TASK-INTUI-004 | Visually/structurally separate interpretation section                | REQ-INTUI-005                    | manual                              | done |
| TASK-INTUI-005 | Manually verify against the real running API                         | acceptance criteria              | manual (`npm run dev` + API)        | done |
