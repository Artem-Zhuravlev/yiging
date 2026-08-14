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
| SPEC-011 | [Gemini Interpretation Provider](specs/gemini-interpretation-provider/spec.md) | `verified` (code); live call unverified |
| SPEC-012 | [AI Endpoint Rate Limiting](specs/ai-rate-limiting/spec.md) | `verified` |
| SPEC-013 | [Consultation Notes & Tags Editing](specs/consultation-editing/spec.md) | `verified` |
| SPEC-014 | [Complete Hexagram Relationships](specs/hexagram-relationships/spec.md) | `verified` |

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

A real `InterpretationProvider` now exists: `GeminiInterpretationProvider`, backed by Google's
Gemini API, selectable via `AI_PROVIDER=gemini` in `apps/api/.env` (default stays `mock` — no
key needed, safe out of the box). `sourceReferences` is never LLM-generated for either
provider — `InterpretationContext::defaultSourceReferences()` computes it once, shared, so a
citation can never be hallucinated regardless of which provider answered. A misconfigured
provider (`gemini` selected, empty key) fails loudly at startup rather than silently serving
mock output; any Gemini API failure maps to a clean `502`, never a raw stack trace — and now
neither does *any* uncaught exception anywhere in the app, since `Kernel::handle()` gained a
catch-all specifically because this is the app's first dependency on something genuinely
outside its control. See [SPEC-011](specs/gemini-interpretation-provider/spec.md) — **and
note its one open item: this session had no API key to test against, so the exact request
contract is verified by research (Google's current docs, cross-checked across 3 fetches), not
by a real call. Set `AI_API_KEY` in `apps/api/.env` and try it to complete that verification.**

`POST /api/interpretations/{id}` is now rate-limited — `AI_RATE_LIMIT_MAX` (default 20)
requests per `AI_RATE_LIMIT_WINDOW_SECONDS` (default 3600) per client IP, backed by a new
`rate_limit_hits` SQLite table, no external cache needed. Applies identically regardless of
which provider is configured; a rejected request never reaches the repository, context
builder, or provider at all. Manually verified against the live dev server: 20 real requests
all `200`, the 21st `429` with `Retry-After: 3600` — see
[SPEC-012](specs/ai-rate-limiting/spec.md). Known limitation, stated plainly: behind a reverse
proxy, this needs that deployment's trusted-proxy configuration to key on the real client IP
rather than the proxy's own address (still caps *total* volume through the proxy either way).

`PATCH /api/consultations/{id}` is now live — the plan's Definition of Done listed "add a note"
as an explicit MVP step, and until now nothing between the domain layer
(`Consultation::withAddedNote()`/`withAddedTag()`, present since SPEC-005) and the read-only
display on `ConsultationPage` (since SPEC-009) actually let a person add one. `/consultations/:id`
now has working "add a note" and "add a tag" forms, each with its own loading/error state, that
update the page in place on success without a full reload — see
[SPEC-013](specs/consultation-editing/spec.md). This completes the plan's MVP loop end to end:
ask → cast → see → interpret → **add a note** → find again.

`GET /api/hexagrams/{id}` and `GET /api/hexagrams` now include a `relationships` object —
`nuclear` (互卦), `reversed` (綜卦, line order flipped), and `complement` (錯卦, every line's
polarity flipped) — each the existing `{kingWenNumber, chineseName, pinyin}` summary shape,
computed entirely by `packages/yijing-core`'s already-tested `YijingRelations` (no new domain
logic, no relationship math in the frontend). This is feature 21 of the plan's next batch
(features 21-40); the Hexagram Explorer UI that navigates this relationship graph is feature 22,
next. See [SPEC-014](specs/hexagram-relationships/spec.md).

Next recommended steps: continue the plan's feature 21-40 batch in order (22: Hexagram Explorer
UI navigating relationships → 23: Visual Hexagram Editor → ... → 40: AI Conversation Per
Consultation), or verify the live Gemini call (see above) whenever an `AI_API_KEY` is available.
