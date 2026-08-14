<?php

declare(strict_types=1);

namespace App\Tests\AI;

use App\AI\InterpretationContextBuilder;
use App\Readings\CastingMethodName;
use App\Readings\Consultation;
use App\Readings\ConsultationNote;
use App\Readings\NoteLabel;
use App\Tests\Readings\Support\HexagramFixture;
use PHPUnit\Framework\TestCase;

final class InterpretationContextBuilderTest extends TestCase
{
    use HexagramFixture;

    public function testBuildsAContextWithOnlyTheChangingLinesStatements(): void
    {
        // Hexagram 1 (all yang) with lines 1 and 4 changing -> resulting hexagram differs.
        $primary = self::hexagramFromPattern('111111', changingPositions: [1, 4]);

        $consultation = Consultation::create(
            'id-1',
            'Should I take the offer?',
            CastingMethodName::ThreeCoins,
            $primary,
            new \DateTimeImmutable(),
        )->withAddedNote(new ConsultationNote(NoteLabel::Before, 'Feeling uncertain.', new \DateTimeImmutable()));

        $context = (new InterpretationContextBuilder())->build($consultation);

        self::assertSame('Should I take the offer?', $context->question);
        self::assertSame(1, $context->primaryHexagram->kingWenNumber);
        self::assertSame([1, 4], $context->changingLinePositions);
        self::assertSame(['Feeling uncertain.'], $context->userNotes);

        self::assertSame(array_keys($context->changingLineStatements), [1, 4]);
        self::assertSame($primary->lineStatements[0], $context->changingLineStatements[1]);
        self::assertSame($primary->lineStatements[3], $context->changingLineStatements[4]);
    }

    public function testChangingLineStatementsIsEmptyWhenThereAreNoChangingLines(): void
    {
        $primary = self::hexagramFromPattern('111111');

        $consultation = Consultation::create(
            'id-1',
            'Should I take the offer?',
            CastingMethodName::ThreeCoins,
            $primary,
            new \DateTimeImmutable(),
        );

        $context = (new InterpretationContextBuilder())->build($consultation);

        self::assertSame([], $context->changingLinePositions);
        self::assertSame([], $context->changingLineStatements);
        self::assertTrue($context->primaryHexagram->equals($context->resultingHexagram));
    }
}
