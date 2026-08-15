<?php

declare(strict_types=1);

namespace App\Readings;

use App\Casting\DivinationMethod;
use App\Casting\ManualMethod;
use App\Casting\RandomIntCoinTosser;
use App\Casting\RandomMethod;
use App\Casting\ThreeCoinsMethod;
use App\Core\Config;
use App\Core\Database;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Yijing\Core\Hexagram;
use Yijing\Core\Line;
use Yijing\Core\LinePolarity;

final class ConsultationController
{
    private readonly ConsultationRepository $repository;
    private readonly ConsultationIdGenerator $idGenerator;
    private readonly Clock $clock;

    public function __construct(Config $config)
    {
        $this->repository = new SqliteConsultationRepository(Database::connect($config));
        $this->idGenerator = new UuidV4ConsultationIdGenerator();
        $this->clock = new SystemClock();
    }

    public function create(Request $request): Response
    {
        try {
            $body = $this->decodeJsonBody($request);
        } catch (\JsonException) {
            return $this->errorResponse('Malformed JSON body.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $question = is_string($body['question'] ?? null) ? $body['question'] : '';
        $methodValue = is_string($body['method'] ?? null) ? $body['method'] : '';
        $methodName = CastingMethodName::tryFrom($methodValue);

        if ($methodName === null) {
            return $this->errorResponse('Invalid or missing "method".', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $divinationMethod = $this->resolveDivinationMethod($methodName, $body);
            $hexagram = $divinationMethod->cast();

            $followUpToConsultationId = $this->parseOptionalContextField($body, 'followUpToConsultationId');
            $this->validateFollowUpTargetExists($followUpToConsultationId);

            $consultation = Consultation::create(
                $this->idGenerator->generate(),
                $question,
                $methodName,
                $hexagram,
                $this->clock->now(),
                context: $this->parseOptionalContextField($body, 'context'),
                whatHappenedBefore: $this->parseOptionalContextField($body, 'whatHappenedBefore'),
                whatUserWantsToUnderstand: $this->parseOptionalContextField($body, 'whatUserWantsToUnderstand'),
                backgroundInformation: $this->parseOptionalContextField($body, 'backgroundInformation'),
                initialInterpretation: $this->parseOptionalContextField($body, 'initialInterpretation'),
                followUpToConsultationId: $followUpToConsultationId,
            );
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->repository->save($consultation);

        return new JsonResponse($this->toJson($consultation), Response::HTTP_CREATED);
    }

    public function index(): Response
    {
        $consultations = array_map(
            fn (Consultation $consultation): array => $this->toJson($consultation),
            $this->repository->findAll(),
        );

        return new JsonResponse($consultations);
    }

    /**
     * @param array<string, string> $vars
     */
    public function show(Request $request, array $vars): Response
    {
        $consultation = $this->repository->findById($vars['id']);

        if ($consultation === null) {
            return $this->errorResponse('Not Found', Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse($this->toJson($consultation));
    }

    /**
     * @param array<string, string> $vars
     */
    public function update(Request $request, array $vars): Response
    {
        $consultation = $this->repository->findById($vars['id']);

        if ($consultation === null) {
            return $this->errorResponse('Not Found', Response::HTTP_NOT_FOUND);
        }

        try {
            $body = $this->decodeJsonBody($request);
        } catch (\JsonException) {
            return $this->errorResponse('Malformed JSON body.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $noteInput = $body['note'] ?? null;
        $tagInput = $body['tag'] ?? null;
        $contextKeys = [
            'context',
            'whatHappenedBefore',
            'whatUserWantsToUnderstand',
            'backgroundInformation',
            'initialInterpretation',
        ];
        $outcomeKeys = ['whatActuallyHappened', 'outcome', 'reflection'];
        $touchesAnyContextField = array_filter(
            $contextKeys,
            static fn (string $key): bool => array_key_exists($key, $body),
        ) !== [];
        $touchesAnyOutcomeField = array_filter(
            $outcomeKeys,
            static fn (string $key): bool => array_key_exists($key, $body),
        ) !== [];
        $touchesFollowUp = array_key_exists('followUpToConsultationId', $body);

        if (
            $noteInput === null
            && $tagInput === null
            && !$touchesAnyContextField
            && !$touchesAnyOutcomeField
            && !$touchesFollowUp
        ) {
            return $this->errorResponse(
                'Provide "note", "tag", a context field, an outcome field, and/or '
                    . '"followUpToConsultationId" to update.',
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        try {
            if ($noteInput !== null) {
                $consultation = $consultation->withAddedNote($this->parseNote($noteInput));
            }

            if ($tagInput !== null) {
                $consultation = $consultation->withAddedTag($this->parseTag($tagInput));
            }

            if ($touchesAnyContextField) {
                $consultation = $consultation->withUpdatedContext(
                    context: $this->resolveContextField($body, 'context', $consultation->context),
                    whatHappenedBefore: $this->resolveContextField(
                        $body,
                        'whatHappenedBefore',
                        $consultation->whatHappenedBefore,
                    ),
                    whatUserWantsToUnderstand: $this->resolveContextField(
                        $body,
                        'whatUserWantsToUnderstand',
                        $consultation->whatUserWantsToUnderstand,
                    ),
                    backgroundInformation: $this->resolveContextField(
                        $body,
                        'backgroundInformation',
                        $consultation->backgroundInformation,
                    ),
                    initialInterpretation: $this->resolveContextField(
                        $body,
                        'initialInterpretation',
                        $consultation->initialInterpretation,
                    ),
                );
            }

            if ($touchesAnyOutcomeField) {
                $consultation = $consultation->withUpdatedOutcome(
                    whatActuallyHappened: $this->resolveContextField(
                        $body,
                        'whatActuallyHappened',
                        $consultation->outcome?->whatActuallyHappened,
                    ),
                    outcome: $this->resolveContextField($body, 'outcome', $consultation->outcome?->outcome),
                    reflection: $this->resolveContextField(
                        $body,
                        'reflection',
                        $consultation->outcome?->reflection,
                    ),
                    recordedAt: $this->clock->now(),
                );
            }

            if ($touchesFollowUp) {
                $followUpToConsultationId = $this->resolveContextField(
                    $body,
                    'followUpToConsultationId',
                    $consultation->followUpToConsultationId,
                );
                $this->validateFollowUpTargetExists($followUpToConsultationId);
                $consultation = $consultation->withFollowUpTo($followUpToConsultationId);
            }
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->repository->save($consultation);

        return new JsonResponse($this->toJson($consultation));
    }

    private function parseNote(mixed $noteInput): ConsultationNote
    {
        if (!is_array($noteInput)) {
            throw new \InvalidArgumentException('"note" must be an object with "label" and "text".');
        }

        $label = NoteLabel::tryFrom(is_string($noteInput['label'] ?? null) ? $noteInput['label'] : '');

        if ($label === null) {
            throw new \InvalidArgumentException('"note.label" must be one of: before, after, later.');
        }

        $text = is_string($noteInput['text'] ?? null) ? $noteInput['text'] : '';

        return new ConsultationNote($label, $text, $this->clock->now());
    }

    private function parseTag(mixed $tagInput): string
    {
        if (!is_string($tagInput) || trim($tagInput) === '') {
            throw new \InvalidArgumentException('"tag" must be a non-empty string.');
        }

        return $tagInput;
    }

    /**
     * For create(): a key absent from the body means "not provided," same as an explicit null.
     *
     * @param array<string, mixed> $body
     */
    private function parseOptionalContextField(array $body, string $key): ?string
    {
        if (!array_key_exists($key, $body)) {
            return null;
        }

        return $this->validatedContextFieldValue($body[$key], $key);
    }

    /**
     * For update(): a key absent from the body means "leave unchanged" (returns $currentValue);
     * present distinguishes a new value (string) from an explicit clear (null).
     *
     * @param array<string, mixed> $body
     */
    private function resolveContextField(array $body, string $key, ?string $currentValue): ?string
    {
        if (!array_key_exists($key, $body)) {
            return $currentValue;
        }

        return $this->validatedContextFieldValue($body[$key], $key);
    }

    private function validatedContextFieldValue(mixed $value, string $key): ?string
    {
        if ($value === null) {
            return null;
        }

        if (!is_string($value)) {
            throw new \InvalidArgumentException(sprintf('"%s" must be a string or null.', $key));
        }

        return $value;
    }

    private function validateFollowUpTargetExists(?string $followUpToConsultationId): void
    {
        if ($followUpToConsultationId === null) {
            return;
        }

        if ($this->repository->findSummaryById($followUpToConsultationId) === null) {
            throw new \InvalidArgumentException(
                '"followUpToConsultationId" must reference an existing consultation.',
            );
        }
    }

    /**
     * @param array<string, mixed> $body
     */
    private function resolveDivinationMethod(CastingMethodName $method, array $body): DivinationMethod
    {
        return match ($method) {
            CastingMethodName::ThreeCoins => new ThreeCoinsMethod(new RandomIntCoinTosser()),
            CastingMethodName::Random => new RandomMethod(new RandomIntCoinTosser()),
            CastingMethodName::Manual => new ManualMethod($this->parseManualLines($body['lines'] ?? null)),
        };
    }

    /**
     * @return list<Line>
     */
    private function parseManualLines(mixed $lines): array
    {
        if (!is_array($lines)) {
            throw new \InvalidArgumentException(
                '"lines" must be an array of exactly 6 line objects for method "manual".',
            );
        }

        $result = [];
        foreach (array_values($lines) as $index => $line) {
            if (!is_array($line) || !array_key_exists('polarity', $line) || !array_key_exists('changing', $line)) {
                throw new \InvalidArgumentException(sprintf('Malformed line at index %d.', $index));
            }

            $polarity = match ($line['polarity']) {
                'yin' => LinePolarity::Yin,
                'yang' => LinePolarity::Yang,
                default => throw new \InvalidArgumentException(
                    sprintf('Invalid polarity at index %d: expected "yin" or "yang".', $index),
                ),
            };

            $result[] = new Line($index + 1, $polarity, (bool) $line['changing']);
        }

        return $result;
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

    private function errorResponse(string $message, int $status): JsonResponse
    {
        return new JsonResponse(['error' => $message], $status);
    }

    /**
     * @return array<string, mixed>
     */
    private function toJson(Consultation $consultation): array
    {
        return [
            'id' => $consultation->id,
            'question' => $consultation->question,
            'method' => $consultation->method->value,
            'primaryHexagram' => $this->hexagramToJson($consultation->primaryHexagram),
            'changingLinePositions' => $consultation->changingLinePositions(),
            'resultingHexagram' => $this->hexagramToJson($consultation->resultingHexagram),
            'createdAt' => $consultation->createdAt->format(DATE_ATOM),
            'notes' => array_map(
                static fn (ConsultationNote $note): array => [
                    'label' => $note->label->value,
                    'text' => $note->text,
                    'createdAt' => $note->createdAt->format(DATE_ATOM),
                ],
                $consultation->notes,
            ),
            'tags' => $consultation->tags,
            'context' => $consultation->context,
            'whatHappenedBefore' => $consultation->whatHappenedBefore,
            'whatUserWantsToUnderstand' => $consultation->whatUserWantsToUnderstand,
            'backgroundInformation' => $consultation->backgroundInformation,
            'initialInterpretation' => $consultation->initialInterpretation,
            'outcome' => $consultation->outcome === null ? null : [
                'whatActuallyHappened' => $consultation->outcome->whatActuallyHappened,
                'outcome' => $consultation->outcome->outcome,
                'reflection' => $consultation->outcome->reflection,
                'recordedAt' => $consultation->outcome->recordedAt->format(DATE_ATOM),
            ],
            'followUpTo' => $this->resolveFollowUpToSummary($consultation),
            'followUps' => array_map(
                static fn (ConsultationSummary $summary): array => [
                    'id' => $summary->id,
                    'question' => $summary->question,
                ],
                $this->repository->findFollowUpSummaries($consultation->id),
            ),
        ];
    }

    /**
     * @return array{id: string, question: string}|null
     */
    private function resolveFollowUpToSummary(Consultation $consultation): ?array
    {
        if ($consultation->followUpToConsultationId === null) {
            return null;
        }

        $summary = $this->repository->findSummaryById($consultation->followUpToConsultationId);

        if ($summary === null) {
            return null;
        }

        return ['id' => $summary->id, 'question' => $summary->question];
    }

    /**
     * @return array<string, mixed>
     */
    private function hexagramToJson(Hexagram $hexagram): array
    {
        return [
            'kingWenNumber' => $hexagram->kingWenNumber,
            'chineseName' => $hexagram->chineseName,
            'pinyin' => $hexagram->pinyin,
        ];
    }
}
