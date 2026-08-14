# Tasks — AI Endpoint Rate Limiting (SPEC-012)

Each task references the requirement(s) it fulfills. Mark done only once the linked test passes.

| Task ID        | Description                                                        | Requirement(s)         | Test(s)                          | Status  |
| --------------- | ------------------------------------------------------------------ | ---------------------------- | -------------------------------------- | ------- |
| TASK-RATE-001 | Write migration for `rate_limit_hits`                                | REQ-RATE-005                 | manual `php scripts/migrate.php` run   | done |
| TASK-RATE-002 | Implement `RateLimiter` interface + `SqliteRateLimiter`              | REQ-RATE-001                 | `SqliteRateLimiterTest`                | done |
| TASK-RATE-003 | Add `Config::int()` + `ai_rate_limit_max`/`ai_rate_limit_window_seconds` | (foundation)               | —                                       | done |
| TASK-RATE-004 | Wire rate-limit check into `InterpretationController::create()`      | REQ-RATE-002, REQ-RATE-003   | `InterpretationControllerTest`         | done |
| TASK-RATE-005 | Document new env vars in `.env.example`                              | (documentation)              | manual review                          | done |
| TASK-RATE-006 | Verify + manually confirm 429 against the real running API + commit  | acceptance criteria          | `npm run verify`                       | done |
