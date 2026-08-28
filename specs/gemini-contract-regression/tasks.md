# Tasks — Gemini API Contract Regression Test (SPEC-040)

## Test seam

- [x] **TASK-GEMCT-001** — `src/AI/HttpTransport.php` interface:
      `post(string $url, string $body, array $headers): array` → `array{status:int, body:string}`,
      docblock naming the `InterpretationProviderException` on unreachable host. → REQ-GEMCT-001
- [x] **TASK-GEMCT-002** — `src/AI/StreamHttpTransport.php`: move `stream_context_create` +
      `@file_get_contents` + `statusCodeFrom($http_response_header)` + the `false` →
      `InterpretationProviderException('Could not reach the Gemini API.')` guard out of
      `HttpGeminiClient`, verbatim. Keep `TIMEOUT_SECONDS`, the status-line regex.
      → REQ-GEMCT-001, REQ-GEMCT-020
- [x] **TASK-GEMCT-003** — `HttpGeminiClient`: constructor `?HttpTransport $transport = null`
      with `$this->transport = $transport ?? new StreamHttpTransport()`; `generateJson()` builds
      body + `$headers` map and calls `$this->transport->post($this->endpoint(), $body,
      $headers)`; add `private endpoint()`; keep status-check / `extractText` / inner-decode /
      array-check; class stays `final`. → REQ-GEMCT-001, REQ-GEMCT-020
- [x] **TASK-GEMCT-004** — `composer test` + `composer stan` + `composer lint` in `apps/api` all
      green with no test changes yet (proves the move is inert; 291→291). → REQ-GEMCT-020,
      REQ-GEMCT-022

## Fixture & support

- [x] **TASK-GEMCT-005** — `tests/AI/fixtures/gemini-generate-content-response.json`: full
      `:generateContent` envelope, `candidates[0].content.parts[0].text` = JSON string matching
      `RESPONSE_SCHEMA` (7 fields, non-null nullable pair, 1 uncertainty), plus `role`,
      `finishReason`, `usageMetadata`, `modelVersion`. → REQ-GEMCT-005
- [x] **TASK-GEMCT-006** — `tests/AI/Support/FakeHttpTransport.php`: records `lastCall`
      `{url, body, headers}`; returns `{status, body}` or throws a supplied exception.
      → REQ-GEMCT-002..007

## Regression tests (`tests/AI/HttpGeminiClientTest.php`)

- [x] **TASK-GEMCT-007** — request URL is `…/v1beta/models/{model}:generateContent`, exact
      constructor model, no `key=` in the URL. → REQ-GEMCT-002
- [x] **TASK-GEMCT-008** — headers include `Content-Type: application/json` and
      `x-goog-api-key: {apiKey}`. → REQ-GEMCT-003
- [x] **TASK-GEMCT-009** — request body: `contents[0].parts[0].text === $prompt`,
      `generationConfig.responseMimeType === 'application/json'`,
      `generationConfig.responseSchema === $schema`, top-level keys exactly
      `['contents','generationConfig']`. → REQ-GEMCT-004
- [x] **TASK-GEMCT-010** — fixture + status 200 → returns the unwrapped, re-decoded inner
      object (7 interpretation fields). → REQ-GEMCT-005
- [x] **TASK-GEMCT-011** — status 400 + error body → `InterpretationProviderException` with the
      code and truncated body in the message. → REQ-GEMCT-006
- [x] **TASK-GEMCT-012** — status 200, no `candidates[0].content.parts[0].text` (and an
      empty-string variant) → `InterpretationProviderException` naming the field. → REQ-GEMCT-006
- [x] **TASK-GEMCT-013** — status 200, `parts[0].text` not valid JSON →
      `InterpretationProviderException`. → REQ-GEMCT-006
- [x] **TASK-GEMCT-014** — transport throws `InterpretationProviderException('Could not reach
      the Gemini API.')` → propagates unchanged. → REQ-GEMCT-006
- [x] **TASK-GEMCT-015** — `GeminiInterpretationProvider` + `HttpGeminiClient` +
      `FakeHttpTransport(200, fixture)` → complete `Interpretation`, `sourceReferences` is the
      context's own. → REQ-GEMCT-007

## Close-out

- [x] **TASK-GEMCT-016** — `apps/api` `composer test`/`stan`/`cs` green; `npm run verify`
      passes; fill `plan.md` verification note; flip `spec.md` to `implemented`; add SPEC-040
      rows to both README tables; add a one-line note to SPEC-011 pointing here. → REQ-GEMCT-022
