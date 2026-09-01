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
use Yijing\Core\CastReading;
use Yijing\Core\CastReadingRef;
use Yijing\Core\Data\HexagramTextCatalog;
use Yijing\Core\Hexagram;
use Yijing\Core\Line;
use Yijing\Core\LinePolarity;

final class ConsultationController
{
    private readonly ConsultationRepository $repository;
    private readonly ConsultationReminderRepository $reminderRepository;
    private readonly ConsultationIdGenerator $idGenerator;
    private readonly Clock $clock;

    public function __construct(Config $config)
    {
        $pdo = Database::connect($config);
        $this->repository = new SqliteConsultationRepository($pdo);
        $this->reminderRepository = new SqliteConsultationReminderRepository($pdo);
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

    public function import(Request $request): Response
    {
        try {
            $items = $this->decodeJsonArrayBody($request);
        } catch (\JsonException) {
            return $this->errorResponse('Malformed JSON body.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $consultations = array_map(fn (mixed $item): Consultation => $this->parseImportItem($item), $items);

            $batchIds = array_map(static fn (Consultation $c): string => $c->id, $consultations);

            if (count($batchIds) !== count(array_unique($batchIds))) {
                throw new \InvalidArgumentException('Import batch contains duplicate ids.');
            }

            foreach ($consultations as $consultation) {
                if ($this->repository->existsById($consultation->id)) {
                    throw new \InvalidArgumentException(
                        sprintf('A consultation with id "%s" already exists.', $consultation->id),
                    );
                }
            }

            foreach ($consultations as $consultation) {
                if (
                    $consultation->followUpToConsultationId !== null
                    && !in_array($consultation->followUpToConsultationId, $batchIds, true)
                    && $this->repository->findSummaryById($consultation->followUpToConsultationId) === null
                ) {
                    throw new \InvalidArgumentException(sprintf(
                        'Consultation "%s" references a followUpToConsultationId that does not exist.',
                        $consultation->id,
                    ));
                }
            }
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->repository->saveImportBatch($consultations);

        return new JsonResponse(['imported' => count($consultations)], Response::HTTP_CREATED);
    }

    public function index(Request $request): Response
    {
        try {
            $query = ConsultationListQuery::fromRequest($request);
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $page = $this->repository->findListPage($query);

        return new JsonResponse([
            'items' => array_map(
                static fn (ConsultationListItem $item): array => $item->toJson(),
                $page->items,
            ),
            'nextCursor' => $page->nextCursor,
        ]);
    }

    /**
     * The due reflection reminders (SPEC-054): consultations whose reminder date has arrived and
     * which still have no recorded outcome. Read on a normal page load (the Home dashboard) —
     * there is no background job or notification behind this.
     */
    public function reminders(Request $request): Response
    {
        return new JsonResponse(array_map(
            static fn (DueReminder $due): array => $due->toJson(),
            $this->reminderRepository->findDue($this->clock->now()),
        ));
    }

    /**
     * @param array<string, string> $vars
     */
    public function setReminder(Request $request, array $vars): Response
    {
        if (!$this->repository->existsById($vars['id'])) {
            return $this->errorResponse('Not Found', Response::HTTP_NOT_FOUND);
        }

        try {
            $body = $this->decodeJsonBody($request);
        } catch (\JsonException) {
            return $this->errorResponse('Malformed JSON body.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $raw = $body['remindAt'] ?? null;

        if (!is_string($raw) || trim($raw) === '') {
            return $this->errorResponse(
                '"remindAt" must be a date (YYYY-MM-DD) or an ISO-8601 instant.',
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        try {
            $remindAt = new \DateTimeImmutable($raw);
        } catch (\Exception) {
            return $this->errorResponse(
                '"remindAt" must be a date (YYYY-MM-DD) or an ISO-8601 instant.',
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $this->reminderRepository->set($vars['id'], $remindAt, $this->clock->now());

        return new JsonResponse(['remindAt' => $remindAt->format(DATE_ATOM)]);
    }

    /**
     * @param array<string, string> $vars
     */
    public function clearReminder(Request $request, array $vars): Response
    {
        if (!$this->repository->existsById($vars['id'])) {
            return $this->errorResponse('Not Found', Response::HTTP_NOT_FOUND);
        }

        $this->reminderRepository->clear($vars['id']);

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    public function tags(Request $request): Response
    {
        $counts = $request->query->get('counts');

        if (is_string($counts) && in_array(strtolower($counts), ['1', 'true', 'yes'], true)) {
            return new JsonResponse($this->repository->allTagsWithCounts());
        }

        return new JsonResponse($this->repository->allTagNames());
    }

    /**
     * @param array<string, string> $vars
     */
    public function renameTag(Request $request, array $vars): Response
    {
        $name = $vars['name'];

        try {
            $body = $this->decodeJsonBody($request);
        } catch (\JsonException) {
            return $this->errorResponse('Malformed JSON body.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $newName = is_string($body['newName'] ?? null) ? trim($body['newName']) : '';

        if ($newName === '') {
            return $this->errorResponse('"newName" must be a non-empty string.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (!$this->repository->tagExists($name)) {
            return $this->errorResponse('Not Found', Response::HTTP_NOT_FOUND);
        }

        if ($newName === $name) {
            return new JsonResponse(['renamed' => true, 'merged' => false]);
        }

        $merged = $this->repository->tagExists($newName);
        $this->repository->renameOrMergeTag($name, $newName);

        return new JsonResponse(['renamed' => true, 'merged' => $merged]);
    }

    /**
     * @param array<string, string> $vars
     */
    public function deleteTag(Request $request, array $vars): Response
    {
        $name = $vars['name'];

        if (!$this->repository->tagExists($name)) {
            return $this->errorResponse('Not Found', Response::HTTP_NOT_FOUND);
        }

        $this->repository->deleteTag($name);

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    /**
     * The full, fully-populated history — for the SPEC-028 "Export Backup (JSON)" download only.
     * This is the one endpoint that still pays the O(n) hydration cost `index()` used to; it's an
     * explicit user action, not a page load (SPEC-041).
     */
    public function export(): Response
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

        return new JsonResponse($this->toJsonWithRepeats($consultation));
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
        $outcomeKeys = [
            'whatActuallyHappened',
            'outcome',
            'reflection',
            'interpretationLens',
            'interpretationSummary',
        ];
        $touchesFavorite = array_key_exists('favorite', $body);
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
            && !$touchesFavorite
        ) {
            return $this->errorResponse(
                'Provide "note", "tag", a context field, an outcome field, "favorite", and/or '
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
                    interpretationLens: $this->resolveContextField(
                        $body,
                        'interpretationLens',
                        $consultation->outcome?->interpretationLens,
                    ),
                    interpretationSummary: $this->resolveContextField(
                        $body,
                        'interpretationSummary',
                        $consultation->outcome?->interpretationSummary,
                    ),
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

            if ($touchesFavorite) {
                if (!is_bool($body['favorite'])) {
                    throw new \InvalidArgumentException('"favorite" must be a boolean.');
                }

                $consultation = $consultation->withFavorite($body['favorite']);
            }
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->repository->save($consultation);

        // Recording an outcome retires the reflection reminder — its job (nudging the querent
        // back to record exactly this) is done (SPEC-054, REQ-RR-005).
        if ($touchesAnyOutcomeField) {
            $this->reminderRepository->clear($vars['id']);
        }

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

    /**
     * @return list<mixed>
     */
    private function decodeJsonArrayBody(Request $request): array
    {
        $decoded = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($decoded) || !array_is_list($decoded)) {
            throw new \JsonException('Request body must be a JSON array.');
        }

        /** @var list<mixed> $decoded */
        return $decoded;
    }

    private function parseImportItem(mixed $item): Consultation
    {
        if (!is_array($item)) {
            throw new \InvalidArgumentException('Each import item must be an object.');
        }

        $id = $item['id'] ?? null;
        $question = $item['question'] ?? null;
        $method = CastingMethodName::tryFrom(is_string($item['method'] ?? null) ? $item['method'] : '');
        $primaryKingWenNumber = $item['primaryHexagram']['kingWenNumber'] ?? null;
        $resultingKingWenNumber = $item['resultingHexagram']['kingWenNumber'] ?? null;
        $changingLinePositions = $item['changingLinePositions'] ?? [];
        $createdAt = $item['createdAt'] ?? null;

        if (
            !is_string($id) || $id === ''
            || !is_string($question)
            || $method === null
            || !is_int($primaryKingWenNumber)
            || !is_int($resultingKingWenNumber)
            || !is_array($changingLinePositions)
            || !is_string($createdAt)
        ) {
            throw new \InvalidArgumentException('Malformed import item: missing or invalid required fields.');
        }

        try {
            $primaryHexagram = self::hexagramFromKingWenNumber($primaryKingWenNumber, array_values($changingLinePositions));
            $resultingHexagram = self::hexagramFromKingWenNumber($resultingKingWenNumber, []);
            $createdAtDate = new \DateTimeImmutable($createdAt);
        } catch (\Exception $e) {
            throw new \InvalidArgumentException('Malformed import item: ' . $e->getMessage());
        }

        return Consultation::reconstitute(
            $id,
            $question,
            $method,
            $primaryHexagram,
            $resultingHexagram,
            $createdAtDate,
            $this->parseImportNotes($item['notes'] ?? []),
            $this->parseImportTags($item['tags'] ?? []),
            context: $this->validatedContextFieldValue($item['context'] ?? null, 'context'),
            whatHappenedBefore: $this->validatedContextFieldValue(
                $item['whatHappenedBefore'] ?? null,
                'whatHappenedBefore',
            ),
            whatUserWantsToUnderstand: $this->validatedContextFieldValue(
                $item['whatUserWantsToUnderstand'] ?? null,
                'whatUserWantsToUnderstand',
            ),
            backgroundInformation: $this->validatedContextFieldValue(
                $item['backgroundInformation'] ?? null,
                'backgroundInformation',
            ),
            initialInterpretation: $this->validatedContextFieldValue(
                $item['initialInterpretation'] ?? null,
                'initialInterpretation',
            ),
            outcome: $this->parseImportOutcome($item['outcome'] ?? null),
            followUpToConsultationId: is_string($item['followUpTo']['id'] ?? null)
                ? $item['followUpTo']['id']
                : null,
            favorite: (bool) ($item['favorite'] ?? false),
        );
    }

    /**
     * @return list<ConsultationNote>
     */
    private function parseImportNotes(mixed $notes): array
    {
        if (!is_array($notes)) {
            throw new \InvalidArgumentException('Malformed import item: "notes" must be an array.');
        }

        return array_map(function (mixed $note): ConsultationNote {
            if (!is_array($note) || !is_string($note['label'] ?? null) || !is_string($note['text'] ?? null)) {
                throw new \InvalidArgumentException('Malformed import item: malformed note.');
            }

            $label = NoteLabel::tryFrom($note['label']);

            if ($label === null) {
                throw new \InvalidArgumentException('Malformed import item: invalid note label.');
            }

            return new ConsultationNote($label, $note['text'], new \DateTimeImmutable((string) ($note['createdAt'] ?? 'now')));
        }, array_values($notes));
    }

    /**
     * @return list<string>
     */
    private function parseImportTags(mixed $tags): array
    {
        if (!is_array($tags)) {
            throw new \InvalidArgumentException('Malformed import item: "tags" must be an array.');
        }

        foreach ($tags as $tag) {
            if (!is_string($tag)) {
                throw new \InvalidArgumentException('Malformed import item: tags must be strings.');
            }
        }

        /** @var list<string> */
        return array_values($tags);
    }

    private function parseImportOutcome(mixed $outcome): ?ConsultationOutcome
    {
        if ($outcome === null) {
            return null;
        }

        if (!is_array($outcome)) {
            throw new \InvalidArgumentException('Malformed import item: "outcome" must be an object or null.');
        }

        return new ConsultationOutcome(
            $this->validatedContextFieldValue($outcome['whatActuallyHappened'] ?? null, 'whatActuallyHappened'),
            $this->validatedContextFieldValue($outcome['outcome'] ?? null, 'outcome'),
            $this->validatedContextFieldValue($outcome['reflection'] ?? null, 'reflection'),
            new \DateTimeImmutable((string) ($outcome['recordedAt'] ?? 'now')),
        );
    }

    /**
     * @param list<int> $changingPositions
     */
    private static function hexagramFromKingWenNumber(int $kingWenNumber, array $changingPositions): Hexagram
    {
        $base = Hexagram::fromKingWenNumber($kingWenNumber);

        if ($changingPositions === []) {
            return $base;
        }

        $lines = array_map(
            static fn (Line $line): Line => in_array($line->position, $changingPositions, true)
                ? new Line($line->position, $line->polarity, true)
                : $line,
            $base->lines,
        );

        return Hexagram::fromLines($lines);
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
                'interpretationLens' => $consultation->outcome->interpretationLens,
                'interpretationSummary' => $consultation->outcome->interpretationSummary,
            ],
            'followUpTo' => $this->resolveFollowUpToSummary($consultation),
            'followUps' => array_map(
                static fn (ConsultationSummary $summary): array => [
                    'id' => $summary->id,
                    'question' => $summary->question,
                ],
                $this->repository->findFollowUpSummaries($consultation->id),
            ),
            'favorite' => $consultation->favorite,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function toJsonWithRepeats(Consultation $consultation): array
    {
        $changingLinePositions = $consultation->changingLinePositions();

        return $this->toJson($consultation) + [
            'repeats' => [
                'primaryHexagram' => $this->summariesToJson(
                    $this->repository->findByPrimaryHexagramNumber(
                        $consultation->primaryHexagram->kingWenNumber,
                        $consultation->id,
                    ),
                ),
                'resultingHexagram' => $this->summariesToJson(
                    $this->repository->findByResultingHexagramNumber(
                        $consultation->resultingHexagram->kingWenNumber,
                        $consultation->id,
                    ),
                ),
                'changingLines' => $changingLinePositions === [] ? [] : $this->summariesToJson(
                    $this->repository->findByChangingLinePositions($changingLinePositions, $consultation->id),
                ),
            ],
            'readingGuidance' => $this->readingGuidanceToJson(
                CastReading::forCast($consultation->primaryHexagram, $changingLinePositions),
                $consultation,
            ),
            'reminder' => $this->reminderToJson($consultation->id),
        ];
    }

    /**
     * @return array{remindAt: string}|null
     */
    private function reminderToJson(string $consultationId): ?array
    {
        $remindAt = $this->reminderRepository->findRemindAt($consultationId);

        return $remindAt === null ? null : ['remindAt' => $remindAt->format(DATE_ATOM)];
    }

    /**
     * Resolves a {@see CastReading} (SPEC-052) into JSON with the actual classical text for each
     * ref pulled from this consultation's primary / resulting hexagram.
     *
     * @return array<string, mixed>
     */
    private function readingGuidanceToJson(CastReading $reading, Consultation $consultation): array
    {
        $json = $reading->toArray();

        $json['refs'] = array_map(
            function (CastReadingRef $ref) use ($consultation): array {
                $hexagram = $ref->hexagram === 'primary'
                    ? $consultation->primaryHexagram
                    : $consultation->resultingHexagram;

                if ($ref->kind === 'judgment') {
                    $text = $hexagram->judgment;
                } else {
                    $position = $ref->position ?? throw new \LogicException('A line ref always has a position.');
                    $text = $hexagram->lineStatements[$position - 1];
                }

                return $ref->toArray() + ['text' => $text];
            },
            $reading->refs,
        );

        if ($reading->specialText !== null) {
            $json['specialTextContent'] = HexagramTextCatalog::specialTextFor(
                $consultation->primaryHexagram->kingWenNumber,
            );
        }

        return $json;
    }

    /**
     * @param list<ConsultationSummary> $summaries
     *
     * @return list<array{id: string, question: string}>
     */
    private function summariesToJson(array $summaries): array
    {
        return array_map(
            static fn (ConsultationSummary $summary): array => ['id' => $summary->id, 'question' => $summary->question],
            $summaries,
        );
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
