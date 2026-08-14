# Tasks — Casting Engine (SPEC-004)

Each task references the requirement(s) it fulfills. Mark done only once the linked test passes.

| Task ID      | Description                                                          | Requirement(s)          | Test(s)               | Status  |
| ------------ | ---------------------------------------------------------------------- | -------------------------- | ------------------------ | ------- |
| TASK-CAST-001 | Implement `Coin` enum + `CoinTosser` interface + `RandomIntCoinTosser` | REQ-CAST-005, REQ-CAST-011 | (used by other tests)    | done |
| TASK-CAST-002 | Implement `DivinationMethod` interface                                | REQ-CAST-001, REQ-CAST-002 | (implemented by below)   | done |
| TASK-CAST-003 | Implement `ThreeCoinsMethod`                                          | REQ-CAST-003, REQ-CAST-004 | `ThreeCoinsMethodTest`   | done |
| TASK-CAST-004 | Implement `ManualMethod`                                              | REQ-CAST-006, REQ-CAST-007 | `ManualMethodTest`       | done |
| TASK-CAST-005 | Implement `RandomMethod`                                              | REQ-CAST-008               | `RandomMethodTest`       | done |
| TASK-CAST-006 | `FakeCoinTosser` test double + exhaustive 8-outcome coverage           | REQ-CAST-010               | `ThreeCoinsMethodTest`   | done |
| TASK-CAST-007 | Verify zero HTTP/PDO/AI dependency in `Casting`, only `yijing-core`    | REQ-CAST-009               | code review / PHPStan    | done |
