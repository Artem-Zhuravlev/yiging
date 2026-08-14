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
| SPEC-002 | [I Ching Domain Model](specs/domain-model/spec.md) | `verified` |
| SPEC-004 | [Casting Engine](specs/casting-engine/spec.md) | `verified` |
| SPEC-005 | [Readings](specs/readings/spec.md) | `verified` |
| SPEC-006 | [Consultation API](specs/consultation-api/spec.md) | `verified` |
| SPEC-003 | [Hexagram Explorer](specs/hexagram-explorer/spec.md) | `verified` |
| SPEC-007 | [Hexagram Explorer UI](specs/hexagram-explorer-ui/spec.md) | `verified` |
| SPEC-009 | [Consultation Flow UI](specs/consultation-flow-ui/spec.md) | `verified` |
| SPEC-008 | [AI Interpretation](specs/ai-interpretation/spec.md) | `verified` |
| SPEC-010 | [Interpretation UI](specs/interpretation-ui/spec.md) | `verified` |

`packages/yijing-core` now implements `Line`, `Trigram` (8), `Hexagram` (64, King Wen sequence,
plus `fromKingWenNumber()`), `changeLine()`/`getResultingHexagram()`, `YijingRelations`
(nuclear/opposite/complement), and — as of the classical-text pass — full judgment/image/6
line-statement text for all 64 hexagrams (James Legge, 1899, public domain; transcribed and
cross-checked against an independent source) via `Data/HexagramTextCatalog.php`. These fields
are non-nullable now; every hexagram everywhere (Explorer, consultation detail) shows real
classical text instead of a placeholder. 51 tests, 1530 assertions — see
[SPEC-002](specs/domain-model/spec.md).

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
[SPEC-003](specs/hexagram-explorer/spec.md). `apps/api` is at 52 tests, 380 assertions.

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

`POST /api/interpretations/{consultationId}` is now live — builds an `InterpretationContext`
from a `Consultation` (its real primary/resulting hexagrams, only the *changing* lines' text,
existing notes) and hands it to a swappable `InterpretationProvider`. The only implementation
so far is `MockInterpretationProvider`: fully deterministic, built entirely from the context's
own canonical text, no API key or external call — every `sourceReferences` entry traces back to
real Legge text, nothing invented — see [SPEC-008](specs/ai-interpretation/spec.md). `apps/api`
is now at 60 tests, 413 assertions.

`/consultations/:id` now has a "Get Interpretation" button rendering all 8 `Interpretation`
fields in a clearly separate, bordered section — never interleaved with the consultation's own
canonical hexagram/text data, matching the plan's explicit requirement to keep AI output and
canonical source visually distinct — see [SPEC-010](specs/interpretation-ui/spec.md). `apps/web`
is now at 35 tests.

**Every spec is now `verified`, SPEC-001 through SPEC-010.** The entire backend, domain model,
and frontend loop from the original plan's MVP definition (ask → cast → see → interpret → find
again) is complete and working end to end against the real running stack, not just unit-tested.

The full production path (Phase 10 of the original plan) has been dry-run end to end in an
isolated copy: `composer install --no-dev --optimize-autoloader` pulls in zero dev tooling,
`php scripts/migrate.php`/`seed.php` bootstrap a brand-new SQLite database from nothing
(including migrations added well after SPEC-001 was first verified), and the app serves the
full request path correctly under `APP_ENV=production` and with no `.env` file at all — see
[SPEC-001](specs/project-architecture/spec.md)'s 2026-08-14 re-verification note.

A pass against the plan's own security checklist (section 31) turned up one real gap — no
maximum length on user-supplied text — since fixed: `Consultation`'s `question` is capped at
2000 characters and `ConsultationNote.text` at 5000, both counted by character (not byte, so
non-Latin text like Chinese or Cyrillic isn't penalized), enforced server-side with a matching
client-side `maxlength` hint. Everything else on that checklist (prepared statements
throughout, no `v-html` anywhere so Vue's default output-escaping holds, no secrets in the
frontend) was already satisfied. See [SPEC-005](specs/readings/spec.md)'s 2026-08-14 addendum.

Next recommended steps (independent of each other): a real `InterpretationProvider` (needs an
API key + secret management + rate limiting, none of which exist yet — a deliberate gap, not an
oversight, per SPEC-008's explicit "out of scope"), or plan section 26+ territory (analytics,
search/filter on history, journal notes editing UI, SPEC-003's `GET /api/texts/{hexagramId}`-
style endpoints if canonical text ever needs its own browsing surface separate from a hexagram).
