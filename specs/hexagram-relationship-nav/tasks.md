# Tasks — Hexagram Relationship Navigation (SPEC-015)

Each task references the requirement(s) it fulfills. Mark done only once the linked test passes.

| Task ID          | Description                                                          | Requirement(s)          | Test(s)                   | Status  |
| ----------------- | ----------------------------------------------------------------------- | ------------------------ | --------------------------- | ------- |
| TASK-RELNAV-001 | Add "Related Hexagrams" section to `HexagramDetailPage.vue` with self-reference handling | REQ-RELNAV-001..004 | `HexagramDetailPage.spec.ts` | done |
| TASK-RELNAV-002 | Fix route-reactivity bug: `onMounted` → `watch(kingWenNumber, ..., { immediate: true })` | REQ-RELNAV-005 | `HexagramDetailPage.spec.ts` | done |
| TASK-RELNAV-003 | Manually verify multi-hop navigation against the real running API/UI, then verify + commit | acceptance criteria | `npm run verify`, manual | done |
