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
     * Every call recorded in order — lets a retry-loop test assert both how many times the
     * client was called and what each successive prompt looked like.
     *
     * @var list<array{prompt: string, schema: array<string, mixed>}>
     */
    public array $calls = [];

    /**
     * @var list<array<string, mixed>>
     */
    private array $responseQueue;

    /**
     * @param array<string, mixed>|null $response single fixed response, returned every call
     * @param list<array<string, mixed>>|null $responses a queue of distinct responses, one per
     *        call, for testing retry behavior — takes priority over $response while non-empty
     */
    public function __construct(
        private readonly ?array $response = null,
        private readonly ?string $failureMessage = null,
        ?array $responses = null,
    ) {
        $this->responseQueue = $responses ?? [];
    }

    public function generateJson(string $prompt, array $schema): array
    {
        $this->lastCall = ['prompt' => $prompt, 'schema' => $schema];
        $this->calls[] = $this->lastCall;

        if ($this->failureMessage !== null) {
            throw new InterpretationProviderException($this->failureMessage);
        }

        if ($this->responseQueue !== []) {
            return array_shift($this->responseQueue);
        }

        return $this->response ?? [];
    }
}
