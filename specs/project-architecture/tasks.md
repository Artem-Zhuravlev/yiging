# Tasks — Project Architecture (SPEC-001)

| Task ID        | Description                                                        | Requirement(s)             | Test(s)                          | Status |
| -------------- | -------------------------------------------------------------------- | --------------------------- | ---------------------------------- | ------ |
| TASK-ARCH-001  | Scaffold `apps/web` (Vue 3 + TS + Vite + Router + Pinia + Tailwind) | REQ-ARCH-002                | `apps/web` typecheck/lint/test/build | done   |
| TASK-ARCH-002  | Scaffold `apps/api` (Composer, PSR-4, FastRoute, HttpFoundation)    | REQ-ARCH-003                | `HealthEndpointTest`               | done   |
| TASK-ARCH-003  | Scaffold `packages/yijing-core` with zero framework dependencies   | REQ-ARCH-005                | `PackageBootstrapTest`             | done   |
| TASK-ARCH-004  | Wire `yijing-core` into `apps/api` via Composer path repository    | REQ-ARCH-004, REQ-ARCH-005  | `composer install` succeeds        | done   |
| TASK-ARCH-005  | `scripts/migrate.php` + `scripts/seed.php`, deterministic, idempotent | REQ-ARCH-006             | manual run against empty DB        | done   |
| TASK-ARCH-006  | `GET /api/health` smoke-test endpoint                               | (infra only, not a REQ)     | `HealthEndpointTest`               | done   |
| TASK-ARCH-007  | Root `npm run verify` orchestrating web + api checks                | REQ-ARCH-008                | manual run                         | done   |
| TASK-ARCH-008  | Pre-push git hook running `npm run verify`                          | REQ-ARCH-009                | manual push attempt                | done   |
| TASK-ARCH-009  | `docs/deployment.md` covering shared hosting / Apache / Nginx / VPS | REQ-ARCH-010                | manual review                      | done   |
