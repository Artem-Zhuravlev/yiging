# Tasks — Hexagram Explorer UI (SPEC-007)

Each task references the requirement(s) it fulfills. Mark done only once the linked test passes.

| Task ID        | Description                                                      | Requirement(s)               | Test(s)                    | Status  |
| -------------- | ---------------------------------------------------------------------- | --------------------------------- | ------------------------------- | ------- |
| TASK-HEXUI-001 | Implement `shared/api/http.ts` (`apiGet`, `ApiError`)                   | REQ-HEXUI-001                     | `http.spec.ts`                  | done |
| TASK-HEXUI-002 | Add Vite dev-server proxy for `/api`                                    | REQ-HEXUI-006                     | manual (`npm run dev`)          | done |
| TASK-HEXUI-003 | Implement `entities/hexagram` (model, api, `HexagramLines.vue`)         | REQ-HEXUI-005, REQ-HEXUI-007      | `HexagramLines.spec.ts`         | done |
| TASK-HEXUI-004 | Implement `HexagramListPage.vue` + route                                | REQ-HEXUI-002                     | `HexagramListPage.spec.ts`      | done |
| TASK-HEXUI-005 | Implement `HexagramDetailPage.vue` + route, incl. 404/error states      | REQ-HEXUI-003, REQ-HEXUI-004      | `HexagramDetailPage.spec.ts`    | done |
| TASK-HEXUI-006 | Add nav shell to `App.vue`                                              | (UX, not independently testable)  | manual                          | done |
| TASK-HEXUI-007 | Manually verify against the real running API                            | acceptance criterion              | manual (`npm run dev` + API)    | done |
