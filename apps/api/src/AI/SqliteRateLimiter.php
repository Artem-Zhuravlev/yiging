<?php

declare(strict_types=1);

namespace App\AI;

use PDO;

/**
 * Fixed-lookback rate limiter: "at most $maxAttempts attempts for a given key within the last
 * $windowSeconds," backed by a plain count query against SQLite - no token/leaky bucket, no
 * external cache. See specs/ai-rate-limiting/plan.md for why this is enough for this project's
 * traffic scale.
 */
final class SqliteRateLimiter implements RateLimiter
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly int $maxAttempts,
        private readonly int $windowSeconds,
    ) {
    }

    public function attempt(string $key): bool
    {
        $now = new \DateTimeImmutable();
        $windowStart = $now->modify("-{$this->windowSeconds} seconds");

        $count = $this->countSince($key, $windowStart);

        if ($count >= $this->maxAttempts) {
            return false;
        }

        $insert = $this->pdo->prepare(
            'INSERT INTO rate_limit_hits (rate_limit_key, created_at) VALUES (:key, :created_at)',
        );
        $insert->execute([
            'key' => $key,
            'created_at' => $now->format(DATE_ATOM),
        ]);

        return true;
    }

    private function countSince(string $key, \DateTimeImmutable $windowStart): int
    {
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*) FROM rate_limit_hits WHERE rate_limit_key = :key AND created_at >= :window_start',
        );
        $statement->execute([
            'key' => $key,
            'window_start' => $windowStart->format(DATE_ATOM),
        ]);

        return (int) $statement->fetchColumn();
    }
}
