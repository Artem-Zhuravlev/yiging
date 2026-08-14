<?php

declare(strict_types=1);

namespace App\Tests\AI;

use App\AI\SqliteRateLimiter;
use PDO;
use PHPUnit\Framework\TestCase;

final class SqliteRateLimiterTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        $migrationsDir = dirname(__DIR__, 2) . '/database/migrations';
        $files = glob($migrationsDir . '/*.php') ?: [];
        sort($files);

        foreach ($files as $file) {
            /** @var array{up: string} $migration */
            $migration = require $file;
            $this->pdo->exec($migration['up']);
        }
    }

    public function testAllowsExactlyMaxAttemptsPerKeyPerWindow(): void
    {
        $limiter = new SqliteRateLimiter($this->pdo, maxAttempts: 3, windowSeconds: 3600);

        self::assertTrue($limiter->attempt('1.2.3.4'));
        self::assertTrue($limiter->attempt('1.2.3.4'));
        self::assertTrue($limiter->attempt('1.2.3.4'));
        self::assertFalse($limiter->attempt('1.2.3.4'));
    }

    public function testDoesNotRecordAnAttemptWhenRejecting(): void
    {
        $limiter = new SqliteRateLimiter($this->pdo, maxAttempts: 1, windowSeconds: 3600);

        self::assertTrue($limiter->attempt('1.2.3.4'));
        self::assertFalse($limiter->attempt('1.2.3.4'));
        self::assertFalse($limiter->attempt('1.2.3.4'));

        $statement = $this->pdo->prepare('SELECT COUNT(*) FROM rate_limit_hits');
        $statement->execute();
        self::assertSame(1, (int) $statement->fetchColumn(), 'only the single allowed attempt should have been recorded');
    }

    public function testDifferentKeysHaveIndependentCounts(): void
    {
        $limiter = new SqliteRateLimiter($this->pdo, maxAttempts: 1, windowSeconds: 3600);

        self::assertTrue($limiter->attempt('1.2.3.4'));
        self::assertFalse($limiter->attempt('1.2.3.4'));

        self::assertTrue($limiter->attempt('5.6.7.8'), 'a different key must not be affected by the first');
    }

    public function testAttemptsOutsideTheWindowDoNotCount(): void
    {
        $limiter = new SqliteRateLimiter($this->pdo, maxAttempts: 1, windowSeconds: 3600);

        $old = (new \DateTimeImmutable('-2 hours'))->format(DATE_ATOM);
        $insert = $this->pdo->prepare('INSERT INTO rate_limit_hits (rate_limit_key, created_at) VALUES (:key, :created_at)');
        $insert->execute(['key' => '1.2.3.4', 'created_at' => $old]);

        self::assertTrue($limiter->attempt('1.2.3.4'), 'an attempt from 2 hours ago must be outside a 1-hour window');
    }

    public function testMaxAttemptsOfZeroRejectsEveryAttempt(): void
    {
        $limiter = new SqliteRateLimiter($this->pdo, maxAttempts: 0, windowSeconds: 3600);

        self::assertFalse($limiter->attempt('1.2.3.4'));
    }
}
