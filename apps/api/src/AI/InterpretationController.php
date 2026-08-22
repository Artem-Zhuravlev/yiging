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
    private const MAX_FOLLOW_UP_QUESTION_LENGTH = 2000;

    private readonly ConsultationRepository $repository;
    private readonly InterpretationContextBuilder $contextBuilder;
    private readonly InterpretationProvider $provider;
    private readonly InterpretationProfileRepository $profileRepository;
    private readonly RateLimiter $rateLimiter;
    private readonly int $rateLimitWindowSeconds;

    public function __construct(Config $config)
    {
        $pdo = Database::connect($config);
        $this->repository = new SqliteConsultationRepository($pdo);
        $this->contextBuilder = new InterpretationContextBuilder();
        $this->provider = self::resolveProvider($config);
        $this->profileRepository = new SqliteInterpretationProfileRepository($pdo);
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

        try {
            $body = $this->decodeJsonBody($request);
        } catch (\JsonException) {
            return new JsonResponse(['error' => 'Malformed JSON body.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $lensValue = is_string($body['lens'] ?? null) ? $body['lens'] : InterpretationLens::General->value;
        $lens = InterpretationLens::tryFrom($lensValue);

        if ($lens === null) {
            return new JsonResponse(
                ['error' => "Invalid 'lens'. Expected one of: general, psychological, practical, symbolic."],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $language = $this->resolveLanguage($body);

        if ($language === null) {
            return new JsonResponse(
                ['error' => "Invalid 'language'. Expected one of: en, uk."],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $consultation = $this->repository->findById($vars['id']);

        if ($consultation === null) {
            return new JsonResponse(['error' => 'Not Found'], Response::HTTP_NOT_FOUND);
        }

        $context = $this->contextBuilder->build($consultation);
        $profile = $this->profileRepository->get();

        try {
            $interpretation = $this->provider->interpret($context, $lens, $profile, $language);
        } catch (InterpretationProviderException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_GATEWAY);
        }

        return new JsonResponse($this->toJson($interpretation, $lens, $language));
    }

    /**
     * @param array<string, string> $vars
     */
    public function followUp(Request $request, array $vars): Response
    {
        // Same rate-limit-first ordering, same limiter/key as create() - a follow-up is a real
        // provider call with real cost, sharing the same hourly budget, not a separate one.
        $rateLimitKey = $request->getClientIp() ?? 'unknown';

        if (!$this->rateLimiter->attempt($rateLimitKey)) {
            $response = new JsonResponse(
                ['error' => 'Too many interpretation requests. Please try again later.'],
                Response::HTTP_TOO_MANY_REQUESTS,
            );
            $response->headers->set('Retry-After', (string) $this->rateLimitWindowSeconds);

            return $response;
        }

        try {
            $body = $this->decodeJsonBody($request);
        } catch (\JsonException) {
            return new JsonResponse(['error' => 'Malformed JSON body.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $question = is_string($body['question'] ?? null) ? $body['question'] : '';

        if (trim($question) === '' || mb_strlen($question) > self::MAX_FOLLOW_UP_QUESTION_LENGTH) {
            return new JsonResponse(
                [
                    'error' => sprintf(
                        '"question" must be a non-empty string of at most %d characters.',
                        self::MAX_FOLLOW_UP_QUESTION_LENGTH,
                    ),
                ],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        try {
            $history = $this->parseHistory($body['history'] ?? []);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $language = $this->resolveLanguage($body);

        if ($language === null) {
            return new JsonResponse(
                ['error' => "Invalid 'language'. Expected one of: en, uk."],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $consultation = $this->repository->findById($vars['id']);

        if ($consultation === null) {
            return new JsonResponse(['error' => 'Not Found'], Response::HTTP_NOT_FOUND);
        }

        $context = $this->contextBuilder->build($consultation);
        $profile = $this->profileRepository->get();

        try {
            $answer = $this->provider->answerFollowUp($context, $history, $question, $profile, $language);
        } catch (InterpretationProviderException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_GATEWAY);
        }

        return new JsonResponse([
            'answer' => $answer->answer,
            'sourceReferences' => $answer->sourceReferences,
        ]);
    }

    /**
     * Absent defaults to English; present-but-invalid returns null (the caller then responds
     * 422) — same present/absent distinction already used for "lens" above.
     *
     * @param array<string, mixed> $body
     */
    private function resolveLanguage(array $body): ?ResponseLanguage
    {
        if (!array_key_exists('language', $body)) {
            return ResponseLanguage::English;
        }

        $languageValue = $body['language'];

        return is_string($languageValue) ? ResponseLanguage::tryFrom($languageValue) : null;
    }

    /**
     * @return list<ConversationExchange>
     */
    private function parseHistory(mixed $history): array
    {
        if (!is_array($history)) {
            throw new \InvalidArgumentException('"history" must be an array.');
        }

        return array_map(function (mixed $exchange): ConversationExchange {
            if (
                !is_array($exchange)
                || !is_string($exchange['question'] ?? null)
                || !is_string($exchange['answer'] ?? null)
            ) {
                throw new \InvalidArgumentException(
                    '"history" entries must each have string "question" and "answer" fields.',
                );
            }

            return new ConversationExchange($exchange['question'], $exchange['answer']);
        }, array_values($history));
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
    private function decodeJsonBody(Request $request): array
    {
        $content = $request->getContent();

        if ($content === '') {
            return [];
        }

        $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($decoded)) {
            throw new \JsonException('Request body must be a JSON object.');
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    /**
     * @return array<string, mixed>
     */
    private function toJson(Interpretation $interpretation, InterpretationLens $lens, ResponseLanguage $language): array
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
            'lens' => $lens->value,
            'language' => $language->value,
        ];
    }
}
