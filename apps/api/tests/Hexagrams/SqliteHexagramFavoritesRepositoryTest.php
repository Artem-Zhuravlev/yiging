<?php

declare(strict_types=1);

namespace App\Tests\Hexagrams;

use App\Hexagrams\SqliteHexagramFavoritesRepository;
use PDO;
use PHPUnit\Framework\TestCase;

final class SqliteHexagramFavoritesRepositoryTest extends TestCase
{
    private PDO $pdo;
    private SqliteHexagramFavoritesRepository $repository;

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

        $this->repository = new SqliteHexagramFavoritesRepository($this->pdo);
    }

    public function testIsFavoriteIsFalseByDefault(): void
    {
        self::assertFalse($this->repository->isFavorite(1));
    }

    public function testAddMarksAHexagramFavorite(): void
    {
        $this->repository->add(1);

        self::assertTrue($this->repository->isFavorite(1));
    }

    public function testAddIsIdempotent(): void
    {
        $this->repository->add(1);
        $this->repository->add(1);

        self::assertSame([1], $this->repository->allFavoriteNumbers());
    }

    public function testRemoveUnmarksAHexagram(): void
    {
        $this->repository->add(1);
        $this->repository->remove(1);

        self::assertFalse($this->repository->isFavorite(1));
    }

    public function testRemoveOnANonFavoriteHexagramIsANoOp(): void
    {
        $this->repository->remove(1);

        self::assertFalse($this->repository->isFavorite(1));
    }

    public function testAllFavoriteNumbersListsEveryMarkedHexagram(): void
    {
        $this->repository->add(1);
        $this->repository->add(11);
        $this->repository->add(64);

        self::assertSame([1, 11, 64], $this->repository->allFavoriteNumbers());
    }
}
