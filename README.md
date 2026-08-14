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
| SPEC-005 | [Readings](specs/readings/spec.md) | `verified` |
| SPEC-006 | [Consultation API](specs/consultation-api/spec.md) | `verified` |
| SPEC-003 | [Hexagram Explorer](specs/hexagram-explorer/spec.md) | `verified` |
| SPEC-007 | [Hexagram Explorer UI](specs/hexagram-explorer-ui/spec.md) | `verified` |
| SPEC-009 | [Consultation Flow UI](specs/consultation-flow-ui/spec.md) | `verified` |

`packages/yijing-core` now implements `Line`, `Trigram` (8), `Hexagram` (64, King Wen sequence,
plus `fromKingWenNumber()`), `changeLine()`/`getResultingHexagram()`, and `YijingRelations`
(nuclear/opposite/complement) — 48 tests, 757 assertions. Judgment/image/line-statement text
(Legge, 1899, public domain) is the remaining work — see
[SPEC-002's tasks.md](specs/domain-model/tasks.md).

`apps/api/src/Casting` implements the casting engine — `DivinationMethod` interface with
`ThreeCoinsMethod` (traditional 6/7/8/9 coin-sum rule), `ManualMethod`, and `RandomMethod`
(dev/test only), all built on an injected `CoinTosser` so casting stays testable without true
randomness — see [SPEC-004](specs/casting-engine/spec.md).

`apps/api/src/Readings` implements the `Consultation` aggregate (question, method used,
primary/resulting hexagram, notes with before/after/later labels, tags) and its SQLite
persistence (`ConsultationRepository`/`SqliteConsultationRepository`, migration for
`consultations`/`consultation_notes`/`tags`/`consultation_tags`) — see
[SPEC-005](specs/readings/spec.md).

`POST /api/consultations`, `GET /api/consultations`, and `GET /api/consultations/{id}` are now
live — `ConsultationController` wires a chosen `Casting` method into a persisted `Consultation`
and returns it as JSON, with `422`/`404` handled explicitly (no uncaught exceptions reaching
the client) — see [SPEC-006](specs/consultation-api/spec.md).

`GET /api/hexagrams`, `GET /api/hexagrams/{number}`, and `GET /api/trigrams` are now live —
read-only browsing over `yijing-core`'s static reference data, no database involved — see
[SPEC-003](specs/hexagram-explorer/spec.md). `apps/api` is now at 52 tests, 380 assertions.

`apps/web` now has a real page: `/hexagrams` and `/hexagrams/:number`, backed by a small typed
API client (`shared/api`) and a Vite dev proxy so `npm run dev` talks to the PHP dev server
same-origin, no CORS needed — see [SPEC-007](specs/hexagram-explorer-ui/spec.md). This is the
pattern (`entities/<domain>` for types+fetch, `pages/<domain>` for routes, feature-sliced
layering) later pages (the consultation flow) will follow.

The consultation flow is live: `/consultations/new` (question + Three Coins/Manual casting),
`/consultations/:id` (full detail — hexagram diagrams with changing lines marked, notes, tags),
and `/consultations` (history, newest-first) — see
[SPEC-009](specs/consultation-flow-ui/spec.md). `apps/web` is now at 30 tests. This completes
the plan's core MVP loop end to end: ask a question → cast → see the hexagram → find it again
later.

Next recommended step: populate classical text for all 64 hexagrams (SPEC-002's remaining
tasks) so judgment/image/line-statement fields stop being `null` everywhere the UI shows them,
or start SPEC-008 (AI interpretation) now that there's a full consultation record to interpret.
