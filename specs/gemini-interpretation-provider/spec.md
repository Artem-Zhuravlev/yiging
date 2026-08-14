# SPEC-011 — Gemini Interpretation Provider

**Status:** verified (code); live Gemini call unverified — see final acceptance criterion
**Owner:** unassigned
**Last updated:** 2026-08-14

## Problem

SPEC-008 built the whole `InterpretationProvider` boundary specifically so a real AI provider
would be a drop-in addition, not a redesign — but only `MockInterpretationProvider` exists.
Every consultation's "interpretation" is currently a mechanical echo of its own canonical text,
not a real AI-generated reading.

## Purpose

Add `GeminiInterpretationProvider`, a real `InterpretationProvider` backed by Google's Gemini
API, selectable via config alongside the existing mock — fulfilling SPEC-008's explicitly
deferred "real provider" scope now that the user has asked for it.

## Scope

- `GeminiClient` interface (`generateJson(prompt, schema): array`) + `HttpGeminiClient`, the
  real implementation — POSTs to Gemini's `generateContent`-family REST API using PHP's
  built-in `file_get_contents` + stream context (no new Composer dependency: `ext-openssl` is
  already required, matching the project's existing "hand-roll it, don't add a package"
  precedent from SPEC-005's UUID generator).
- `GeminiInterpretationProvider implements InterpretationProvider`: builds a prompt grounded
  strictly in the `InterpretationContext`'s real canonical text, requests structured JSON
  output matching `Interpretation`'s shape, and maps the response — **except**
  `sourceReferences`, which is never LLM-generated (see "Architecture decisions" in plan.md for
  why) but computed the same deterministic way for every provider.
- `InterpretationContext::defaultSourceReferences()`: the citation-list computation, moved out
  of `MockInterpretationProvider` (which used it privately) so both providers share exactly one
  implementation — this is what keeps REQ-AI-004 ("cite exactly what was used, nothing
  invented") true regardless of which provider answered.
- Config: `AI_PROVIDER` (`mock` default, or `gemini`), `AI_API_KEY`, `AI_MODEL` — the exact
  three variables the original plan's config section already named. `InterpretationController`
  selects the provider at construction time; misconfiguration (`gemini` selected, empty key)
  fails loudly with a clear message, not a silent fallback to mock.
- A Kernel-level safety net: any uncaught exception during request handling now becomes a
  clean `500 {"error": "Internal Server Error"}` instead of a raw PHP stack trace reaching the
  client — see "Architecture decisions" for why this belongs in this spec specifically.

## Out of scope

- **Rate limiting.** A real cost-bearing endpoint with no caller-side throttling is a genuine
  gap the plan itself flags (section 31) — explicitly not solved here. Implementing it well
  needs its own design (storage for request counts across PHP-FPM workers, a windowing
  strategy) that deserves its own spec, not a bolt-on here. **Interim mitigation: set a usage
  cap/budget alert in the Google AI Studio / Cloud Console for this API key** — a real,
  available safeguard that requires no code.
- **Frontend provider indicator** (e.g. showing "via Gemini" vs "via Mock" in the UI). The
  `Interpretation` JSON shape is identical regardless of provider (`sourceReferences`/
  `uncertainties` already carry whatever honesty each provider provides); no UI change is
  needed for this spec to be complete.
- **Streaming responses.** `POST /api/interpretations/{id}` stays a single request/response;
  Gemini's response is awaited in full before answering. Streaming would change the HTTP
  contract (SPEC-008/010) for a UX improvement nobody has asked for yet.
- **Persisting which provider produced an interpretation.** Matches SPEC-008's existing
  "interpretations aren't persisted at all" stance — unchanged here.
- **Retrying a failed Gemini call automatically.** A failure surfaces as a clear `502` (see
  below); the user re-clicking "Get Interpretation" (SPEC-010) is the retry mechanism, not
  hidden server-side retry logic that could multiply cost on a flaky connection.

## User behavior

```
apps/api/.env: AI_PROVIDER=gemini, AI_API_KEY=<a real key>, AI_MODEL=<a current Gemini model>

POST /api/interpretations/{consultationId}
  -> 200, the same Interpretation JSON shape SPEC-008 already defined, except summary/
     coreTheme/situation/changingLineMeaning/transition/practicalReflection/uncertainties are
     now genuinely AI-generated (grounded in the consultation's real canonical text), and
     sourceReferences is computed the same deterministic way it always was

AI_PROVIDER=gemini but AI_API_KEY is empty
  -> the API fails to start serving interpretation requests at all (constructor-time error,
     not a per-request one) — a clear signal this is a deployment misconfiguration, not a
     transient failure

Gemini API unreachable, key rejected, or malformed response
  -> 502, {"error": "..."} with a message identifying the failure - never a raw stack trace,
     never silently falling back to the mock provider (that would misrepresent a real failure
     as a successful interpretation)
```

## Functional requirements

- **REQ-GEM-001** — `GeminiInterpretationProvider::interpret()` MUST send a prompt built
  entirely from the given `InterpretationContext`'s own fields (question, primary/resulting
  hexagram identity + real judgment/image text, changing line statements, existing notes) —
  MUST NOT reference any hexagram data not present in the context.
- **REQ-GEM-002** — The request to Gemini MUST specify structured JSON output matching
  `Interpretation`'s field set (excluding `sourceReferences`, which this spec computes, never
  requests from the model).
- **REQ-GEM-003** — The response mapping MUST validate that `summary`, `coreTheme`,
  `situation`, and `practicalReflection` are present, non-empty strings, and that
  `uncertainties` is an array — throwing `InterpretationProviderException` (never silently
  substituting empty strings) if the response is malformed.
- **REQ-GEM-004** — `changingLineMeaning`/`transition` MUST be `null` when absent or
  non-string in the response (matching a no-changing-lines reading), never coerced to an empty
  string.
- **REQ-GEM-005** — `sourceReferences` MUST always be `$context->defaultSourceReferences()` —
  never taken from the model's response, even if the model happened to include one.
- **REQ-GEM-006** — `InterpretationController` MUST select the provider from `Config`
  (`AI_PROVIDER`: `mock` if unset/`mock`, `gemini` if set to `gemini`) and MUST throw a clear
  configuration error at construction time if `gemini` is selected with an empty `AI_API_KEY`
  — never silently falling back to `mock`.
- **REQ-GEM-007** — A `GeminiClient` failure (network error, non-2xx response, malformed/
  missing expected fields in the raw HTTP response) MUST surface as
  `InterpretationProviderException`, which `InterpretationController` MUST catch and map to
  `502` with a descriptive (but secret-free — never includes the API key) error message.

## Non-functional requirements

- **REQ-GEM-008** — No new Composer dependency for the HTTP call — `HttpGeminiClient` uses
  PHP's built-in `file_get_contents`/stream context (curl is available in this environment but
  not declared as a required extension in `composer.json`; streams only need `ext-openssl`,
  already required).
- **REQ-GEM-009** — `GeminiInterpretationProvider` MUST depend only on the `GeminiClient`
  interface, never construct `HttpGeminiClient` itself — so tests inject a fake client and
  never make a real network call.
- **REQ-GEM-010** — The Gemini API key MUST only ever live in `apps/api/.env` (backend-only,
  per `docs/coding-rules.md`'s "API key only backend" — already true structurally, since
  nothing in `apps/web` touches AI config) and MUST NOT appear in any error message returned to
  the client.
- **REQ-GEM-011** — `Kernel::handle()` MUST catch any uncaught `\Throwable` from routing,
  controller construction, or handling, log it server-side, and return a generic
  `500 {"error": "Internal Server Error"}` — protecting the whole app, not just this endpoint,
  now that a real external-network dependency (a new class of failure: DNS, TLS, timeout) exists
  for the first time in this codebase.

## Data requirements

None — no new persistence.

## API requirements

`POST /api/interpretations/{id}`'s contract is unchanged from SPEC-008/010 — same shape,
same status codes, plus the new `502` case (only reachable when `AI_PROVIDER=gemini`).

## Edge cases

- Consultation with no changing lines → prompt explicitly states this; `changingLineMeaning`/
  `transition` expected `null` in the response, validated per REQ-GEM-004.
- Gemini responds with valid JSON but missing a required field (e.g. `summary` absent) →
  `InterpretationProviderException`, `502` — never a `200` with a blank/missing field baked
  into the client-facing `Interpretation`.
- `AI_MODEL` names a retired/nonexistent model → Gemini's own API returns an error response;
  surfaces as `502` via the same `GeminiClient` failure path, with the underlying message
  preserved (helps diagnose "wrong model name" specifically, without needing new code for it).
- `AI_PROVIDER` set to something other than `mock`/`gemini` (typo) → constructor-time error,
  same as the missing-key case — a config problem, not a per-request one.

## Acceptance criteria

- [x] `GeminiInterpretationProvider` builds a context-grounded prompt and maps a well-formed
      structured response to `Interpretation` correctly — verified with a fake `GeminiClient`
      (no real network call in tests).
- [x] `sourceReferences` is always `$context->defaultSourceReferences()`, verified even when
      a fake client's response includes a different `sourceReferences` value (proving it's
      ignored, not just usually-absent).
- [x] A malformed/incomplete fake response throws `InterpretationProviderException`.
- [x] `changingLineMeaning`/`transition` map to `null`, not `""`, when absent.
- [x] `InterpretationController` throws a clear error at construction when `AI_PROVIDER=gemini`
      with an empty `AI_API_KEY`.
- [x] A `GeminiClient` failure maps to `502` with a message that never contains the API key.
- [x] `Kernel::handle()` converts an uncaught exception (simulated in a test) into a clean
      `500` JSON response, not a raw error/stack trace.
- [x] `MockInterpretationProvider` still passes all its existing SPEC-008 tests unchanged after
      the `defaultSourceReferences()` refactor.
- [x] `npm run verify` passes end to end.
- [ ] **Live verification against the real Gemini API is the user's to perform** (needs a real
      `AI_API_KEY` this session cannot obtain or safely handle) — everything else on this list
      is verified without one; this item is explicitly not blocking "done" for the code itself.

`apps/api/src/AI` gained `GeminiClient`/`HttpGeminiClient`/`GeminiInterpretationProvider`/
`InterpretationProviderException`; `InterpretationContext::defaultSourceReferences()` now
backs both providers; `InterpretationController` selects the provider from `Config` and maps
provider failures to `502`; `Kernel::handle()` gained a catch-all for uncaught exceptions.
11 new tests (76 total in `apps/api`, 448 assertions; `yijing-core`'s 51 tests/1530 assertions
unaffected). `npm run verify` passes end to end. The default (`mock`) provider was
smoke-tested against the live dev server post-refactor to confirm no regression.

**What was and wasn't verified live:** the exact Gemini API contract (`POST
/v1beta/interactions`, `x-goog-api-key` header, `response_format.schema`, `output_text`
response field) was corroborated across 3 independent research fetches against Google's
current documentation, not from training-data memory — Gemini's API surface has changed since
then. It was **not** exercised against a real API key from this session, since none was
available and API keys are not something this assistant handles directly (see the `.env.example`
setup steps and `docs/deployment.md`). If Google's actual behavior differs from what the
research surfaced, the first live call will fail with a `502` carrying Gemini's own error
message (REQ-GEM-007), which should make any mismatch directly diagnosable.
