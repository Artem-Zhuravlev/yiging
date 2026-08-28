<?php

declare(strict_types=1);

namespace App\Journal;

use App\Core\Config;
use App\Core\Database;
use App\Core\ListCursor;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class JournalController
{
    private readonly JournalRepository $repository;
    private readonly JournalEntryIdGenerator $idGenerator;
    private readonly Clock $clock;

    public function __construct(Config $config)
    {
        $this->repository = new SqliteJournalRepository(Database::connect($config));
        $this->idGenerator = new UuidV4JournalEntryIdGenerator();
        $this->clock = new SystemClock();
    }

    public function create(Request $request): Response
    {
        try {
            $content = $request->getContent();
            $body = $content === '' ? [] : json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return $this->errorResponse('Malformed JSON body.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $text = is_array($body) && is_string($body['text'] ?? null) ? $body['text'] : '';

        try {
            $entry = new JournalEntry($this->idGenerator->generate(), $text, $this->clock->now());
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->repository->save($entry);

        return new JsonResponse($this->toJson($entry), Response::HTTP_CREATED);
    }

    private const DEFAULT_LIMIT = 30;
    private const MIN_LIMIT = 1;
    private const MAX_LIMIT = 100;

    public function index(Request $request): Response
    {
        $cursor = $request->query->get('cursor');
        $cursor = is_string($cursor) && trim($cursor) !== '' ? trim($cursor) : null;

        if ($cursor !== null) {
            try {
                ListCursor::decode($cursor);
            } catch (\InvalidArgumentException $e) {
                return $this->errorResponse($e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        $page = $this->repository->findPage($this->clampLimit($request->query->get('limit')), $cursor);

        return new JsonResponse([
            'items' => array_map(fn (JournalEntry $entry): array => $this->toJson($entry), $page->items),
            'nextCursor' => $page->nextCursor,
        ]);
    }

    private function clampLimit(mixed $raw): int
    {
        if (is_string($raw) && ctype_digit($raw)) {
            return max(self::MIN_LIMIT, min(self::MAX_LIMIT, (int) $raw));
        }

        return self::DEFAULT_LIMIT;
    }

    private function errorResponse(string $message, int $status): JsonResponse
    {
        return new JsonResponse(['error' => $message], $status);
    }

    /**
     * @return array<string, mixed>
     */
    private function toJson(JournalEntry $entry): array
    {
        return [
            'id' => $entry->id,
            'text' => $entry->text,
            'createdAt' => $entry->createdAt->format(DATE_ATOM),
        ];
    }
}
