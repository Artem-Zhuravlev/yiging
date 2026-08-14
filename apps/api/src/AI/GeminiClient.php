<?php

declare(strict_types=1);

namespace App\AI;

interface GeminiClient
{
    /**
     * Sends $prompt to Gemini requesting output matching $schema (a JSON Schema object,
     * lowercase types per Gemini's structured-output contract), and returns the already
     * json_decode()'d structured response.
     *
     * @param array<string, mixed> $schema
     * @return array<string, mixed>
     *
     * @throws InterpretationProviderException on any transport failure, non-2xx response, or
     *         a response that isn't valid JSON matching $schema's top-level shape
     */
    public function generateJson(string $prompt, array $schema): array;
}
