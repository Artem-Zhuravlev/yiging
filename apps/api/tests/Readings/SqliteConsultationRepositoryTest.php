<?php

declare(strict_types=1);

namespace App\Tests\Readings;

use App\Readings\CastingMethodName;
use App\Readings\Consultation;
use App\Readings\ConsultationNote;
use App\Readings\ConsultationSummary;
use App\Readings\NoteLabel;
use App\Readings\SqliteConsultationRepository;
use App\Tests\Readings\Support\HexagramFixture;
use PDO;
use PHPUnit\Framework\TestCase;

final class SqliteConsultationRepositoryTest extends TestCase
{
    use HexagramFixture;

    private PDO $pdo;
    private SqliteConsultationRepository $repository;

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

        $this->repository = new SqliteConsultationRepository($this->pdo);
    }

    public function testSaveAndFindByIdRoundTripsANonTrivialConsultation(): void
    {
        // Hexagram 63 (Ji Ji) with lines 1 and 4 changing.
        $primary = self::hexagramFromPattern('101010', changingPositions: [1, 4]);

        $consultation = Consultation::create(
            'consult-1',
            'Should I take the offer?',
            CastingMethodName::ThreeCoins,
            $primary,
            new \DateTimeImmutable('2026-08-14T10:00:00+00:00'),
        )
            ->withAddedNote(new ConsultationNote(
                NoteLabel::Before,
                'Feeling uncertain.',
                new \DateTimeImmutable('2026-08-14T09:55:00+00:00'),
            ))
            ->withAddedNote(new ConsultationNote(
                NoteLabel::After,
                'Took the offer.',
                new \DateTimeImmutable('2026-08-14T10:05:00+00:00'),
            ))
            ->withAddedTag('career')
            ->withAddedTag('big-decision');

        $this->repository->save($consultation);
        $found = $this->repository->findById('consult-1');

        self::assertNotNull($found);
        self::assertSame($consultation->id, $found->id);
        self::assertSame($consultation->question, $found->question);
        self::assertSame($consultation->method, $found->method);
        self::assertSame($consultation->primaryHexagram->kingWenNumber, $found->primaryHexagram->kingWenNumber);
        self::assertSame($consultation->changingLinePositions(), $found->changingLinePositions());
        self::assertSame($consultation->resultingHexagram->kingWenNumber, $found->resultingHexagram->kingWenNumber);
        self::assertSame(
            $consultation->createdAt->format(DATE_ATOM),
            $found->createdAt->format(DATE_ATOM),
        );

        self::assertCount(2, $found->notes);
        self::assertSame(NoteLabel::Before, $found->notes[0]->label);
        self::assertSame('Feeling uncertain.', $found->notes[0]->text);
        self::assertSame(NoteLabel::After, $found->notes[1]->label);
        self::assertSame('Took the offer.', $found->notes[1]->text);

        self::assertSame(['big-decision', 'career'], $found->tags);
    }

    public function testSaveAndFindByIdRoundTripsAllFiveContextFields(): void
    {
        $consultation = Consultation::create(
            'consult-1',
            'Should I take the offer?',
            CastingMethodName::ThreeCoins,
            self::hexagramFromPattern('111111'),
            new \DateTimeImmutable('2026-08-14T10:00:00+00:00'),
            context: 'Some context.',
            whatHappenedBefore: 'Received an offer.',
            whatUserWantsToUnderstand: 'Timing.',
            backgroundInformation: 'Background.',
            initialInterpretation: 'Leaning yes.',
        );

        $this->repository->save($consultation);
        $found = $this->repository->findById('consult-1');

        self::assertNotNull($found);
        self::assertSame('Some context.', $found->context);
        self::assertSame('Received an offer.', $found->whatHappenedBefore);
        self::assertSame('Timing.', $found->whatUserWantsToUnderstand);
        self::assertSame('Background.', $found->backgroundInformation);
        self::assertSame('Leaning yes.', $found->initialInterpretation);
    }

    public function testAConsultationWithNoContextFieldsRoundTripsAllFiveAsNull(): void
    {
        // Simulates a pre-SPEC-019 consultation: none of the five new columns were ever set.
        $consultation = Consultation::create(
            'consult-1',
            'Should I take the offer?',
            CastingMethodName::ThreeCoins,
            self::hexagramFromPattern('111111'),
            new \DateTimeImmutable('2026-08-14T10:00:00+00:00'),
        );

        $this->repository->save($consultation);
        $found = $this->repository->findById('consult-1');

        self::assertNotNull($found);
        self::assertNull($found->context);
        self::assertNull($found->whatHappenedBefore);
        self::assertNull($found->whatUserWantsToUnderstand);
        self::assertNull($found->backgroundInformation);
        self::assertNull($found->initialInterpretation);
    }

    public function testAConsultationWithNoOutcomeRoundTripsAsNull(): void
    {
        $consultation = Consultation::create(
            'consult-1',
            'Should I take the offer?',
            CastingMethodName::ThreeCoins,
            self::hexagramFromPattern('111111'),
            new \DateTimeImmutable('2026-08-14T10:00:00+00:00'),
        );

        $this->repository->save($consultation);
        $found = $this->repository->findById('consult-1');

        self::assertNotNull($found);
        self::assertNull($found->outcome);
    }

    public function testSaveAndFindByIdRoundTripsAnOutcome(): void
    {
        $consultation = Consultation::create(
            'consult-1',
            'Should I take the offer?',
            CastingMethodName::ThreeCoins,
            self::hexagramFromPattern('111111'),
            new \DateTimeImmutable('2026-08-14T10:00:00+00:00'),
        )->withUpdatedOutcome(
            'Took the offer.',
            'Started two weeks later, going well.',
            'Glad I trusted the reading.',
            new \DateTimeImmutable('2026-08-20T09:00:00+00:00'),
        );

        $this->repository->save($consultation);
        $found = $this->repository->findById('consult-1');

        self::assertNotNull($found);
        self::assertNotNull($found->outcome);
        self::assertSame('Took the offer.', $found->outcome->whatActuallyHappened);
        self::assertSame('Started two weeks later, going well.', $found->outcome->outcome);
        self::assertSame('Glad I trusted the reading.', $found->outcome->reflection);
        self::assertSame(
            '2026-08-20T09:00:00+00:00',
            $found->outcome->recordedAt->format(DATE_ATOM),
        );
    }

    public function testSaveUpsertsAnExistingOutcomeRatherThanDuplicatingTheRow(): void
    {
        $consultation = Consultation::create(
            'consult-1',
            'Should I take the offer?',
            CastingMethodName::ThreeCoins,
            self::hexagramFromPattern('111111'),
            new \DateTimeImmutable('2026-08-14T10:00:00+00:00'),
        )->withUpdatedOutcome('First version.', null, null, new \DateTimeImmutable('2026-08-20T09:00:00+00:00'));
        $this->repository->save($consultation);

        $updated = $consultation->withUpdatedOutcome(
            'Second version.',
            null,
            null,
            new \DateTimeImmutable('2026-08-21T09:00:00+00:00'),
        );
        $this->repository->save($updated);

        $found = $this->repository->findById('consult-1');
        self::assertNotNull($found);
        self::assertNotNull($found->outcome);
        self::assertSame('Second version.', $found->outcome->whatActuallyHappened);

        $countStatement = $this->pdo->query('SELECT COUNT(*) FROM consultation_outcomes');
        self::assertNotFalse($countStatement);
        self::assertSame(1, (int) $countStatement->fetchColumn());
    }

    public function testSavingAConsultationWithoutTouchingOutcomeWritesNoRow(): void
    {
        // Adding a note (a save() call where $consultation->outcome stays null) must not create
        // a stray consultation_outcomes row.
        $consultation = Consultation::create(
            'consult-1',
            'Should I take the offer?',
            CastingMethodName::ThreeCoins,
            self::hexagramFromPattern('111111'),
            new \DateTimeImmutable('2026-08-14T10:00:00+00:00'),
        )->withAddedTag('career');

        $this->repository->save($consultation);

        $countStatement = $this->pdo->query('SELECT COUNT(*) FROM consultation_outcomes');
        self::assertNotFalse($countStatement);
        self::assertSame(0, (int) $countStatement->fetchColumn());
    }

    public function testSaveAndFindByIdRoundTripsAFollowUpLink(): void
    {
        $original = Consultation::create(
            'consult-1',
            'Should I take the offer?',
            CastingMethodName::ThreeCoins,
            self::hexagramFromPattern('111111'),
            new \DateTimeImmutable('2026-08-14T10:00:00+00:00'),
        );
        $this->repository->save($original);

        $followUp = Consultation::create(
            'consult-2',
            'Did I make the right call?',
            CastingMethodName::ThreeCoins,
            self::hexagramFromPattern('111111'),
            new \DateTimeImmutable('2026-08-15T10:00:00+00:00'),
            followUpToConsultationId: 'consult-1',
        );
        $this->repository->save($followUp);

        $found = $this->repository->findById('consult-2');
        self::assertNotNull($found);
        self::assertSame('consult-1', $found->followUpToConsultationId);
    }

    public function testAConsultationWithNoFollowUpLinkRoundTripsAsNull(): void
    {
        $consultation = Consultation::create(
            'consult-1',
            'Should I take the offer?',
            CastingMethodName::ThreeCoins,
            self::hexagramFromPattern('111111'),
            new \DateTimeImmutable('2026-08-14T10:00:00+00:00'),
        );
        $this->repository->save($consultation);

        $found = $this->repository->findById('consult-1');
        self::assertNotNull($found);
        self::assertNull($found->followUpToConsultationId);
    }

    public function testFindSummaryByIdReturnsIdAndQuestion(): void
    {
        $consultation = Consultation::create(
            'consult-1',
            'Should I take the offer?',
            CastingMethodName::ThreeCoins,
            self::hexagramFromPattern('111111'),
            new \DateTimeImmutable('2026-08-14T10:00:00+00:00'),
        );
        $this->repository->save($consultation);

        $summary = $this->repository->findSummaryById('consult-1');

        self::assertNotNull($summary);
        self::assertSame('consult-1', $summary->id);
        self::assertSame('Should I take the offer?', $summary->question);
    }

    public function testFindSummaryByIdReturnsNullForAMissingConsultation(): void
    {
        self::assertNull($this->repository->findSummaryById('does-not-exist'));
    }

    public function testFindFollowUpSummariesListsThemOldestFirst(): void
    {
        $original = Consultation::create(
            'consult-1',
            'Should I take the offer?',
            CastingMethodName::ThreeCoins,
            self::hexagramFromPattern('111111'),
            new \DateTimeImmutable('2026-08-14T10:00:00+00:00'),
        );
        $this->repository->save($original);

        $this->repository->save(Consultation::create(
            'consult-3',
            'Third question?',
            CastingMethodName::ThreeCoins,
            self::hexagramFromPattern('111111'),
            new \DateTimeImmutable('2026-08-16T10:00:00+00:00'),
            followUpToConsultationId: 'consult-1',
        ));
        $this->repository->save(Consultation::create(
            'consult-2',
            'Second question?',
            CastingMethodName::ThreeCoins,
            self::hexagramFromPattern('111111'),
            new \DateTimeImmutable('2026-08-15T10:00:00+00:00'),
            followUpToConsultationId: 'consult-1',
        ));

        $followUps = $this->repository->findFollowUpSummaries('consult-1');

        self::assertCount(2, $followUps);
        self::assertSame('consult-2', $followUps[0]->id);
        self::assertSame('consult-3', $followUps[1]->id);
    }

    public function testFindFollowUpSummariesReturnsEmptyArrayWhenThereAreNone(): void
    {
        $consultation = Consultation::create(
            'consult-1',
            'Should I take the offer?',
            CastingMethodName::ThreeCoins,
            self::hexagramFromPattern('111111'),
            new \DateTimeImmutable('2026-08-14T10:00:00+00:00'),
        );
        $this->repository->save($consultation);

        self::assertSame([], $this->repository->findFollowUpSummaries('consult-1'));
    }

    public function testFindByPrimaryHexagramNumberListsOtherMatchesNewestFirstExcludingSelf(): void
    {
        $this->repository->save(Consultation::create(
            'consult-1',
            'First?',
            CastingMethodName::ThreeCoins,
            self::hexagramFromPattern('111111'),
            new \DateTimeImmutable('2026-08-14T10:00:00+00:00'),
        ));
        $this->repository->save(Consultation::create(
            'consult-2',
            'Second, same primary hexagram?',
            CastingMethodName::ThreeCoins,
            self::hexagramFromPattern('111111'),
            new \DateTimeImmutable('2026-08-16T10:00:00+00:00'),
        ));
        $this->repository->save(Consultation::create(
            'consult-3',
            'Third, different primary hexagram?',
            CastingMethodName::ThreeCoins,
            self::hexagramFromPattern('000000'),
            new \DateTimeImmutable('2026-08-17T10:00:00+00:00'),
        ));

        $matches = $this->repository->findByPrimaryHexagramNumber(1, 'consult-1');

        self::assertCount(1, $matches);
        self::assertSame('consult-2', $matches[0]->id);
    }

    public function testFindByResultingHexagramNumberListsOtherMatches(): void
    {
        $this->repository->save(Consultation::create(
            'consult-1',
            'First?',
            CastingMethodName::ThreeCoins,
            self::hexagramFromPattern('111111', [1]),
            new \DateTimeImmutable('2026-08-14T10:00:00+00:00'),
        ));
        $this->repository->save(Consultation::create(
            'consult-2',
            'Second, same resulting hexagram?',
            CastingMethodName::ThreeCoins,
            self::hexagramFromPattern('111111', [1]),
            new \DateTimeImmutable('2026-08-15T10:00:00+00:00'),
        ));

        $resultingKingWenNumber = Consultation::create(
            'probe',
            'probe',
            CastingMethodName::ThreeCoins,
            self::hexagramFromPattern('111111', [1]),
            new \DateTimeImmutable('2026-08-14T10:00:00+00:00'),
        )->resultingHexagram->kingWenNumber;

        $matches = $this->repository->findByResultingHexagramNumber($resultingKingWenNumber, 'consult-1');

        self::assertCount(1, $matches);
        self::assertSame('consult-2', $matches[0]->id);
    }

    public function testFindByChangingLinePositionsMatchesTheExactSetOnly(): void
    {
        $this->repository->save(Consultation::create(
            'consult-1',
            'First?',
            CastingMethodName::ThreeCoins,
            self::hexagramFromPattern('111111', [1, 4]),
            new \DateTimeImmutable('2026-08-14T10:00:00+00:00'),
        ));
        $this->repository->save(Consultation::create(
            'consult-2',
            'Second, same changing lines?',
            CastingMethodName::ThreeCoins,
            self::hexagramFromPattern('000000', [1, 4]),
            new \DateTimeImmutable('2026-08-15T10:00:00+00:00'),
        ));
        $this->repository->save(Consultation::create(
            'consult-3',
            'Third, different changing lines?',
            CastingMethodName::ThreeCoins,
            self::hexagramFromPattern('111111', [2]),
            new \DateTimeImmutable('2026-08-16T10:00:00+00:00'),
        ));

        $matches = $this->repository->findByChangingLinePositions([1, 4], 'consult-1');

        self::assertCount(1, $matches);
        self::assertSame('consult-2', $matches[0]->id);
    }

    public function testSaveAndFindByIdRoundTripsAFavoriteFlag(): void
    {
        $consultation = Consultation::create(
            'consult-1',
            'Should I take the offer?',
            CastingMethodName::ThreeCoins,
            self::hexagramFromPattern('111111'),
            new \DateTimeImmutable('2026-08-14T10:00:00+00:00'),
        )->withFavorite(true);
        $this->repository->save($consultation);

        $found = $this->repository->findById('consult-1');

        self::assertNotNull($found);
        self::assertTrue($found->favorite);
    }

    public function testAConsultationWithNoFavoriteFlagRoundTripsAsFalse(): void
    {
        $this->repository->save(Consultation::create(
            'consult-1',
            'Should I take the offer?',
            CastingMethodName::ThreeCoins,
            self::hexagramFromPattern('111111'),
            new \DateTimeImmutable('2026-08-14T10:00:00+00:00'),
        ));

        $found = $this->repository->findById('consult-1');

        self::assertNotNull($found);
        self::assertFalse($found->favorite);
    }

    public function testExistsByIdReflectsWhetherAConsultationHasBeenSaved(): void
    {
        self::assertFalse($this->repository->existsById('consult-1'));

        $this->repository->save(Consultation::create(
            'consult-1',
            'Should I take the offer?',
            CastingMethodName::ThreeCoins,
            self::hexagramFromPattern('111111'),
            new \DateTimeImmutable('2026-08-14T10:00:00+00:00'),
        ));

        self::assertTrue($this->repository->existsById('consult-1'));
    }

    public function testSaveImportBatchInsertsEveryConsultationAndResolvesFollowUpLinksInEitherOrder(): void
    {
        $original = Consultation::create(
            'consult-1',
            'Should I take the offer?',
            CastingMethodName::ThreeCoins,
            self::hexagramFromPattern('111111'),
            new \DateTimeImmutable('2026-08-14T10:00:00+00:00'),
        );
        // Forward reference: consult-2 (earlier in the batch) links to consult-1 (later in the
        // batch) — must resolve regardless of array order.
        $followUp = Consultation::create(
            'consult-2',
            'Did I make the right call?',
            CastingMethodName::ThreeCoins,
            self::hexagramFromPattern('111111'),
            new \DateTimeImmutable('2026-08-15T10:00:00+00:00'),
            followUpToConsultationId: 'consult-1',
        );

        $this->repository->saveImportBatch([$followUp, $original]);

        $foundOriginal = $this->repository->findById('consult-1');
        $foundFollowUp = $this->repository->findById('consult-2');

        self::assertNotNull($foundOriginal);
        self::assertNotNull($foundFollowUp);
        self::assertSame('consult-1', $foundFollowUp->followUpToConsultationId);
        self::assertSame([$foundFollowUp->id], array_map(
            static fn (ConsultationSummary $s): string => $s->id,
            $this->repository->findFollowUpSummaries('consult-1'),
        ));
    }

    public function testFindByIdReturnsNullForAMissingConsultation(): void
    {
        self::assertNull($this->repository->findById('does-not-exist'));
    }

    public function testSaveUpsertsAnExistingConsultation(): void
    {
        $original = Consultation::create(
            'consult-1',
            'Original question?',
            CastingMethodName::Manual,
            self::hexagramFromPattern('111111'),
            new \DateTimeImmutable('2026-08-14T10:00:00+00:00'),
        )->withAddedTag('first-tag');

        $this->repository->save($original);

        $updated = Consultation::reconstitute(
            'consult-1',
            'Updated question?',
            CastingMethodName::Manual,
            self::hexagramFromPattern('111111'),
            self::hexagramFromPattern('111111'),
            new \DateTimeImmutable('2026-08-14T10:00:00+00:00'),
            [],
            ['second-tag'],
        );

        $this->repository->save($updated);

        $found = $this->repository->findById('consult-1');
        self::assertNotNull($found);
        self::assertSame('Updated question?', $found->question);
        self::assertSame(['second-tag'], $found->tags);

        self::assertCount(1, $this->repository->findAll(), 'upsert must not create a duplicate row');
    }

    public function testFindAllReturnsConsultationsNewestFirst(): void
    {
        $older = Consultation::create(
            'consult-older',
            'Older question?',
            CastingMethodName::Manual,
            self::hexagramFromPattern('111111'),
            new \DateTimeImmutable('2026-08-01T00:00:00+00:00'),
        );
        $newer = Consultation::create(
            'consult-newer',
            'Newer question?',
            CastingMethodName::Manual,
            self::hexagramFromPattern('111111'),
            new \DateTimeImmutable('2026-08-10T00:00:00+00:00'),
        );

        $this->repository->save($older);
        $this->repository->save($newer);

        $all = $this->repository->findAll();

        self::assertCount(2, $all);
        self::assertSame('consult-newer', $all[0]->id);
        self::assertSame('consult-older', $all[1]->id);
    }
}
