<?php

declare(strict_types=1);

namespace App\Readings;

use App\Core\ListCursor;
use Symfony\Component\HttpFoundation\Request;

/**
 * Parsed, validated parameters for `GET /api/consultations` (SPEC-041): page size, cursor
 * position, and the server-side search / tag / favourite filters that used to run client-side
 * over the whole downloaded history.
 */
final class ConsultationListQuery
{
    private const DEFAULT_LIMIT = 30;
    private const MIN_LIMIT = 1;
    private const MAX_LIMIT = 100;

    /**
     * @param list<string> $tags AND semantics — a row must carry every one
     */
    public function __construct(
        public readonly int $limit,
        public readonly ?string $cursor,
        public readonly ?string $q,
        public readonly array $tags,
        public readonly bool $favoriteOnly,
    ) {
    }

    /**
     * @throws \InvalidArgumentException if `cursor` is present but not decodable
     */
    public static function fromRequest(Request $request): self
    {
        $cursor = self::stringOrNull($request->query->get('cursor'));

        if ($cursor !== null) {
            // Fail fast on a malformed cursor so the controller can map it to 422, rather than
            // silently serving page 1.
            ListCursor::decode($cursor);
        }

        return new self(
            limit: self::clampLimit($request->query->get('limit')),
            cursor: $cursor,
            q: self::stringOrNull($request->query->get('q')),
            tags: self::parseTags($request->query->get('tags')),
            favoriteOnly: self::isTruthy($request->query->get('favorite')),
        );
    }

    private static function clampLimit(mixed $raw): int
    {
        if (!is_string($raw) && !is_int($raw)) {
            return self::DEFAULT_LIMIT;
        }

        if (is_string($raw) && !ctype_digit($raw)) {
            return self::DEFAULT_LIMIT;
        }

        return max(self::MIN_LIMIT, min(self::MAX_LIMIT, (int) $raw));
    }

    private static function stringOrNull(mixed $raw): ?string
    {
        if (!is_string($raw)) {
            return null;
        }

        $trimmed = trim($raw);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * @return list<string>
     */
    private static function parseTags(mixed $raw): array
    {
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }

        $tags = array_map(trim(...), explode(',', $raw));

        return array_values(array_unique(array_filter($tags, static fn (string $t): bool => $t !== '')));
    }

    private static function isTruthy(mixed $raw): bool
    {
        return is_string($raw) && in_array(strtolower($raw), ['1', 'true', 'yes'], true);
    }
}
