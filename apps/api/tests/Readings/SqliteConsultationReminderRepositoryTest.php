<?php

declare(strict_types=1);

namespace App\Tests\Readings;

use App\Readings\CastingMethodName;
use App\Readings\Consultation;
use App\Readings\SqliteConsultationReminderRepository;
use App\Readings\SqliteConsultationRepository;
use App\Tests\Readings\Support\HexagramFixture;
use PDO;
use PHPUnit\Framework\TestCase;

final class SqliteConsultationReminderRepositoryTest extends TestCase
{
    use HexagramFixture;

    private PDO $pdo;
    private SqliteConsultationRepository $consultations;
    private SqliteConsultationReminderRepository $reminders;

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
        $this->reminders = new SqliteConsultationReminderRepository($this->pdo);
    }

    private function saveConsultation(string $id, ?string $outcome = null): void
    {
        $consultation = Consultation::create(
            $id,
            'Question ' . $id,
            CastingMethodName::ThreeCoins,
            self::hexagramFromPattern('101010'),
            new \DateTimeImmutable('2026-08-14T10:00:00+00:00'),
        );

        if ($outcome !== null) {
            $consultation = $consultation->withUpdatedOutcome(
                null,
                $outcome,
                null,
                new \DateTimeImmutable('2026-08-20T10:00:00+00:00'),
            );
        }

        $this->consultations->save($consultation);
    }

    public function testSetAndFindRemindAtRoundTrips(): void
    {
        $this->saveConsultation('c1');

        $this->reminders->set(
            'c1',
            new \DateTimeImmutable('2026-09-15T00:00:00+00:00'),
            new \DateTimeImmutable('2026-09-01T12:00:00+00:00'),
        );

        $found = $this->reminders->findRemindAt('c1');
        self::assertNotNull($found);
        self::assertSame('2026-09-15', $found->format('Y-m-d'));
        self::assertNull($this->reminders->findRemindAt('unknown'));
    }

    public function testSetReplacesTheDateButKeepsCreatedAt(): void
    {
        $this->saveConsultation('c1');

        $this->reminders->set(
            'c1',
            new \DateTimeImmutable('2026-09-15T00:00:00+00:00'),
            new \DateTimeImmutable('2026-09-01T12:00:00+00:00'),
        );
        $this->reminders->set(
            'c1',
            new \DateTimeImmutable('2026-10-20T00:00:00+00:00'),
            new \DateTimeImmutable('2026-09-10T12:00:00+00:00'),
        );

        $found = $this->reminders->findRemindAt('c1');
        self::assertNotNull($found);
        self::assertSame('2026-10-20', $found->format('Y-m-d'));

        $statement = $this->pdo->prepare(
            'SELECT created_at FROM consultation_reminders WHERE consultation_id = :id',
        );
        $statement->execute(['id' => 'c1']);
        self::assertStringStartsWith('2026-09-01', (string) $statement->fetchColumn());
    }

    public function testClearRemovesTheReminderAndIsANoOpWhenAbsent(): void
    {
        $this->saveConsultation('c1');
        $this->reminders->set(
            'c1',
            new \DateTimeImmutable('2026-09-15T00:00:00+00:00'),
            new \DateTimeImmutable('2026-09-01T12:00:00+00:00'),
        );

        $this->reminders->clear('c1');
        self::assertNull($this->reminders->findRemindAt('c1'));

        $this->reminders->clear('c1');
        $this->reminders->clear('never-had-one');
        self::assertNull($this->reminders->findRemindAt('c1'));
    }

    public function testFindDueReturnsOnlyPastDueOutcomeLessOrderedByRemindAt(): void
    {
        $this->saveConsultation('earlier');
        $this->saveConsultation('later');
        $this->saveConsultation('future');
        $this->saveConsultation('recorded', outcome: 'It went fine.');

        $created = new \DateTimeImmutable('2026-08-01T00:00:00+00:00');
        $this->reminders->set('earlier', new \DateTimeImmutable('2026-08-10T00:00:00+00:00'), $created);
        $this->reminders->set('later', new \DateTimeImmutable('2026-08-20T00:00:00+00:00'), $created);
        $this->reminders->set('future', new \DateTimeImmutable('2027-01-01T00:00:00+00:00'), $created);
        $this->reminders->set('recorded', new \DateTimeImmutable('2026-08-05T00:00:00+00:00'), $created);

        $due = $this->reminders->findDue(new \DateTimeImmutable('2026-09-01T00:00:00+00:00'));

        self::assertCount(2, $due);
        self::assertSame('earlier', $due[0]->id);
        self::assertSame('later', $due[1]->id);
        self::assertSame('Question earlier', $due[0]->question);
        self::assertSame(63, $due[0]->primaryKingWenNumber);
    }

    public function testFindDueDropsARowOnceItsConsultationGainsAnOutcome(): void
    {
        $this->saveConsultation('c1');
        $this->reminders->set(
            'c1',
            new \DateTimeImmutable('2026-08-10T00:00:00+00:00'),
            new \DateTimeImmutable('2026-08-01T00:00:00+00:00'),
        );

        self::assertCount(1, $this->reminders->findDue(new \DateTimeImmutable('2026-09-01T00:00:00+00:00')));

        $this->saveConsultation('c1', outcome: 'Resolved.');

        self::assertSame([], $this->reminders->findDue(new \DateTimeImmutable('2026-09-01T00:00:00+00:00')));
    }
}
