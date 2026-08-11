# SPEC-001 — Project Architecture

**Status:** verified
**Owner:** bootstrap
**Last updated:** 2026-08-11

## Problem

Yijing needs a foundation that can grow into a full I Ching study platform (hexagram browsing,
casting, readings, journal, AI-assisted interpretation, visualization) without requiring
infrastructure the project may not always be able to run — no Docker, no VM, no managed
database service. It must also be deployable to ordinary shared/cPanel-style PHP hosting.

## Purpose

Define the deployment constraints, technology stack, monorepo layout, and domain boundaries
that every later feature spec builds on top of.

## Scope

- Deployment constraints (what infrastructure is and isn't allowed)
- Frontend stack and build output
- Backend stack and build output
- Monorepo directory structure
- Domain boundary rules (where business logic may and may not live)
- Spec-driven development workflow (see [`specs/README.md`](../README.md))
- Verification pipeline and git push gating

## Out of scope

- The actual I Ching domain model (trigrams, hexagrams, lines) — see SPEC-002.
- Casting, readings, journal, auth, AI interpretation — each gets its own future spec.
- CI/CD provider configuration beyond a local pre-push hook.

## Functional requirements

- **REQ-ARCH-001** — The application MUST run in production with only PHP 8.2+, a web server
  (Apache or Nginx), SQLite, and normal filesystem access. No Docker, VMs, Kubernetes, Redis,
  PostgreSQL, Elasticsearch, browser-automation tooling, background workers, or a Node.js
  runtime may be required in production.
- **REQ-ARCH-002** — The frontend MUST be Vue 3 + TypeScript (strict) + Vite + Vue Router +
  Pinia + Tailwind CSS, and MUST compile to static assets via `npm run build`.
- **REQ-ARCH-003** — The backend MUST be PHP 8.2+ with Composer/PSR-4 autoloading, PDO for
  database access, and SQLite as the only database engine. Framework choice must stay
  lightweight and portable (no Laravel without a documented reason).
- **REQ-ARCH-004** — The repository MUST be a monorepo with `apps/web` (frontend),
  `apps/api` (backend), `packages/yijing-core` (pure domain logic), `packages/shared`,
  `specs/`, `docs/`, and `scripts/` at the top level.
- **REQ-ARCH-005** — `packages/yijing-core` MUST NOT depend on Vue, any PHP framework, HTTP,
  a database, AI, or the filesystem. It contains only pure domain logic.
- **REQ-ARCH-006** — The database MUST be creatable from scratch via a documented, scriptable
  mechanism (`php scripts/migrate.php`, `php scripts/seed.php`), with deterministic seed data.
- **REQ-ARCH-007** — Every feature MUST have an approved spec before implementation, and a
  passing verification pipeline before it may be pushed (see SDD workflow).

## Non-functional requirements

- **REQ-ARCH-008** — `npm run verify` at the repo root MUST run lint, typecheck, tests, and
  build for the frontend, and lint (PSR-12), static analysis, and tests for the backend, and
  MUST fail the process (non-zero exit) if any step fails.
- **REQ-ARCH-009** — A pre-push git hook MUST run the verification pipeline and block the push
  on failure. `--no-verify` must not be used without explicit human instruction.
- **REQ-ARCH-010** — Deployment MUST be documented for shared hosting, Apache, Nginx+PHP-FPM,
  and a plain VPS without Docker.

## Data requirements

None beyond the SQLite file itself and the `schema_migrations` bookkeeping table used by the
migration runner.

## API requirements

A minimal `GET /api/health` endpoint exists as an infrastructure smoke test (routing → PDO
config → JSON response, end to end). It carries no product meaning and is not itself a feature
spec.

## Edge cases

- Fresh clone, no `.env` file → API falls back to sensible defaults (`APP_ENV=production`,
  `DATABASE_PATH=./database/database.sqlite` resolved relative to `apps/api/`).
- `apps/api/database/database.sqlite` missing → `php scripts/migrate.php` creates it.
- No migrations pending → migrate script reports "up to date" and exits 0.

## Acceptance criteria

- [x] `npm run dev` (in `apps/web`) serves the app locally.
- [x] `npm run build` (in `apps/web`) produces a static `dist/`.
- [x] `php -S 127.0.0.1:8000 -t public` (in `apps/api`) serves `/api/health` as JSON.
- [x] `php scripts/migrate.php` creates `apps/api/database/database.sqlite` from nothing.
- [x] Frontend lint, typecheck, test, and build all pass.
- [x] Backend lint (php-cs-fixer), static analysis (PHPStan level 8), and tests all pass.
- [x] No Docker, VM, Kubernetes, Redis, PostgreSQL, or Node runtime is required in production.
