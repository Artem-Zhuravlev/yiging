# Tasks — Hexagram Comparison (SPEC-017)

Each task references the requirement(s) it fulfills. Mark done only once the linked test passes.

| Task ID          | Description                                                                  | Requirement(s)          | Test(s)                          | Status  |
| ------------------ | -------------------------------------------------------------------------------- | ------------------------ | ----------------------------------- | ------- |
| TASK-HEXCMP-001 | Add `LineComparison` + `HexagramComparator::compareLines()` to `yijing-core`      | REQ-HEXCMP-003          | `HexagramComparatorTest`           | done |
| TASK-HEXCMP-002 | Implement `GET /api/hexagrams/compare` + route                                    | REQ-HEXCMP-001..002, 005 | `HexagramControllerTest`          | done |
| TASK-HEXCMP-003 | Add `compareHexagrams()` + types to `entities/hexagram`                           | REQ-HEXCMP-004          | `api.spec.ts`                      | done |
| TASK-HEXCMP-004 | Build `HexagramComparePage.vue` + route                                           | REQ-HEXCMP-004          | `HexagramComparePage.spec.ts`     | done |
| TASK-HEXCMP-005 | Add "Compare hexagrams" link to `ConsultationPage.vue`                            | REQ-HEXCMP-006          | `ConsultationPage.spec.ts`        | done |
| TASK-HEXCMP-006 | Manually verify against the real running API/UI, then verify + commit             | acceptance criteria      | `npm run verify`, manual          | done |
