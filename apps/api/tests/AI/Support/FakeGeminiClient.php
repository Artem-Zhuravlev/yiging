<?php

declare(strict_types=1);

namespace App\Tests\AI\Support;

use App\AI\GeminiClient;
use App\AI\InterpretationProviderException;

final class FakeGeminiClient implements GeminiClient
{
    /**
     * @var array{prompt: string, schema: array<string, mixed>}|null
     */
    public ?array $lastCall = null;

    /**
     * @param array<string, mixed>|null $response
     */
    public function __construct(
        private readonly ?array $response = null,
        private readonly ?string $failureMessage = null,
    ) {
    }

    public function generateJson(string $prompt, array $schema): array
    {
        $this->lastCall = ['prompt' => $prompt, 'schema' => $schema];

        if ($this->failureMessage !== null) {
            throw new InterpretationProviderException($this->failureMessage);
        }

        return $this->response ?? [];
    }
}
