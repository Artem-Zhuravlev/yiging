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
| SPEC-002 | I Ching Domain Model   | `in-progress` (structural pass done, classical text pending) |
| SPEC-004 | [Casting Engine](specs/casting-engine/spec.md) | `verified` |

`packages/yijing-core` now implements `Line`, `Trigram` (8), `Hexagram` (64, King Wen sequence),
`changeLine()`/`getResultingHexagram()`, and `YijingRelations` (nuclear/opposite/complement) —
46 tests, 244 assertions. Judgment/image/line-statement text (Legge, 1899, public domain) is the
remaining work — see [SPEC-002's tasks.md](specs/domain-model/tasks.md).

`apps/api/src/Casting` implements the casting engine — `DivinationMethod` interface with
`ThreeCoinsMethod` (traditional 6/7/8/9 coin-sum rule), `ManualMethod`, and `RandomMethod`
(dev/test only), all built on an injected `CoinTosser` so casting stays testable without true
randomness — see [SPEC-004](specs/casting-engine/spec.md).

Next recommended step: SPEC-005 (Readings) — the `Consultation` aggregate (question, timestamp,
notes, tags, method used) that wraps a `Casting` result, plus its SQLite persistence — or
populate classical text for all 64 hexagrams (SPEC-002's remaining tasks) in parallel.
