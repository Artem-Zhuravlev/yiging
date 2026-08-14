<?php

declare(strict_types=1);

namespace App\AI;

/**
 * Real GeminiClient implementation, calling Google's Interactions API
 * (https://ai.google.dev/api) via PHP's built-in HTTP stream wrapper - no HTTP client
 * dependency needed beyond the ext-openssl this project already requires.
 *
 * Deliberately uses the Interactions API (POST /v1beta/interactions) rather than the older
 * generateContent endpoint: it is Google's current, actively-recommended endpoint as of this
 * writing. See specs/gemini-interpretation-provider/plan.md for the research behind this
 * choice and an explicit caveat that it has not been exercised against a real API key from
 * this environment.
 */
final class HttpGeminiClient implements GeminiClient
{
    private const ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta/interactions';
    private const TIMEOUT_SECONDS = 30;

    public function __construct(
        private readonly string $apiKey,
        private readonly string $model,
    ) {
    }

    public function generateJson(string $prompt, array $schema): array
    {
        $requestBody = json_encode([
            'model' => $this->model,
            'input' => $prompt,
            'response_format' => [
                'type' => 'text',
                'mime_type' => 'application/json',
                'schema' => $schema,
            ],
        ], JSON_THROW_ON_ERROR);

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", [
                    'Content-Type: application/json',
                    'x-goog-api-key: ' . $this->apiKey,
                ]),
                'content' => $requestBody,
                'timeout' => self::TIMEOUT_SECONDS,
                'ignore_errors' => true,
            ],
        ]);

        $rawResponse = @file_get_contents(self::ENDPOINT, false, $context);

        if ($rawResponse === false) {
            throw new InterpretationProviderException('Could not reach the Gemini API.');
        }

        $statusCode = $this->statusCodeFrom($http_response_header);

        if ($statusCode < 200 || $statusCode >= 300) {
            throw new InterpretationProviderException(
                sprintf('Gemini API returned HTTP %d: %s', $statusCode, $this->truncate($rawResponse)),
            );
        }

        $decoded = json_decode($rawResponse, true);

        if (!is_array($decoded) || !isset($decoded['output_text']) || !is_string($decoded['output_text'])) {
            throw new InterpretationProviderException(
                'Gemini API response did not include the expected "output_text" field: '
                    . $this->truncate($rawResponse),
            );
        }

        $structured = json_decode($decoded['output_text'], true);

        if (!is_array($structured)) {
            throw new InterpretationProviderException(
                'Gemini API "output_text" was not valid JSON: ' . $this->truncate($decoded['output_text']),
            );
        }

        /** @var array<string, mixed> $structured */
        return $structured;
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

    private function truncate(string $text, int $maxLength = 500): string
    {
        return mb_strlen($text) > $maxLength ? mb_substr($text, 0, $maxLength) . '…' : $text;
    }
}
