# Plan — Project Architecture (SPEC-001)

**Depends on spec status:** `approved`

## Technical approach

Monorepo with two independently deployable halves:

```
Browser → static Vue assets → PHP HTTP API → SQLite
```

The frontend is a pure Vite build with no server-side rendering. The backend is a thin PHP
front controller (`public/index.php`) that dispatches through a minimal FastRoute-based router
into plain PHP classes — no full-stack framework, so it stays deployable on shared hosting.

## Architecture decisions

- **FastRoute over a framework router** — a single-purpose routing library keeps `apps/api`
  free of framework lock-in and framework-specific deployment requirements.
- **Symfony HttpFoundation for Request/Response** — well-tested HTTP abstractions without
  pulling in the rest of a framework.
- **`yijing-core` as a separate Composer package (path repository)** — enforces that domain
  logic (trigrams, hexagrams, casting math) cannot accidentally depend on HTTP, PDO, or Vue,
  by making it physically impossible to `use` those from that package's dependency graph.
- **SQLite only** — matches the no-Docker/no-managed-DB constraint and is trivially portable
  (a single file, copyable between environments).
- **Vitest + PHPUnit** — same-ecosystem test runners for each half, no cross-cutting test
  infrastructure needed.

## Affected areas

- `apps/web/` — Vite + Vue 3 + TS + Router + Pinia + Tailwind scaffold.
- `apps/api/` — Composer project, PSR-4 `App\`, FastRoute + HttpFoundation, PHPUnit, PHPStan,
  php-cs-fixer.
- `packages/yijing-core/` — Composer library `yijing/core`, PSR-4 `Yijing\Core\`, no
  dependencies beyond PHP itself.
- `packages/shared/` — reserved for cross-cutting shared types/constants once a concrete need
  exists (currently empty scaffold).
- `scripts/migrate.php`, `scripts/seed.php` — database bootstrap.
- Root `package.json` — orchestrates `npm run verify` across both apps.
- `.husky/pre-push` — verification gate.

## Data / schema changes

`schema_migrations` tracking table, created automatically by `scripts/migrate.php`. No product
tables yet — those arrive with SPEC-002 and later feature specs.

## Risks / open questions

- `packages/shared` has no concrete contents yet; if nothing needs it by the time SPEC-003
  lands, consider removing it rather than keeping a permanently empty package.
- FastRoute's route cache is disabled (simpleDispatcher, not cached) for simplicity; revisit if
  route count grows large enough to matter for shared-hosting performance.
