<?php

declare(strict_types=1);

namespace App\Tests\Readings;

use App\Readings\CastingMethodName;
use App\Readings\Consultation;
use App\Readings\ConsultationNote;
use App\Readings\ConsultationOutcome;
use App\Readings\NoteLabel;
use App\Tests\Readings\Support\HexagramFixture;
use PHPUnit\Framework\TestCase;

final class ConsultationTest extends TestCase
{
    use HexagramFixture;

    public function testCreateDerivesTheResultingHexagramAndStartsWithNoNotesOrTags(): void
    {
        // Hexagram 1 (all yang) with line 1 changing -> resulting Hexagram 44 (Gou).
        $primary = self::hexagramFromPattern('111111', changingPositions: [1]);

        $consultation = Consultation::create(
            'id-1',
            'Should I take the offer?',
            CastingMethodName::ThreeCoins,
            $primary,
            new \DateTimeImmutable('2026-08-14T12:00:00+00:00'),
        );

        self::assertSame(1, $consultation->primaryHexagram->kingWenNumber);
        self::assertSame(44, $consultation->resultingHexagram->kingWenNumber);
        self::assertSame([], $consultation->notes);
        self::assertSame([], $consultation->tags);
    }

    public function testCreateRejectsAnEmptyQuestion(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Consultation::create(
            'id-1',
            '   ',
            CastingMethodName::Manual,
            self::hexagramFromPattern('111111'),
            new \DateTimeImmutable(),
        );
    }

    public function testCreateAcceptsAQuestionAtExactlyTheLengthLimit(): void
    {
        $consultation = Consultation::create(
            'id-1',
            str_repeat('a', 2000),
            CastingMethodName::Manual,
            self::hexagramFromPattern('111111'),
            new \DateTimeImmutable(),
        );

        self::assertSame(2000, mb_strlen($consultation->question));
    }

    public function testCreateRejectsAQuestionOverTheLengthLimit(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Consultation::create(
            'id-1',
            str_repeat('a', 2001),
            CastingMethodName::Manual,
            self::hexagramFromPattern('111111'),
            new \DateTimeImmutable(),
        );
    }

    public function testCreateCountsQuestionLengthByCharacterNotByte(): void
    {
        // Each '乾' is 3 bytes in UTF-8; 2000 characters must be accepted, not rejected as
        // if it were ~6000 bytes.
        $consultation = Consultation::create(
            'id-1',
            str_repeat('乾', 2000),
            CastingMethodName::Manual,
            self::hexagramFromPattern('111111'),
            new \DateTimeImmutable(),
        );

        self::assertSame(2000, mb_strlen($consultation->question));
    }

    public function testWithAddedNoteReturnsANewInstanceAndAppends(): void
    {
        $consultation = Consultation::create(
            'id-1',
            'Should I take the offer?',
            CastingMethodName::Manual,
            self::hexagramFromPattern('111111'),
            new \DateTimeImmutable(),
        );

        $note = new ConsultationNote(NoteLabel::After, 'Took the offer.', new \DateTimeImmutable());
        $withNote = $consultation->withAddedNote($note);

        self::assertSame([], $consultation->notes, 'original instance must not be mutated');
        self::assertSame([$note], $withNote->notes);
    }

    public function testWithAddedTagReturnsANewInstanceAndAppends(): void
    {
        $consultation = Consultation::create(
            'id-1',
            'Should I take the offer?',
            CastingMethodName::Manual,
            self::hexagramFromPattern('111111'),
            new \DateTimeImmutable(),
        );

        $withTag = $consultation->withAddedTag('career');

        self::assertSame([], $consultation->tags, 'original instance must not be mutated');
        self::assertSame(['career'], $withTag->tags);
    }

    public function testWithAddedTagIsIdempotentForDuplicates(): void
    {
        $consultation = Consultation::create(
            'id-1',
            'Should I take the offer?',
            CastingMethodName::Manual,
            self::hexagramFromPattern('111111'),
            new \DateTimeImmutable(),
        )->withAddedTag('career');

        $again = $consultation->withAddedTag('career');

        self::assertSame(['career'], $again->tags);
    }

    public function testCreateAcceptsAllFiveOptionalContextFields(): void
    {
        $consultation = Consultation::create(
            'id-1',
            'Should I take the offer?',
            CastingMethodName::Manual,
            self::hexagramFromPattern('111111'),
            new \DateTimeImmutable(),
            context: 'Been considering this for weeks.',
            whatHappenedBefore: 'Received the offer last Tuesday.',
            whatUserWantsToUnderstand: 'Whether the timing is right.',
            backgroundInformation: 'Currently employed elsewhere.',
            initialInterpretation: 'Feels like a yes.',
        );

        self::assertSame('Been considering this for weeks.', $consultation->context);
        self::assertSame('Received the offer last Tuesday.', $consultation->whatHappenedBefore);
        self::assertSame('Whether the timing is right.', $consultation->whatUserWantsToUnderstand);
        self::assertSame('Currently employed elsewhere.', $consultation->backgroundInformation);
        self::assertSame('Feels like a yes.', $consultation->initialInterpretation);
    }

    public function testCreateDefaultsAllFiveContextFieldsToNull(): void
    {
        $consultation = Consultation::create(
            'id-1',
            'Should I take the offer?',
            CastingMethodName::Manual,
            self::hexagramFromPattern('111111'),
            new \DateTimeImmutable(),
        );

        self::assertNull($consultation->context);
        self::assertNull($consultation->whatHappenedBefore);
        self::assertNull($consultation->whatUserWantsToUnderstand);
        self::assertNull($consultation->backgroundInformation);
        self::assertNull($consultation->initialInterpretation);
    }

    public function testCreateRejectsAContextFieldOverTheLengthLimit(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Consultation::create(
            'id-1',
            'Should I take the offer?',
            CastingMethodName::Manual,
            self::hexagramFromPattern('111111'),
            new \DateTimeImmutable(),
            context: str_repeat('a', 5001),
        );
    }

    public function testCreateAcceptsAContextFieldAtExactlyTheLengthLimit(): void
    {
        $consultation = Consultation::create(
            'id-1',
            'Should I take the offer?',
            CastingMethodName::Manual,
            self::hexagramFromPattern('111111'),
            new \DateTimeImmutable(),
            context: str_repeat('a', 5000),
        );

        self::assertSame(5000, mb_strlen((string) $consultation->context));
    }

    public function testWithUpdatedContextSetsAndClearsFieldsIndependently(): void
    {
        $consultation = Consultation::create(
            'id-1',
            'Should I take the offer?',
            CastingMethodName::Manual,
            self::hexagramFromPattern('111111'),
            new \DateTimeImmutable(),
            context: 'Original context.',
            whatHappenedBefore: 'Original before.',
        );

        $updated = $consultation->withUpdatedContext(
            context: 'Updated context.',
            whatHappenedBefore: null,
            whatUserWantsToUnderstand: $consultation->whatUserWantsToUnderstand,
            backgroundInformation: $consultation->backgroundInformation,
            initialInterpretation: $consultation->initialInterpretation,
        );

        self::assertSame('Updated context.', $updated->context);
        self::assertNull($updated->whatHappenedBefore);
        self::assertSame('Original context.', $consultation->context, 'original instance must not be mutated');
    }

    public function testWithUpdatedContextValidatesLength(): void
    {
        $consultation = Consultation::create(
            'id-1',
            'Should I take the offer?',
            CastingMethodName::Manual,
            self::hexagramFromPattern('111111'),
            new \DateTimeImmutable(),
        );

        $this->expectException(\InvalidArgumentException::class);

        $consultation->withUpdatedContext(
            context: str_repeat('a', 5001),
            whatHappenedBefore: null,
            whatUserWantsToUnderstand: null,
            backgroundInformation: null,
            initialInterpretation: null,
        );
    }

    public function testWithAddedNotePreservesExistingContextFields(): void
    {
        // Regression: withAddedNote()/withAddedTag() rebuild via a positional constructor call
        // that must explicitly carry the five context fields through, or they'd silently reset
        // to null every time a note or tag was added.
        $consultation = Consultation::create(
            'id-1',
            'Should I take the offer?',
            CastingMethodName::Manual,
            self::hexagramFromPattern('111111'),
            new \DateTimeImmutable(),
            context: 'Some context.',
        );

        $note = new ConsultationNote(NoteLabel::After, 'Took the offer.', new \DateTimeImmutable());
        $withNote = $consultation->withAddedNote($note);
        $withTag = $consultation->withAddedTag('career');

        self::assertSame('Some context.', $withNote->context);
        self::assertSame('Some context.', $withTag->context);
    }

    public function testANewConsultationHasNoOutcome(): void
    {
        $consultation = Consultation::create(
            'id-1',
            'Should I take the offer?',
            CastingMethodName::Manual,
            self::hexagramFromPattern('111111'),
            new \DateTimeImmutable(),
        );

        self::assertNull($consultation->outcome);
    }

    public function testWithUpdatedOutcomeSetsAllThreeFieldsAndRecordedAt(): void
    {
        $consultation = Consultation::create(
            'id-1',
            'Should I take the offer?',
            CastingMethodName::Manual,
            self::hexagramFromPattern('111111'),
            new \DateTimeImmutable(),
        );

        $recordedAt = new \DateTimeImmutable('2026-08-15T12:00:00+00:00');
        $withOutcome = $consultation->withUpdatedOutcome(
            'Took the offer.',
            'Started two weeks later, going well.',
            'Glad I trusted the reading.',
            $recordedAt,
        );

        self::assertNull($consultation->outcome, 'original instance must not be mutated');
        self::assertNotNull($withOutcome->outcome);
        self::assertSame('Took the offer.', $withOutcome->outcome->whatActuallyHappened);
        self::assertSame('Started two weeks later, going well.', $withOutcome->outcome->outcome);
        self::assertSame('Glad I trusted the reading.', $withOutcome->outcome->reflection);
        self::assertSame($recordedAt, $withOutcome->outcome->recordedAt);
    }

    public function testWithUpdatedOutcomeCanLeaveIndividualFieldsNull(): void
    {
        $consultation = Consultation::create(
            'id-1',
            'Should I take the offer?',
            CastingMethodName::Manual,
            self::hexagramFromPattern('111111'),
            new \DateTimeImmutable(),
        );

        $withOutcome = $consultation->withUpdatedOutcome(
            'Took the offer.',
            null,
            null,
            new \DateTimeImmutable(),
        );

        self::assertNotNull($withOutcome->outcome);
        self::assertSame('Took the offer.', $withOutcome->outcome->whatActuallyHappened);
        self::assertNull($withOutcome->outcome->outcome);
        self::assertNull($withOutcome->outcome->reflection);
    }

    public function testWithUpdatedOutcomeValidatesFieldLength(): void
    {
        $consultation = Consultation::create(
            'id-1',
            'Should I take the offer?',
            CastingMethodName::Manual,
            self::hexagramFromPattern('111111'),
            new \DateTimeImmutable(),
        );

        $this->expectException(\InvalidArgumentException::class);

        $consultation->withUpdatedOutcome(str_repeat('a', 5001), null, null, new \DateTimeImmutable());
    }

    public function testWithAddedNotePreservesAnAlreadySetOutcome(): void
    {
        $consultation = Consultation::create(
            'id-1',
            'Should I take the offer?',
            CastingMethodName::Manual,
            self::hexagramFromPattern('111111'),
            new \DateTimeImmutable(),
        )->withUpdatedOutcome('Took the offer.', null, null, new \DateTimeImmutable());

        $note = new ConsultationNote(NoteLabel::After, 'Note text.', new \DateTimeImmutable());
        $withNote = $consultation->withAddedNote($note);

        self::assertSame('Took the offer.', $withNote->outcome?->whatActuallyHappened);
    }

    public function testWithAddedTagPreservesAnAlreadySetOutcome(): void
    {
        $consultation = Consultation::create(
            'id-1',
            'Should I take the offer?',
            CastingMethodName::Manual,
            self::hexagramFromPattern('111111'),
            new \DateTimeImmutable(),
        )->withUpdatedOutcome('Took the offer.', null, null, new \DateTimeImmutable());

        $withTag = $consultation->withAddedTag('career');

        self::assertSame('Took the offer.', $withTag->outcome?->whatActuallyHappened);
    }

    public function testWithUpdatedContextPreservesAnAlreadySetOutcome(): void
    {
        $consultation = Consultation::create(
            'id-1',
            'Should I take the offer?',
            CastingMethodName::Manual,
            self::hexagramFromPattern('111111'),
            new \DateTimeImmutable(),
        )->withUpdatedOutcome('Took the offer.', null, null, new \DateTimeImmutable());

        $withContext = $consultation->withUpdatedContext('New context.', null, null, null, null);

        self::assertSame('Took the offer.', $withContext->outcome?->whatActuallyHappened);
    }

    public function testWithUpdatedOutcomePreservesExistingContextFields(): void
    {
        $consultation = Consultation::create(
            'id-1',
            'Should I take the offer?',
            CastingMethodName::Manual,
            self::hexagramFromPattern('111111'),
            new \DateTimeImmutable(),
            context: 'Some context.',
        );

        $withOutcome = $consultation->withUpdatedOutcome('Took the offer.', null, null, new \DateTimeImmutable());

        self::assertSame('Some context.', $withOutcome->context);
    }

    public function testConsultationOutcomeAcceptsAFieldAtExactlyTheLengthLimit(): void
    {
        $outcome = new ConsultationOutcome(str_repeat('a', 5000), null, null, new \DateTimeImmutable());

        self::assertSame(5000, mb_strlen((string) $outcome->whatActuallyHappened));
    }

    public function testConsultationOutcomeRejectsAFieldOverTheLengthLimit(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ConsultationOutcome(null, str_repeat('a', 5001), null, new \DateTimeImmutable());
    }

    public function testANewConsultationHasNoFollowUpLink(): void
    {
        $consultation = Consultation::create(
            'id-1',
            'Should I take the offer?',
            CastingMethodName::Manual,
            self::hexagramFromPattern('111111'),
            new \DateTimeImmutable(),
        );

        self::assertNull($consultation->followUpToConsultationId);
    }

    public function testCreateAcceptsAFollowUpToConsultationId(): void
    {
        $consultation = Consultation::create(
            'id-2',
            'Did I make the right call?',
            CastingMethodName::Manual,
            self::hexagramFromPattern('111111'),
            new \DateTimeImmutable(),
            followUpToConsultationId: 'id-1',
        );

        self::assertSame('id-1', $consultation->followUpToConsultationId);
    }

    public function testWithFollowUpToSetsAndClearsTheLink(): void
    {
        $consultation = Consultation::create(
            'id-1',
            'Should I take the offer?',
            CastingMethodName::Manual,
            self::hexagramFromPattern('111111'),
            new \DateTimeImmutable(),
        );

        $linked = $consultation->withFollowUpTo('other-id');
        self::assertSame('other-id', $linked->followUpToConsultationId);
        self::assertNull($consultation->followUpToConsultationId, 'original instance must not be mutated');

        $cleared = $linked->withFollowUpTo(null);
        self::assertNull($cleared->followUpToConsultationId);
    }

    public function testWithFollowUpToRejectsSelfReference(): void
    {
        $consultation = Consultation::create(
            'id-1',
            'Should I take the offer?',
            CastingMethodName::Manual,
            self::hexagramFromPattern('111111'),
            new \DateTimeImmutable(),
        );

        $this->expectException(\InvalidArgumentException::class);

        $consultation->withFollowUpTo('id-1');
    }

    public function testWithAddedNotePreservesAnAlreadySetFollowUpLink(): void
    {
        $consultation = Consultation::create(
            'id-1',
            'Should I take the offer?',
            CastingMethodName::Manual,
            self::hexagramFromPattern('111111'),
            new \DateTimeImmutable(),
        )->withFollowUpTo('other-id');

        $withNote = $consultation->withAddedNote(new ConsultationNote(NoteLabel::After, 'Note.', new \DateTimeImmutable()));

        self::assertSame('other-id', $withNote->followUpToConsultationId);
    }

    public function testWithAddedTagPreservesAnAlreadySetFollowUpLink(): void
    {
        $consultation = Consultation::create(
            'id-1',
            'Should I take the offer?',
            CastingMethodName::Manual,
            self::hexagramFromPattern('111111'),
            new \DateTimeImmutable(),
        )->withFollowUpTo('other-id');

        self::assertSame('other-id', $consultation->withAddedTag('career')->followUpToConsultationId);
    }

    public function testWithUpdatedContextPreservesAnAlreadySetFollowUpLink(): void
    {
        $consultation = Consultation::create(
            'id-1',
            'Should I take the offer?',
            CastingMethodName::Manual,
            self::hexagramFromPattern('111111'),
            new \DateTimeImmutable(),
        )->withFollowUpTo('other-id');

        $withContext = $consultation->withUpdatedContext('New context.', null, null, null, null);

        self::assertSame('other-id', $withContext->followUpToConsultationId);
    }

    public function testWithUpdatedOutcomePreservesAnAlreadySetFollowUpLink(): void
    {
        $consultation = Consultation::create(
            'id-1',
            'Should I take the offer?',
            CastingMethodName::Manual,
            self::hexagramFromPattern('111111'),
            new \DateTimeImmutable(),
        )->withFollowUpTo('other-id');

        $withOutcome = $consultation->withUpdatedOutcome('Took it.', null, null, new \DateTimeImmutable());

        self::assertSame('other-id', $withOutcome->followUpToConsultationId);
    }

    public function testWithFollowUpToPreservesExistingOutcome(): void
    {
        $consultation = Consultation::create(
            'id-1',
            'Should I take the offer?',
            CastingMethodName::Manual,
            self::hexagramFromPattern('111111'),
            new \DateTimeImmutable(),
        )->withUpdatedOutcome('Took it.', null, null, new \DateTimeImmutable());

        $linked = $consultation->withFollowUpTo('other-id');

        self::assertSame('Took it.', $linked->outcome?->whatActuallyHappened);
    }

    public function testChangingLinePositionsMatchesThePrimaryHexagramsChangingLines(): void
    {
        $primary = self::hexagramFromPattern('101010', changingPositions: [2, 5]);

        $consultation = Consultation::create(
            'id-1',
            'Should I take the offer?',
            CastingMethodName::ThreeCoins,
            $primary,
            new \DateTimeImmutable(),
        );

        self::assertSame([2, 5], $consultation->changingLinePositions());
    }

    public function testConsultationNoteAcceptsTextAtExactlyTheLengthLimit(): void
    {
        $note = new ConsultationNote(NoteLabel::Before, str_repeat('a', 5000), new \DateTimeImmutable());

        self::assertSame(5000, mb_strlen($note->text));
    }

    public function testConsultationNoteRejectsTextOverTheLengthLimit(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ConsultationNote(NoteLabel::Before, str_repeat('a', 5001), new \DateTimeImmutable());
    }
}
