<?php

declare(strict_types=1);

namespace App\Tests\Readings;

use App\Readings\CastingMethodName;
use App\Readings\Consultation;
use App\Readings\ConsultationNote;
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
}
