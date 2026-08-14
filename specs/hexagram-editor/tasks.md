# Tasks — Visual Hexagram Editor (SPEC-016)

Each task references the requirement(s) it fulfills. Mark done only once the linked test passes.

| Task ID           | Description                                                            | Requirement(s)          | Test(s)                        | Status  |
| ------------------ | ------------------------------------------------------------------------- | ------------------------ | --------------------------------- | ------- |
| TASK-HEXEDIT-001 | Implement `GET /api/hexagrams/from-lines` + route                          | REQ-HEXEDIT-001..003   | `HexagramControllerTest`         | done |
| TASK-HEXEDIT-002 | Add `computeHexagramFromLines()` to `entities/hexagram/api.ts`             | REQ-HEXEDIT-004         | `api.spec.ts`                     | done |
| TASK-HEXEDIT-003 | Build `HexagramEditorPage.vue` + route + link from `HexagramListPage`      | REQ-HEXEDIT-004..005   | `HexagramEditorPage.spec.ts`     | done |
| TASK-HEXEDIT-004 | Manually verify against the real running API/UI, then verify + commit      | acceptance criteria      | `npm run verify`, manual         | done |
