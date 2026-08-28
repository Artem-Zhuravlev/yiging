# Plan — Gemini API Contract Regression Test (SPEC-040)

## Approach

Introduce one interface as a test seam (`HttpTransport`), move the impure network step behind
it verbatim, then write offline regression tests over a recorded response fixture. Mirrors this
codebase's established pattern: real code depends on a small interface, tests supply a `Fake*`
(`GeminiClient`/`FakeGeminiClient`, `CoinTosser`/`FakeCoinTosser`).

## Affected files

### New
- `apps/api/src/AI/HttpTransport.php` — interface, one method
  `post(string $url, string $body, array $headers): array` → `array{status:int, body:string}`.
- `apps/api/src/AI/StreamHttpTransport.php` — the `stream_context_create` +
  `@file_get_contents(...)` + `statusCodeFrom($http_response_header)` block, moved out of
  `HttpGeminiClient` unchanged. Throws `InterpretationProviderException('Could not reach the
  Gemini API.')` when `file_get_contents` returns `false`. Keeps the `TIMEOUT_SECONDS = 30` and
  the `HTTP/... 3-digit` status-line regex.
- `apps/api/tests/AI/Support/FakeHttpTransport.php` — `public ?array $lastCall`
  (`{url, body, headers}`); constructor `(int $status, string $body)` or
  `(InterpretationProviderException $throw)`.
- `apps/api/tests/AI/fixtures/gemini-generate-content-response.json` — full `:generateContent`
  success envelope; `candidates[0].content.parts[0].text` is a JSON **string** matching
  `RESPONSE_SCHEMA` (all seven fields, non-null `changingLineMeaning`/`transition`,
  one `uncertainties` entry). Include `role: "model"`, `finishReason: "STOP"`, `usageMetadata`,
  `modelVersion`.
- `apps/api/tests/AI/HttpGeminiClientTest.php` — the regression tests.

### Changed
- `apps/api/src/AI/HttpGeminiClient.php`:
  - constructor gains `private readonly HttpTransport $transport = new StreamHttpTransport()`.
  - `generateJson()` builds `$requestBody` and `$headers` (as an assoc array now, not a
    pre-joined string — `StreamHttpTransport` does the `implode("\r\n", …)`), calls
    `$this->transport->post($this->endpoint(), $requestBody, $headers)`, then runs the existing
    status check / `extractText` / inner-`json_decode` / array-check logic on the returned
    `{status, body}`.
  - add `private function endpoint(): string` = `sprintf(self::ENDPOINT_TEMPLATE, $this->model)`.
  - `statusCodeFrom()` and the `$http_response_header` handling move to `StreamHttpTransport`;
    `extractText()`, `truncate()` stay.
  - class stays `final`.

### Untouched
- `InterpretationController.php` — still `new HttpGeminiClient($apiKey, $config->string('ai_model'))`.
- `GeminiInterpretationProvider.php`, `GeminiClient.php`, `FakeGeminiClient.php`,
  `GeminiInterpretationProviderTest.php`.

## Sequence

1. Add `HttpTransport` + `StreamHttpTransport` (verbatim move). Run `composer test` — nothing
   should change (no test exercises this path yet), `phpstan`/`cs-fixer` clean.
2. Rewire `HttpGeminiClient` to the transport with the default arg. `composer test` again —
   still green, `InterpretationControllerTest` unaffected.
3. Add `FakeHttpTransport` + the fixture JSON.
4. Write `HttpGeminiClientTest` (8 request/parse tests + 1 compose-with-provider test).
5. `composer test` / `composer stan` / `composer cs` in `apps/api`; then `npm run verify` from
   root.

## Testing notes

- Request-body assertions: `json_decode($transport->lastCall['body'], true)` then
  `assertSame($prompt, $body['contents'][0]['parts'][0]['text'])`,
  `assertSame('application/json', $body['generationConfig']['responseMimeType'])`,
  `assertSame($schema, $body['generationConfig']['responseSchema'])`,
  `assertSame(['contents','generationConfig'], array_keys($body))`.
- URL assertion: `assertSame('https://…/v1beta/models/gemini-3.6-flash:generateContent',
  $transport->lastCall['url'])`; `assertStringNotContainsString('key=', $url)`.
- Header assertion: normalize `lastCall['headers']` to a map;
  `assertSame('application/json', $h['Content-Type'])`,
  `assertSame('test-key-123', $h['x-goog-api-key'])`.
- Fixture load: `file_get_contents(__DIR__.'/fixtures/gemini-generate-content-response.json')`.
- Compose test: `new GeminiInterpretationProvider(new HttpGeminiClient('k','m', new
  FakeHttpTransport(200, $fixture)))->interpret(context, General, default, English)` →
  assert every `Interpretation` field is populated and `sourceReferences` is the context's own.

## Verification note (2026-08-28)

- `HttpTransport` + `StreamHttpTransport` added; `HttpGeminiClient` rewired. At that point
  `composer test` was still **291 tests, 1056 assertions, OK**, `composer stan` "No errors",
  `composer lint` "0 of 95 files" — the move is inert.
- Added `FakeHttpTransport`, the fixture, and `HttpGeminiClientTest` (10 tests, 41 assertions).
  Full `apps/api` suite now **301 tests, 1097 assertions, OK**; `stan` clean; `lint` clean
  (97 files).
- `StreamHttpTransport` is a verbatim lift of the old inline block: same `stream_context_create`
  options (`method`/`header`/`content`/`timeout`/`ignore_errors`), same `TIMEOUT_SECONDS = 30`,
  same `#^HTTP/\S+\s+(\d{3})#` status-line regex, same `false` →
  `InterpretationProviderException('Could not reach the Gemini API.')`. The only change is that
  headers arrive as an assoc array and are `implode`d here instead of by the caller.
- `npm run verify` — all steps pass (web lint/typecheck/test/build; api lint/stan/phpunit;
  yijing-core lint/stan/phpunit).

Not covered (unchanged from SPEC-011): a real live-key call. Still a manual step.
