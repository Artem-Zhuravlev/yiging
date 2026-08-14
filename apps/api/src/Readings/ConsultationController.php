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

            $consultation = Consultation::create(
                $this->idGenerator->generate(),
                $question,
                $methodName,
                $hexagram,
                $this->clock->now(),
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

        if ($noteInput === null && $tagInput === null) {
            return $this->errorResponse('Provide "note" and/or "tag" to add.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            if ($noteInput !== null) {
                $consultation = $consultation->withAddedNote($this->parseNote($noteInput));
            }

            if ($tagInput !== null) {
                $consultation = $consultation->withAddedTag($this->parseTag($tagInput));
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
        ];
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
