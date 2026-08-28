<?php

declare(strict_types=1);

namespace App\AI;

/**
 * The production {@see HttpTransport}: a plain HTTPS POST via PHP's built-in HTTP stream wrapper
 * — no HTTP-client dependency beyond the ext-openssl this project already requires.
 *
 * This is the exact `stream_context_create` + `@file_get_contents` + `$http_response_header`
 * status-line parsing that lived inline in `HttpGeminiClient::generateJson()` before SPEC-040;
 * moved here verbatim so the request/response contract above it can be tested without a network
 * call.
 */
final class StreamHttpTransport implements HttpTransport
{
    private const TIMEOUT_SECONDS = 30;

    public function post(string $url, string $body, array $headers): array
    {
        $headerLines = [];
        foreach ($headers as $name => $value) {
            $headerLines[] = $name . ': ' . $value;
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", $headerLines),
                'content' => $body,
                'timeout' => self::TIMEOUT_SECONDS,
                'ignore_errors' => true,
            ],
        ]);

        $rawResponse = @file_get_contents($url, false, $context);

        if ($rawResponse === false) {
            throw new InterpretationProviderException('Could not reach the Gemini API.');
        }

        // $http_response_header is populated in local scope by file_get_contents() over the HTTP
        // stream wrapper (same as the pre-SPEC-040 inline code relied on).
        return [
            'status' => $this->statusCodeFrom($http_response_header),
            'body' => $rawResponse,
        ];
    }

    /**
     * @param list<string> $headers
     */
    private function statusCodeFrom(array $headers): int
    {
        foreach ($headers as $header) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $header, $matches) === 1) {
                return (int) $matches[1];
            }
        }

        return 0;
    }
}
