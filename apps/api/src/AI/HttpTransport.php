<?php

declare(strict_types=1);

namespace App\AI;

/**
 * The one impure step of talking to the Gemini API: an HTTPS POST. Split behind this interface
 * purely as a test seam (SPEC-040) so `HttpGeminiClient`'s request-building and response-parsing
 * contract can be regression-tested offline against a recorded response — production still uses
 * {@see StreamHttpTransport}, which is the exact stream-wrapper code that used to live inline in
 * `HttpGeminiClient::generateJson()`.
 */
interface HttpTransport
{
    /**
     * @param array<string, string> $headers header name => value
     *
     * @return array{status: int, body: string} HTTP status code (0 if no status line was seen)
     *                                           and the raw response body
     *
     * @throws InterpretationProviderException if the host cannot be reached at all
     */
    public function post(string $url, string $body, array $headers): array;
}
