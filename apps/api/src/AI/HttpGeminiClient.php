<?php

declare(strict_types=1);

namespace App\AI;

/**
 * Real GeminiClient implementation, calling Google's Generative Language API
 * (`POST /v1beta/models/{model}:generateContent`). The HTTPS POST itself goes through an
 * injected {@see HttpTransport} (default {@see StreamHttpTransport}, PHP's built-in HTTP stream
 * wrapper — no HTTP-client dependency); everything else here — request body, endpoint URL,
 * headers, status handling, response unwrapping — is this class's own contract, pinned by
 * HttpGeminiClientTest (SPEC-040).
 *
 * This exact endpoint/request/response shape was verified against a real API key and a real
 * response during the SPEC-011 session (see specs/gemini-interpretation-provider/spec.md's
 * live-verification note) - superseding an earlier, unverified implementation that targeted a
 * `/v1beta/interactions` endpoint researched from documentation but never actually called.
 */
final class HttpGeminiClient implements GeminiClient
{
    private const ENDPOINT_TEMPLATE = 'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent';

    private readonly HttpTransport $transport;

    public function __construct(
        private readonly string $apiKey,
        private readonly string $model,
        ?HttpTransport $transport = null,
    ) {
        $this->transport = $transport ?? new StreamHttpTransport();
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

        ['status' => $statusCode, 'body' => $rawResponse] = $this->transport->post(
            $this->endpoint(),
            $requestBody,
            [
                'Content-Type' => 'application/json',
                'x-goog-api-key' => $this->apiKey,
            ],
        );

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

    private function endpoint(): string
    {
        return sprintf(self::ENDPOINT_TEMPLATE, $this->model);
    }

    /**
     * @param array<mixed> $decoded
     */
    private function extractText(array $decoded): ?string
    {
        $text = $decoded['candidates'][0]['content']['parts'][0]['text'] ?? null;

        return is_string($text) && $text !== '' ? $text : null;
    }

    private function truncate(string $text, int $maxLength = 500): string
    {
        return mb_strlen($text) > $maxLength ? mb_substr($text, 0, $maxLength) . '…' : $text;
    }
}
