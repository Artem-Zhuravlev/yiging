# Yijing

A digital I Ching (Book of Changes) study & practice platform — built on an actual model of the
64 hexagrams, 8 trigrams, yin/yang lines, and changing-line mechanics, not a generic "AI fortune
teller."

## Architecture

```
Browser → static Vue assets → PHP HTTP API → SQLite
```

No Docker, VM, Kubernetes, Redis, PostgreSQL, or Node.js runtime in production — see
[SPEC-001](specs/project-architecture/spec.md) for the full constraint list and rationale.

```
yijing/
├── apps/
│   ├── web/            Vue 3 + TypeScript + Vite + Router + Pinia + Tailwind
│   └── api/             PHP 8.2 + Composer + FastRoute + PDO/SQLite
├── packages/
│   ├── yijing-core/    Framework-free I Ching domain model
│   └── shared/          Reserved for cross-cutting shared types (currently empty)
├── specs/                Spec-driven development — see specs/README.md
├── docs/                 Coding rules, deployment guide
└── scripts/              migrate.php, seed.php, verify.mjs
```

## Spec-driven development

**This project does not accept code without a spec.** Read [`specs/README.md`](specs/README.md)
before implementing anything. In short:

```
NO SPEC = NO IMPLEMENTATION = NO COMMIT = NO PUSH
```

## Requirements

- Node.js 20+ and npm (frontend build/tooling only — not needed in production)
- PHP 8.2+ with `pdo_sqlite`, `mbstring`, `json`, `openssl` extensions
- Composer

## Running locally

```bash
# Frontend
npm install
npm run dev              # http://localhost:5173

# Backend (separate terminal)
cd apps/api
composer install
cp .env.example .env
php ../../scripts/migrate.php
composer serve            # http://127.0.0.1:8000 — try /api/health
```

## Verification

```bash
npm run verify
```

Runs, in order: web lint → web typecheck → web test → web build → api lint (php-cs-fixer) →
api static analysis (PHPStan level 8) → api test (PHPUnit) → yijing-core test (PHPUnit). Fails
fast on the first broken step. This same command runs automatically on `git push` via a Husky
pre-push hook (`.husky/pre-push`) — a failing push means a failing check, not a blocked tool.

## Deployment

See [`docs/deployment.md`](docs/deployment.md) — shared hosting, Apache, Nginx+PHP-FPM, and
plain-VPS instructions, all Docker-free.

## Current specs

| ID       | Feature               | Status     |
| -------- | ----------------------- | ---------- |
| SPEC-001 | Project Architecture   | `verified` |
| SPEC-002 | I Ching Domain Model   | `draft`    |

Next recommended step: review and approve [SPEC-002](specs/domain-model/spec.md) — in
particular, resolve the open question on classical-text source/licensing — before any hexagram
domain code is written.
