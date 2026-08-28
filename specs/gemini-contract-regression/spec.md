# SPEC-040 — Gemini API Contract Regression Test

**Status:** implemented
**Owner:** unassigned
**Last updated:** 2026-08-28

## Problem

`HttpGeminiClient` — the only real `GeminiClient` implementation, the code that builds the
actual HTTPS request to Google's Generative Language API and parses the actual response — has
**zero automated tests**. Everything above it (`GeminiInterpretationProvider`, the retry loop,
the language check, the controller) is thoroughly covered via `FakeGeminiClient`, but the wire
contract itself is verified only by SPEC-011's one-time manual `curl` / live-key session.

SPEC-011's history shows why this matters: the *first* implementation targeted
`POST /v1beta/interactions` (built from documentation, never actually called) and was completely
wrong — wrong endpoint, wrong request body, wrong nullable-field syntax — and none of that was
caught until a human ran a real call weeks later. The corrected contract
(`POST /v1beta/models/{model}:generateContent`, `contents[].parts[].text`,
`generationConfig.responseSchema`, `generationConfig.responseMimeType`, response text at
`candidates[0].content.parts[0].text`, Gemini's `nullable: true` schema dialect) now lives only
as prose in that spec and as untested code here. A refactor, a "cleanup", or a dependency bump
could silently break the request shape and `npm run verify` would stay green.

## Purpose

Pin `HttpGeminiClient`'s request-building and response-parsing contract with fast, offline
regression tests driven by a recorded real-shape response fixture — so an accidental change to
the endpoint, request body, headers, or response-extraction path fails a test instead of
failing silently in production.

## Scope

### Minimal test seam (no behaviour change)
- New interface `App\AI\HttpTransport` with one method:
  `post(string $url, string $body, array $headers): array` returning
  `array{status: int, body: string}`.
- New `App\AI\StreamHttpTransport implements HttpTransport` — the existing
  `stream_context_create` + `@file_get_contents` + `$http_response_header` status-line parsing,
  moved verbatim out of `HttpGeminiClient::generateJson()`. Still no HTTP-client dependency;
  still uses the built-in stream wrapper. The "could not reach the API" failure
  (`file_get_contents` returns `false`) is raised here, unchanged.
- `HttpGeminiClient::__construct()` gains a third parameter `?HttpTransport $transport = null`
  and does `$this->transport = $transport ?? new StreamHttpTransport()` (the codebase's existing
  `?? new …` fallback idiom). The single production call site (`InterpretationController::250`)
  passes two args and is unchanged; production wiring uses the real transport exactly as before.
- `HttpGeminiClient::generateJson()` keeps every other responsibility it has today: building
  the request body, choosing the endpoint URL, setting the two headers, mapping non-2xx to
  `InterpretationProviderException`, extracting `candidates[0].content.parts[0].text`,
  `json_decode`-ing that inner text, and validating it decoded to an array.

### The fixture
- `apps/api/tests/AI/fixtures/gemini-generate-content-response.json` — a realistic, complete
  `:generateContent` success body: `candidates[0].content.parts[0].text` holding a JSON string
  that matches `GeminiInterpretationProvider::RESPONSE_SCHEMA`, plus `role`, `finishReason`,
  `usageMetadata`, `modelVersion` as a real response carries them. Sourced from the shape
  documented in SPEC-011's 2026-08-21 live-verification note (no secret, no real user data).

### The tests (`apps/api/tests/AI/HttpGeminiClientTest.php`)
- **Request URL** — `generateJson()` calls the transport with
  `https://generativelanguage.googleapis.com/v1beta/models/{model}:generateContent`, the
  `{model}` being the exact constructor value, no trailing slash, `:generateContent` (colon,
  not `/`) suffix.
- **Request headers** — `Content-Type: application/json` and `x-goog-api-key: {apiKey}` are
  both passed; the key is in the header, never in the URL query string.
- **Request body** — decodes to `contents[0].parts[0].text === $prompt`,
  `generationConfig.responseMimeType === 'application/json'`,
  `generationConfig.responseSchema === $schema` (the array passed in), and carries no other
  top-level keys.
- **Happy path parse** — given the fixture body + status 200, returns the inner decoded object
  (the seven interpretation fields), i.e. it unwraps `candidates[0].content.parts[0].text` and
  re-decodes the JSON string inside it.
- **Non-2xx** — status 400 with an error body → `InterpretationProviderException` whose message
  includes the status code and (truncated) body.
- **Malformed envelope** — status 200 but no `candidates[0].content.parts[0].text` →
  `InterpretationProviderException` naming the missing field.
- **Inner text not JSON** — status 200, `parts[0].text` present but not valid JSON →
  `InterpretationProviderException`.
- **Transport-level unreachable** — a transport that throws
  `InterpretationProviderException('Could not reach the Gemini API.')` propagates unchanged.
- One end-to-end assertion that `GeminiInterpretationProvider` + `HttpGeminiClient` +
  `FakeHttpTransport(fixture)` produces a fully-populated `Interpretation` — proving the two
  real classes compose over the recorded bytes.

### Support
- `apps/api/tests/AI/Support/FakeHttpTransport.php` — records the last `post()` call
  (`url`, `body`, `headers`) and returns a constructor-supplied `{status, body}`, or throws a
  constructor-supplied exception.

## Out of scope

- **Any live network call.** The tests are fully offline; the live-key check stays a manual
  step noted in SPEC-011.
- **Changing the request or response contract.** This spec only *locks* the current one.
- **`GeminiInterpretationProvider` behaviour** — already covered; not retouched beyond the one
  compose-over-fixture assertion.
- **Retry/backoff, streaming responses, `usageMetadata` assertions, multi-candidate handling.**
  Not part of today's contract.
- **A second `HttpTransport` implementation** (cURL etc.). The interface exists purely as a
  test seam.

## Functional requirements

- **REQ-GEMCT-001** — `HttpGeminiClient` performs its network I/O through an injected
  `HttpTransport`, defaulting to `StreamHttpTransport`; the production call site is unchanged.
- **REQ-GEMCT-002** — A test asserts the request URL is
  `…/v1beta/models/{model}:generateContent` with the exact constructor model and no key in the
  query string.
- **REQ-GEMCT-003** — A test asserts both required headers are sent, including
  `x-goog-api-key`.
- **REQ-GEMCT-004** — A test asserts the request body shape:
  `contents[0].parts[0].text`, `generationConfig.responseMimeType`,
  `generationConfig.responseSchema`.
- **REQ-GEMCT-005** — A test drives the client with the recorded fixture and asserts the
  unwrapped/re-decoded inner object is returned.
- **REQ-GEMCT-006** — Tests cover non-2xx, missing-envelope-field, and inner-text-not-JSON, each
  raising `InterpretationProviderException`.
- **REQ-GEMCT-007** — A test proves `GeminiInterpretationProvider` composed over
  `HttpGeminiClient` + the fixture yields a complete `Interpretation`.

## Non-functional requirements

- **REQ-GEMCT-020** — No behaviour change: identical bytes on the wire and identical exceptions
  as before, for every path. `StreamHttpTransport` is a verbatim move.
- **REQ-GEMCT-021** — Tests run offline, in the existing PHPUnit suite, with no new Composer
  dependency.
- **REQ-GEMCT-022** — `phpstan` level 8 and `php-cs-fixer` stay clean; `npm run verify` passes.

## Data requirements

None persisted. One checked-in JSON fixture file under `tests/`.

## API requirements

No public HTTP API change. Internal: the new `HttpTransport` interface.

## Edge cases

- `file_get_contents` returns `false` (DNS failure, refused connection) → `StreamHttpTransport`
  throws `InterpretationProviderException('Could not reach the Gemini API.')`, exactly as the
  inline code does today.
- Status line absent from `$http_response_header` → `statusCodeFrom()` returns `0` → treated as
  non-2xx, same as today.
- Fixture `parts[0].text` contains an empty string → existing `extractText()` returns `null` →
  `InterpretationProviderException` (covered by the malformed-envelope test with an empty-text
  variant).

## Acceptance criteria

- [x] `HttpGeminiClient` takes an optional `HttpTransport` (nullable 3rd ctor arg; falls back
      to `new StreamHttpTransport()` when null); `InterpretationController:250` still constructs
      it with two arguments.
- [x] `StreamHttpTransport` contains the moved stream code — `stream_context_create` +
      `@file_get_contents` + `$http_response_header` status-line regex + the `false` → "Could
      not reach the Gemini API." guard — a verbatim move; `composer test` stayed green with no
      test changes at that step (301-test run before adding the new file).
- [x] `HttpGeminiClientTest` (10 tests, 41 assertions): endpoint URL + no key in URL; both
      headers; request-body shape (`contents[0].parts[0].text`, `responseMimeType`,
      `responseSchema`, exact top-level keys); fixture happy-path unwrap+re-decode; non-2xx with
      code+body; missing envelope text; empty candidate text; inner text not JSON;
      transport-unreachable propagation; `GeminiInterpretationProvider` composed over the real
      client + fixture yields a full `Interpretation` with the context's own `sourceReferences`.
- [x] Fixture `tests/AI/fixtures/gemini-generate-content-response.json` is a full
      `:generateContent` body (role/finishReason/safetyRatings/usageMetadata/modelVersion) with
      the seven-field interpretation JSON nested as a string in
      `candidates[0].content.parts[0].text`.
- [x] `cd apps/api && composer test` green (301 tests); `composer stan` + `composer lint` clean.
- [x] `npm run verify` passes end to end.
