# Plan — AI Endpoint Rate Limiting (SPEC-012)

**Depends on spec status:** `approved`

## Technical approach

```
apps/api/src/AI/
├── RateLimiter.php               interface: attempt(string $key): bool
├── SqliteRateLimiter.php          the only implementation
└── InterpretationController.php   + rate-limit check as the first thing create() does

apps/api/src/Core/
└── Config.php                     + int(), ai_rate_limit_max, ai_rate_limit_window_seconds

apps/api/database/migrations/
└── 2026_08_14_000002_create_rate_limit_hits.php
```

- `RateLimiter::attempt(string $key): bool` — one call does both the check and the recording,
  avoiding a separate check-then-record pair of calls that could race (immaterial at this
  traffic scale, but a single atomic-feeling call is simpler to reason about regardless).
- `SqliteRateLimiter`: `SELECT COUNT(*) FROM rate_limit_hits WHERE rate_limit_key = :key AND
  created_at >= :window_start`; if the count is already `>= maxAttempts`, return `false`
  without inserting; otherwise `INSERT` a row with the current timestamp and return `true`.
  Fixed-lookback counting (not a token/leaky bucket) — simplest correct approach, and every
  decision is auditable by just reading the table.
- `Config` gains `int(string $key): int` (parallel to the existing `string()`) and two new
  `fromEnv()` entries: `ai_rate_limit_max` (default `20`), `ai_rate_limit_window_seconds`
  (default `3600`).
- `InterpretationController::create()`: the very first lines become the rate-limit check —
  before the repository lookup, before context building, before touching the provider at all.
  On rejection: `429` with `Retry-After: {windowSeconds}` and a plain error body, matching
  every other controller's `{"error": "..."}` shape.

## Architecture decisions

- **One limit for both providers, not "only when gemini is active."** Two reasons: (1) the
  mock provider is nearly free but not literally free (it still does DB work per request), so
  unlimited hammering is still not something to leave wide open; (2) a single, unconditional
  policy is simpler to reason about and test than one that changes behavior based on which
  provider happens to be configured — the rate limit is a property of the *endpoint*, not of
  whichever provider is behind it today.
- **Fixed-lookback SQL count, not a token bucket.** A token bucket (or leaky bucket) is the
  more sophisticated standard approach, but it needs either an in-memory store (not available
  across PHP-FPM/CLI-server workers without a shared cache this project deliberately doesn't
  have — no Redis, per SPEC-001) or extra bookkeeping columns to simulate refill. A
  `COUNT(*) WHERE created_at >= window_start` query against SQLite is simpler, exactly as
  correct for "N requests per window" semantics, and needs nothing this project doesn't already
  have.
- **Keyed by IP, with the trusted-proxy caveat stated rather than solved.** Solving
  `X-Forwarded-For` correctly requires knowing the specific deployment's proxy topology, which
  varies per installation (`docs/deployment.md` documents several). Getting it wrong silently
  (trusting an untrusted client-supplied header) would be worse than the current honest
  limitation (one shared bucket behind a proxy) — so this spec states the limitation rather than
  guessing at a specific deployment's trusted-proxy list.
- **No cleanup job for old rows.** Named explicitly in spec.md as deferred until it's a real
  problem — adding TTL/pruning logic now, before there's any evidence of it mattering at this
  app's traffic scale, would be exactly the kind of premature complexity the project's own
  coding rules warn against.

## Affected areas

- `apps/api/src/AI/RateLimiter.php` (new)
- `apps/api/src/AI/SqliteRateLimiter.php` (new)
- `apps/api/src/AI/InterpretationController.php` (rate-limit check)
- `apps/api/src/Core/Config.php` (`int()`, 2 new env-backed values)
- `apps/api/database/migrations/2026_08_14_000002_create_rate_limit_hits.php` (new)
- `apps/api/.env.example` (document the 2 new vars)
- `apps/api/tests/AI/**` (new + updated tests)

## Data / schema changes

New `rate_limit_hits` table — see spec.md "Data requirements" for full DDL.

## Risks / open questions

- None currently open. The trusted-proxy and row-cleanup limitations are both named explicitly
  in spec.md, not silently dropped.
