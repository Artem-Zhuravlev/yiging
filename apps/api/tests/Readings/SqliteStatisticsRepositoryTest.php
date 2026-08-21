<?php

declare(strict_types=1);

namespace App\Tests\Readings;

use App\Readings\CastingMethodName;
use App\Readings\Consultation;
use App\Readings\SqliteConsultationRepository;
use App\Readings\SqliteStatisticsRepository;
use App\Tests\Readings\Support\HexagramFixture;
use PDO;
use PHPUnit\Framework\TestCase;

final class SqliteStatisticsRepositoryTest extends TestCase
{
    use HexagramFixture;

    private PDO $pdo;
    private SqliteConsultationRepository $consultations;
    private SqliteStatisticsRepository $statistics;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->pdo->exec('PRAGMA foreign_keys = ON');

        $migrationsDir = dirname(__DIR__, 2) . '/database/migrations';
        $files = glob($migrationsDir . '/*.php') ?: [];
        sort($files);

        foreach ($files as $file) {
            /** @var array{up: string} $migration */
            $migration = require $file;
            $this->pdo->exec($migration['up']);
        }

        $this->consultations = new SqliteConsultationRepository($this->pdo);
        $this->statistics = new SqliteStatisticsRepository($this->pdo);
    }

    public function testComputeOnAnEmptyHistoryReturnsAllZeroesAndEmptyLists(): void
    {
        $statistics = $this->statistics->compute();

        self::assertSame(0, $statistics->totalConsultations);
        self::assertSame([], $statistics->hexagramFrequency);
        self::assertSame(0, $statistics->yinLineCount);
        self::assertSame(0, $statistics->yangLineCount);
        self::assertSame([], $statistics->tagFrequency);
    }

    public function testComputeCountsHexagramFrequencyAndYinYangAcrossPrimaryHexagrams(): void
    {
        // Hexagram 1 (all yang) twice, hexagram 2 (all yin) once.
        $this->consultations->save(Consultation::create(
            'consult-1',
            'First?',
            CastingMethodName::ThreeCoins,
            self::hexagramFromPattern('111111'),
            new \DateTimeImmutable('2026-08-14T10:00:00+00:00'),
        ));
        $this->consultations->save(Consultation::create(
            'consult-2',
            'Second?',
            CastingMethodName::ThreeCoins,
            self::hexagramFromPattern('111111'),
            new \DateTimeImmutable('2026-08-15T10:00:00+00:00'),
        ));
        $this->consultations->save(Consultation::create(
            'consult-3',
            'Third?',
            CastingMethodName::ThreeCoins,
            self::hexagramFromPattern('000000'),
            new \DateTimeImmutable('2026-08-16T10:00:00+00:00'),
        ));

        $statistics = $this->statistics->compute();

        self::assertSame(3, $statistics->totalConsultations);
        self::assertCount(2, $statistics->hexagramFrequency);
        self::assertSame(1, $statistics->hexagramFrequency[0]->kingWenNumber);
        self::assertSame(2, $statistics->hexagramFrequency[0]->count);
        self::assertSame(2, $statistics->hexagramFrequency[1]->kingWenNumber);
        self::assertSame(1, $statistics->hexagramFrequency[1]->count);

        // 2 x all-yang (12 yang lines) + 1 x all-yin (6 yin lines).
        self::assertSame(6, $statistics->yinLineCount);
        self::assertSame(12, $statistics->yangLineCount);
        self::assertSame(18, $statistics->yinLineCount + $statistics->yangLineCount);
    }

    public function testComputeCountsTagFrequencyAcrossConsultations(): void
    {
        $first = Consultation::create(
            'consult-1',
            'First?',
            CastingMethodName::ThreeCoins,
            self::hexagramFromPattern('111111'),
            new \DateTimeImmutable('2026-08-14T10:00:00+00:00'),
        )->withAddedTag('career');
        $second = Consultation::create(
            'consult-2',
            'Second?',
            CastingMethodName::ThreeCoins,
            self::hexagramFromPattern('111111'),
            new \DateTimeImmutable('2026-08-15T10:00:00+00:00'),
        )->withAddedTag('career')->withAddedTag('health');

        $this->consultations->save($first);
        $this->consultations->save($second);

        $statistics = $this->statistics->compute();

        self::assertCount(2, $statistics->tagFrequency);
        self::assertSame('career', $statistics->tagFrequency[0]->name);
        self::assertSame(2, $statistics->tagFrequency[0]->count);
        self::assertSame('health', $statistics->tagFrequency[1]->name);
        self::assertSame(1, $statistics->tagFrequency[1]->count);
    }
}
