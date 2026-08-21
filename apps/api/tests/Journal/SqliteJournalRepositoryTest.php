<?php

declare(strict_types=1);

namespace App\Tests\Journal;

use App\Journal\JournalEntry;
use App\Journal\SqliteJournalRepository;
use PDO;
use PHPUnit\Framework\TestCase;

final class SqliteJournalRepositoryTest extends TestCase
{
    private PDO $pdo;
    private SqliteJournalRepository $repository;

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

        $this->repository = new SqliteJournalRepository($this->pdo);
    }

    public function testFindAllReturnsEmptyArrayWhenThereAreNoEntries(): void
    {
        self::assertSame([], $this->repository->findAll());
    }

    public function testSaveAndFindAllRoundTripsAnEntry(): void
    {
        $entry = new JournalEntry('entry-1', 'A reflection.', new \DateTimeImmutable('2026-08-14T10:00:00+00:00'));
        $this->repository->save($entry);

        $found = $this->repository->findAll();

        self::assertCount(1, $found);
        self::assertSame('entry-1', $found[0]->id);
        self::assertSame('A reflection.', $found[0]->text);
        self::assertSame('2026-08-14T10:00:00+00:00', $found[0]->createdAt->format(DATE_ATOM));
    }

    public function testFindAllReturnsEntriesNewestFirst(): void
    {
        $this->repository->save(new JournalEntry('entry-1', 'First.', new \DateTimeImmutable('2026-08-14T10:00:00+00:00')));
        $this->repository->save(new JournalEntry('entry-2', 'Second.', new \DateTimeImmutable('2026-08-15T10:00:00+00:00')));
        $this->repository->save(new JournalEntry('entry-3', 'Third.', new \DateTimeImmutable('2026-08-16T10:00:00+00:00')));

        $found = $this->repository->findAll();

        self::assertSame(['entry-3', 'entry-2', 'entry-1'], array_map(
            static fn (JournalEntry $e): string => $e->id,
            $found,
        ));
    }
}
