# SPEC-012 — AI Endpoint Rate Limiting

**Status:** verified
**Owner:** unassigned
**Last updated:** 2026-08-14

## Problem

`POST /api/interpretations/{id}` now carries real cost once `AI_PROVIDER=gemini` is configured
(SPEC-011) — and has none of the caller-side throttling the plan's own security checklist
(section 31, "rate limiting for the AI endpoint") calls for. Nothing currently stops a client
(malicious or just buggy — e.g. a retry loop) from calling it without limit.

## Purpose

Add per-client request throttling to `POST /api/interpretations/{id}` specifically — the one
endpoint with real external cost — backed by SQLite (no new infrastructure), closing the gap
SPEC-011 explicitly named and deferred.

## Scope

- `RateLimiter` interface (`attempt(string $key): bool` — checks and, if allowed, records in
  one call) + `SqliteRateLimiter`, the only implementation.
- A new `rate_limit_hits` table (migration), storing one row per allowed attempt, keyed by an
  arbitrary string (here, the client's IP address).
- Config: `AI_RATE_LIMIT_MAX` (default 20) and `AI_RATE_LIMIT_WINDOW_SECONDS` (default 3600) —
  "N requests per window," a fixed-lookback count (not a token bucket or leaky bucket — simpler,
  sufficient for this traffic scale, and trivially auditable by reading the table).
- `InterpretationController::create()` checks the limit (keyed by `$request->getClientIp()`)
  before doing any other work (repository lookup, context building, calling the provider) and
  responds `429` with a `Retry-After` header if exceeded.
- Applies to **both** providers (`mock` and `gemini`) — one policy, not two — see "Architecture
  decisions" in plan.md for why.

## Out of scope

- **Per-user (vs. per-IP) limiting.** No auth exists anywhere in this app yet (consistently
  deferred since SPEC-006); IP is the only identifying signal available. Revisit once auth
  exists.
- **Trusted-proxy / `X-Forwarded-For` configuration.** Behind the reverse-proxy setups
  `docs/deployment.md` already documents, `Request::getClientIp()` returns the proxy's own
  address unless Symfony's trusted-proxies list is configured for that specific deployment —
  an environment-specific operational concern, not something this feature can solve once for
  every possible deployment. Documented plainly as a known limitation, not silently ignored:
  un-configured, this still caps *total* request volume through the proxy (one shared bucket),
  which is a real, if blunter, mitigation.
- **Row cleanup / TTL for old `rate_limit_hits` rows.** They fall out of the window's *count*
  immediately (the query filters by `created_at`), but the rows themselves persist. At this
  app's traffic scale this is not a practical growth concern; add a cleanup job if it ever
  becomes one.
- **Rate limiting any other endpoint.** Scoped to the one endpoint with real external cost, per
  the plan's own wording. `RateLimiter`/`SqliteRateLimiter` are written generically (any string
  key) specifically so applying this elsewhere later needs no redesign — but nothing else is
  wired to it here.
- **A response body richer than a plain error message** (e.g. structured
  `{retryAfterSeconds: N}`) — the `Retry-After` header already carries that machine-readable
  value per HTTP convention; duplicating it in the body is unnecessary for this pass.

## User behavior

```
POST /api/interpretations/{id}, within the configured limit
  -> unchanged from SPEC-008/011 - 200 with the interpretation, or 404/502 as before

POST /api/interpretations/{id}, over the limit (default: 21st request from the same IP within
an hour)
  -> 429, {"error": "..."} , Retry-After: 3600 header
  -> no repository lookup, no context building, no provider call happens - rejected before any
     of that work
```

## Functional requirements

- **REQ-RATE-001** — `SqliteRateLimiter::attempt($key)` MUST return `true` and record the
  attempt (insert a row) when fewer than `AI_RATE_LIMIT_MAX` rows exist for `$key` with
  `created_at` within the last `AI_RATE_LIMIT_WINDOW_SECONDS`, and MUST return `false` without
  recording anything otherwise.
- **REQ-RATE-002** — `InterpretationController::create()` MUST check the rate limit (keyed by
  `$request->getClientIp()`, falling back to a fixed placeholder key if the IP is unavailable,
  never crashing on a missing IP) before any other work in the method, and MUST respond `429`
  with a `Retry-After` header (set to `AI_RATE_LIMIT_WINDOW_SECONDS`) when exceeded.
- **REQ-RATE-003** — The limit applies identically regardless of which `InterpretationProvider`
  is configured — the check happens before provider selection is even relevant to the request.

## Non-functional requirements

- **REQ-RATE-004** — No new Composer dependency — SQLite (already the project's only database)
  is sufficient; no external rate-limiting service (Redis etc.), consistent with SPEC-001's
  "no Redis" constraint.
- **REQ-RATE-005** — `rate_limit_hits` MUST be created via the existing migration mechanism
  (`php scripts/migrate.php`), matching every other table in this project.

## Data requirements

```sql
CREATE TABLE rate_limit_hits (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    rate_limit_key TEXT NOT NULL,
    created_at TEXT NOT NULL
);

CREATE INDEX idx_rate_limit_hits_key_created_at ON rate_limit_hits(rate_limit_key, created_at);
```

## API requirements

`POST /api/interpretations/{id}` gains the `429` case described above; every other response
shape/status from SPEC-008/010/011 is unchanged.

## Edge cases

- Exactly at the limit (the `AI_RATE_LIMIT_MAX`-th request) → allowed (the check is
  `count >= max`, so the boundary request itself is the last one let through, not the first
  one rejected).
- `AI_RATE_LIMIT_MAX` set to `0` → every request rejected (an operator's way to fully disable
  the endpoint without removing the route) — not special-cased, falls out of `0 >= 0`.
- Requests from two different IPs → independent buckets, one client's usage never affects
  another's.
- `$request->getClientIp()` returns `null` (can happen in unusual server configs) → falls back
  to a fixed key (e.g. `'unknown'`), meaning all such requests share one bucket rather than the
  check crashing or being silently skipped.

## Acceptance criteria

- [x] `SqliteRateLimiter::attempt()` allows exactly `AI_RATE_LIMIT_MAX` calls per key per
      window and rejects the next one, verified against a real (temp, migrated) SQLite database
      — not a fake, since the whole point is the SQL counting logic being correct.
- [x] Two different keys never affect each other's count.
- [x] `InterpretationController` returns `429` with a `Retry-After` header once the limit is
      hit, and does not call the repository or provider when rejecting.
- [x] The limit applies the same way whether `AI_PROVIDER` is `mock` or `gemini` (the check
      happens before provider selection is even relevant).
- [x] `npm run verify` passes end to end.
- [x] Manually verified against the real running API: hitting the endpoint past the configured
      limit returns `429`.

`apps/api/src/AI` gained `RateLimiter`/`SqliteRateLimiter`; `InterpretationController::create()`
now checks the limit (keyed by client IP) before any other work. 6 new tests (82 total in
`apps/api`, 466 assertions). `npm run verify` passes end to end. Manually verified against the
real running dev server: 20 real requests (default limit) all `200`, the 21st and 22nd both
`429` with `Retry-After: 3600` and the documented error body — exactly as designed, not just
passing in isolation.
