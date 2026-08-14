<?php

declare(strict_types=1);

namespace App\AI;

use App\Core\Config;
use App\Core\Database;
use App\Readings\ConsultationRepository;
use App\Readings\SqliteConsultationRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class InterpretationController
{
    private readonly ConsultationRepository $repository;
    private readonly InterpretationContextBuilder $contextBuilder;
    private readonly InterpretationProvider $provider;
    private readonly RateLimiter $rateLimiter;
    private readonly int $rateLimitWindowSeconds;

    public function __construct(Config $config)
    {
        $pdo = Database::connect($config);
        $this->repository = new SqliteConsultationRepository($pdo);
        $this->contextBuilder = new InterpretationContextBuilder();
        $this->provider = self::resolveProvider($config);
        $this->rateLimitWindowSeconds = $config->int('ai_rate_limit_window_seconds', 3600);
        $this->rateLimiter = new SqliteRateLimiter(
            $pdo,
            $config->int('ai_rate_limit_max', 20),
            $this->rateLimitWindowSeconds,
        );
    }

    /**
     * @param array<string, string> $vars
     */
    public function create(Request $request, array $vars): Response
    {
        // Rate limit check comes before any other work - a rejected request should never
        // touch the repository, build a context, or call the provider.
        $rateLimitKey = $request->getClientIp() ?? 'unknown';

        if (!$this->rateLimiter->attempt($rateLimitKey)) {
            $response = new JsonResponse(
                ['error' => 'Too many interpretation requests. Please try again later.'],
                Response::HTTP_TOO_MANY_REQUESTS,
            );
            $response->headers->set('Retry-After', (string) $this->rateLimitWindowSeconds);

            return $response;
        }

        $consultation = $this->repository->findById($vars['id']);

        if ($consultation === null) {
            return new JsonResponse(['error' => 'Not Found'], Response::HTTP_NOT_FOUND);
        }

        $context = $this->contextBuilder->build($consultation);

        try {
            $interpretation = $this->provider->interpret($context);
        } catch (InterpretationProviderException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_GATEWAY);
        }

        return new JsonResponse($this->toJson($interpretation));
    }

    /**
     * Fails loudly (not a silent fallback to the mock provider) when "gemini" is selected but
     * misconfigured - a deployment problem the operator needs to see, not one that quietly
     * makes every interpretation a mock one.
     */
    private static function resolveProvider(Config $config): InterpretationProvider
    {
        $providerName = $config->string('ai_provider');

        return match ($providerName) {
            // '' covers a hand-built Config that never set ai_provider at all (e.g. most
            // tests) - same as the documented "mock" default in Config::fromEnv(), not a
            // distinct case to reject.
            'mock', '' => new MockInterpretationProvider(),
            'gemini' => self::resolveGeminiProvider($config),
            default => throw new \RuntimeException(
                "Unknown AI_PROVIDER '{$providerName}'. Expected 'mock' or 'gemini'.",
            ),
        };
    }

    private static function resolveGeminiProvider(Config $config): InterpretationProvider
    {
        $apiKey = $config->string('ai_api_key');

        if ($apiKey === '') {
            throw new \RuntimeException(
                'AI_PROVIDER is set to "gemini" but AI_API_KEY is empty. Set it in apps/api/.env.',
            );
        }

        return new GeminiInterpretationProvider(new HttpGeminiClient($apiKey, $config->string('ai_model')));
    }

    /**
     * @return array<string, mixed>
     */
    private function toJson(Interpretation $interpretation): array
    {
        return [
            'summary' => $interpretation->summary,
            'coreTheme' => $interpretation->coreTheme,
            'situation' => $interpretation->situation,
            'changingLineMeaning' => $interpretation->changingLineMeaning,
            'transition' => $interpretation->transition,
            'practicalReflection' => $interpretation->practicalReflection,
            'uncertainties' => $interpretation->uncertainties,
            'sourceReferences' => $interpretation->sourceReferences,
        ];
    }
}
