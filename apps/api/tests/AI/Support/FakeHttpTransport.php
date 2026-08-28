<?php

declare(strict_types=1);

namespace App\Tests\AI\Support;

use App\AI\HttpTransport;
use App\AI\InterpretationProviderException;

/**
 * Test double for {@see HttpTransport}: records the request `HttpGeminiClient` builds and hands
 * back a canned response (or throws), so the request/response contract can be asserted without a
 * network call (SPEC-040).
 */
final class FakeHttpTransport implements HttpTransport
{
    /**
     * @var array{url: string, body: string, headers: array<string, string>}|null
     */
    public ?array $lastCall = null;

    public function __construct(
        private readonly int $status = 200,
        private readonly string $responseBody = '',
        private readonly ?InterpretationProviderException $throw = null,
    ) {
    }

    public function post(string $url, string $body, array $headers): array
    {
        $this->lastCall = ['url' => $url, 'body' => $body, 'headers' => $headers];

        if ($this->throw !== null) {
            throw $this->throw;
        }

        return ['status' => $this->status, 'body' => $this->responseBody];
    }
}
