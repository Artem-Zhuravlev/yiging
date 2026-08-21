<?php

declare(strict_types=1);

namespace App\Tests\AI;

use App\AI\InterpretationProfile;
use App\AI\ResponseLength;
use App\AI\SqliteInterpretationProfileRepository;
use App\AI\Tone;
use PDO;
use PHPUnit\Framework\TestCase;

final class SqliteInterpretationProfileRepositoryTest extends TestCase
{
    private PDO $pdo;
    private SqliteInterpretationProfileRepository $repository;

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

        $this->repository = new SqliteInterpretationProfileRepository($this->pdo);
    }

    public function testGetReturnsTheDefaultProfileWhenNothingHasEverBeenSaved(): void
    {
        $profile = $this->repository->get();

        self::assertTrue($profile->isDefault());
        self::assertSame(Tone::Neutral, $profile->tone);
        self::assertSame(ResponseLength::Standard, $profile->length);
        self::assertNull($profile->notes);
    }

    public function testSaveAndGetRoundTripsANonDefaultProfile(): void
    {
        $this->repository->save(new InterpretationProfile(Tone::Poetic, ResponseLength::Brief, 'Be vivid.'));

        $profile = $this->repository->get();

        self::assertSame(Tone::Poetic, $profile->tone);
        self::assertSame(ResponseLength::Brief, $profile->length);
        self::assertSame('Be vivid.', $profile->notes);
    }

    public function testSaveTwiceUpdatesTheSameSingletonRowRatherThanInserting(): void
    {
        $this->repository->save(new InterpretationProfile(Tone::Formal));
        $this->repository->save(new InterpretationProfile(Tone::Casual));

        self::assertSame(Tone::Casual, $this->repository->get()->tone);

        $statement = $this->pdo->prepare('SELECT COUNT(*) FROM interpretation_profile');
        $statement->execute();
        self::assertSame(1, (int) $statement->fetchColumn());
    }
}
