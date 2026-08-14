# Plan — Gemini Interpretation Provider (SPEC-011)

**Depends on spec status:** `approved`

## Technical approach

```
apps/api/src/AI/
├── InterpretationContext.php           + defaultSourceReferences() (moved from Mock)
├── InterpretationProviderException.php  new: shared failure type for any provider
├── GeminiClient.php                     new: interface, generateJson(prompt, schema): array
├── HttpGeminiClient.php                 new: real implementation (file_get_contents)
├── GeminiInterpretationProvider.php     new: implements InterpretationProvider
├── MockInterpretationProvider.php        refactored: uses context's defaultSourceReferences()
└── InterpretationController.php          + provider selection from Config

apps/api/src/Core/
└── Kernel.php                            + try/catch around handle()
```

- **API contract** (confirmed against Google's current primary API reference,
  `ai.google.dev/api`, and cross-checked across 3 independent fetches — not from memory, since
  Gemini's API surface has changed since this session's training data): `POST
  https://generativelanguage.googleapis.com/v1beta/interactions`, header `x-goog-api-key:
  {key}`, body `{"model": "...", "input": "...", "response_format": {"type": "text",
  "mime_type": "application/json", "schema": {...standard JSON Schema, lowercase types...}}}`,
  response `{"output_text": "...json string..."}`. This (not the older `generateContent`
  endpoint) is Google's actively-recommended current endpoint.
- `GeminiClient` interface: `generateJson(string $prompt, array $schema): array` — returns the
  already-`json_decode`d structured response. Keeps `GeminiInterpretationProvider` free of any
  HTTP/JSON-transport detail, and makes it fully testable with a fake.
- `HttpGeminiClient implements GeminiClient`: builds the request, POSTs via
  `file_get_contents()` + `stream_context_create()` (method, headers, body, a 30s timeout,
  `ignore_errors => true` so a non-2xx response body is still readable for a useful error
  message instead of just `false`), reads the HTTP status from `$http_response_header`, and
  throws `InterpretationProviderException` for any transport failure, non-2xx status, or
  missing/non-string `output_text`. The API key is interpolated only into the request header,
  never into any exception message.
- `GeminiInterpretationProvider::interpret()`: builds a plain-text prompt from the context
  (question, primary hexagram identity + judgment + image, changing lines' statements or "no
  changing lines," resulting hexagram identity + judgment when applicable, existing notes),
  requests a JSON Schema covering every `Interpretation` field except `sourceReferences`,
  validates the required fields are present non-empty strings (REQ-GEM-003), and returns an
  `Interpretation` with `sourceReferences: $context->defaultSourceReferences()` always,
  regardless of what (if anything) the model returned for it.
- `InterpretationContext::defaultSourceReferences(): list<string>` — the exact logic
  `MockInterpretationProvider` already had privately, moved onto the context and made public,
  since "what canonical text is honestly in scope for this reading" is a property of the
  context's data, not something either provider should independently reimplement.
  `MockInterpretationProvider` is updated to call it instead of its own private method — same
  output, one implementation.
- `InterpretationController`: constructor reads `Config::string('ai_provider')` (default via
  `Config::fromEnv()`'s own `?? 'mock'` fallback, matching every other env var's pattern already
  in that class) and either constructs `MockInterpretationProvider` or validates `ai_api_key` is
  non-empty and constructs `GeminiInterpretationProvider(new HttpGeminiClient(...))`; an unknown
  provider name or a missing key for `gemini` throws immediately, in the constructor — a
  deployment misconfiguration, not a per-request condition.
- `create()` wraps `$this->provider->interpret($context)` in a
  `try { } catch (InterpretationProviderException $e) { return 502 with $e->getMessage() }` —
  the one new case added to this controller; everything else (404, the happy path) is
  unchanged from SPEC-008.
- `Kernel::handle()`: the existing `match` over `$routeInfo[0]` moves inside a `try` block; a
  `catch (\Throwable $e)` logs `$e` via `error_log()` and returns a generic `500` `JsonResponse`
  — the only behavioral change for every *other* endpoint is that a bug that previously crashed
  with a raw PHP error now returns clean JSON instead; nothing about the happy path changes.

## Architecture decisions

- **`sourceReferences` is never LLM-generated**, for any provider. This is the one field
  SPEC-008's REQ-AI-004 requires to be exactly correct — a real LLM's freely-generated citation
  list could hallucinate references to text that wasn't actually provided, which is precisely
  the failure mode the plan's "AI must never be a source of truth for classical text" principle
  exists to prevent. Computing it in code, once, shared by every provider (via
  `InterpretationContext::defaultSourceReferences()`), makes REQ-AI-004 true by construction
  instead of by hoping the model behaves.
- **`generateContent`-family endpoint chosen over the newer "Interactions API" only after
  research, not by default-to-familiar.** Google's own reference page states the Interactions
  API is now recommended for "agentic workflows, server-side state management, and complex
  multi-modal, multi-turn conversations" — none of which this use case needs (each
  interpretation request is a single, stateless, single-turn generation). The same reference
  describes standard `generateContent`-style calls as best for exactly this "non-interactive
  task" shape. Combined with getting a consistent, corroborated contract across independent
  fetches for the endpoint this plan actually uses, this was a reasoned fit-for-purpose choice,
  not inertia.
- **No new Composer dependency for the HTTP call.** `ext-openssl` is already required
  (`composer.json`); PHP's stream-wrapper HTTPS support needs nothing more. Matches this
  project's established pattern (SPEC-005's hand-rolled UUIDv4) of not reaching for a package
  when a small, well-scoped piece of code covers the need.
- **Kernel-level catch-all belongs in this spec, not a separate one.** Every controller before
  this one only ever failed in ways its own code controlled (a domain exception, a 404). This
  spec introduces the app's first call to something genuinely outside its control — a network
  request to a third party that can fail in new ways (DNS, TLS, timeout, an unexpected response
  shape). Catching `InterpretationProviderException` locally in `InterpretationController`
  handles the *expected* failure modes; the Kernel-level net is what keeps an *unexpected* one
  (a bug in this new code, or anywhere else) from leaking a stack trace to the client — a
  materially higher-probability event now than it was when every dependency was local and pure.
- **Fail loud, not soft, on misconfiguration.** `AI_PROVIDER=gemini` with no key throws at
  construction rather than silently using the mock provider. A silent fallback would make a
  real deployment problem invisible — the operator would see "interpretations work" and never
  notice they're all mock output.

## Affected areas

- `apps/api/src/AI/InterpretationContext.php` (+ `defaultSourceReferences()`)
- `apps/api/src/AI/InterpretationProviderException.php` (new)
- `apps/api/src/AI/GeminiClient.php` (new)
- `apps/api/src/AI/HttpGeminiClient.php` (new)
- `apps/api/src/AI/GeminiInterpretationProvider.php` (new)
- `apps/api/src/AI/MockInterpretationProvider.php` (refactor)
- `apps/api/src/AI/InterpretationController.php` (provider selection, 502 handling)
- `apps/api/src/Core/Config.php` (+ `ai_provider`/`ai_api_key`/`ai_model`)
- `apps/api/src/Core/Kernel.php` (+ catch-all)
- `apps/api/.env.example` (+ `AI_PROVIDER`/`AI_API_KEY`/`AI_MODEL`, documented)
- `apps/api/tests/AI/**` (new tests + updates)
- `apps/api/tests/Core/**` (Kernel catch-all test)
- `docs/deployment.md` (brief note on setting the Gemini key in production)

## Data / schema changes

None.

## Risks / open questions

- **This session cannot obtain or verify a real `AI_API_KEY`.** Everything is built and tested
  against a fake `GeminiClient`; the actual live HTTP contract with Google's API is verified by
  research (3 corroborating fetches), not by a real call. If Google's actual behavior differs
  in some detail this research didn't surface, the first live call will fail with a `502` whose
  message should make the mismatch diagnosable (REQ-GEM-007's descriptive-error requirement
  exists partly for this reason) — but it is a real, named risk, not a guarantee.
- **Model name churn.** Gemini model availability changes on a timescale of months. `AI_MODEL`
  is fully configurable specifically because of this; the shipped default is a best-effort
  based on research at implementation time, documented in `.env.example` as something to verify
  against `https://ai.google.dev/gemini-api/docs/models` rather than trusted blindly.
- **Rate limiting** is named explicitly as deferred (see spec.md "Out of scope"), with an
  interim mitigation (provider-side usage caps) that requires no code from this project.
