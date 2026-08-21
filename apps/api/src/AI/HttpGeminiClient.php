<?php

declare(strict_types=1);

namespace App\AI;

/**
 * Real GeminiClient implementation, calling Google's Generative Language API
 * (`POST /v1beta/models/{model}:generateContent`) via PHP's built-in HTTP stream wrapper - no
 * HTTP client dependency needed beyond the ext-openssl this project already requires.
 *
 * This exact endpoint/request/response shape was verified against a real API key and a real
 * response during this session (see specs/gemini-interpretation-provider/spec.md's live-
 * verification note) - superseding an earlier, unverified implementation that targeted a
 * `/v1beta/interactions` endpoint researched from documentation but never actually called; that
 * endpoint does not respond (hangs rather than erroring), confirmed directly with curl before
 * this fix.
 */
final class HttpGeminiClient implements GeminiClient
{
    private const ENDPOINT_TEMPLATE = 'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent';
    private const TIMEOUT_SECONDS = 30;

    public function __construct(
        private readonly string $apiKey,
        private readonly string $model,
    ) {
    }

    public function generateJson(string $prompt, array $schema): array
    {
        $requestBody = json_encode([
            'contents' => [
                ['parts' => [['text' => $prompt]]],
            ],
            'generationConfig' => [
                'responseMimeType' => 'application/json',
                'responseSchema' => $schema,
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

        $endpoint = sprintf(self::ENDPOINT_TEMPLATE, $this->model);
        $rawResponse = @file_get_contents($endpoint, false, $context);

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
        $text = is_array($decoded) ? $this->extractText($decoded) : null;

        if ($text === null) {
            throw new InterpretationProviderException(
                'Gemini API response did not include the expected candidates[0].content.parts[0].text '
                    . 'field: ' . $this->truncate($rawResponse),
            );
        }

        $structured = json_decode($text, true);

        if (!is_array($structured)) {
            throw new InterpretationProviderException(
                'Gemini API response text was not valid JSON: ' . $this->truncate($text),
            );
        }

        /** @var array<string, mixed> $structured */
        return $structured;
    }

    /**
     * @param array<mixed> $decoded
     */
    private function extractText(array $decoded): ?string
    {
        $text = $decoded['candidates'][0]['content']['parts'][0]['text'] ?? null;

        return is_string($text) && $text !== '' ? $text : null;
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
