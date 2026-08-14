# Tasks — Complete Hexagram Relationships (SPEC-014)

Each task references the requirement(s) it fulfills. Mark done only once the linked test passes.

| Task ID       | Description                                                              | Requirement(s)      | Test(s)                     | Status  |
| ------------- | ------------------------------------------------------------------------- | -------------------- | ---------------------------- | ------- |
| TASK-REL-001 | Add `relationships` to `HexagramController::toJson()` (nuclear/reversed/complement) | REQ-REL-001..004 | `HexagramControllerTest`     | done |
| TASK-REL-002 | Add `relationships` field to `entities/hexagram`'s `Hexagram` type (local `HexagramSummary`-shaped type) | REQ-REL-003 | `api.spec.ts`               | done |
| TASK-REL-003 | Verify + finalize SPEC-014                                                | acceptance criteria  | `npm run verify`              | done |
