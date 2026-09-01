<?php

declare(strict_types=1);

namespace Yijing\Core\Tests;

use PHPUnit\Framework\TestCase;
use Yijing\Core\CastReading;
use Yijing\Core\Hexagram;
use Yijing\Core\ReadingRule;
use Yijing\Core\Tests\Support\LinePatternTrait;

final class CastReadingTest extends TestCase
{
    use LinePatternTrait;

    private static function qian(): Hexagram
    {
        return Hexagram::fromLines(self::linesFromPattern('111111'));
    }

    /**
     * @return list<array{string, string, int|null, bool}>
     */
    private static function refTuples(CastReading $reading): array
    {
        return array_map(
            static fn ($ref): array => [$ref->hexagram, $ref->kind, $ref->position, $ref->governing],
            $reading->refs,
        );
    }

    public function testNoChangingLinesReadsThePrimaryJudgment(): void
    {
        $reading = CastReading::forCast(self::qian(), []);

        self::assertSame(ReadingRule::NoChangingLines, $reading->rule);
        self::assertNull($reading->specialText);
        self::assertSame([['primary', 'judgment', null, true]], self::refTuples($reading));
    }

    public function testOneChangingLineReadsThatLineOfThePrimary(): void
    {
        $reading = CastReading::forCast(self::qian(), [3]);

        self::assertSame(ReadingRule::OneChangingLine, $reading->rule);
        self::assertSame([['primary', 'line', 3, true]], self::refTuples($reading));
    }

    public function testTwoChangingLinesReadBothOfThePrimaryUpperGoverning(): void
    {
        $reading = CastReading::forCast(self::qian(), [5, 2]);

        self::assertSame(ReadingRule::TwoChangingLines, $reading->rule);
        self::assertSame([
            ['primary', 'line', 2, false],
            ['primary', 'line', 5, true],
        ], self::refTuples($reading));
    }

    public function testThreeChangingLinesReadBothJudgmentsPrimaryGoverning(): void
    {
        $reading = CastReading::forCast(self::qian(), [1, 2, 3]);

        self::assertSame(ReadingRule::ThreeChangingLines, $reading->rule);
        self::assertSame([
            ['primary', 'judgment', null, true],
            ['resulting', 'judgment', null, false],
        ], self::refTuples($reading));
    }

    public function testFourChangingLinesReadTheTwoUnchangedResultingLinesLowerGoverning(): void
    {
        // Changing 1,2,4,6 -> unchanged 3 and 5.
        $reading = CastReading::forCast(self::qian(), [6, 1, 4, 2]);

        self::assertSame(ReadingRule::FourChangingLines, $reading->rule);
        self::assertSame([
            ['resulting', 'line', 3, true],
            ['resulting', 'line', 5, false],
        ], self::refTuples($reading));
    }

    public function testFiveChangingLinesReadTheSingleUnchangedResultingLine(): void
    {
        // Changing everything but position 4.
        $reading = CastReading::forCast(self::qian(), [1, 2, 3, 5, 6]);

        self::assertSame(ReadingRule::FiveChangingLines, $reading->rule);
        self::assertSame([['resulting', 'line', 4, true]], self::refTuples($reading));
    }

    public function testAllSixChangingOnQianReadsUseNine(): void
    {
        $reading = CastReading::forCast(self::qian(), [1, 2, 3, 4, 5, 6]);

        self::assertSame(ReadingRule::SixChangingLines, $reading->rule);
        self::assertSame('use-nine', $reading->specialText);
        self::assertSame([], $reading->refs);
    }

    public function testAllSixChangingOnKunReadsUseSix(): void
    {
        $kun = Hexagram::fromLines(self::linesFromPattern('000000'));

        $reading = CastReading::forCast($kun, [1, 2, 3, 4, 5, 6]);

        self::assertSame('use-six', $reading->specialText);
        self::assertSame([], $reading->refs);
    }

    public function testAllSixChangingOnAnyOtherHexagramReadsTheResultingJudgment(): void
    {
        $other = Hexagram::fromLines(self::linesFromPattern('101010')); // hexagram 64

        $reading = CastReading::forCast($other, [1, 2, 3, 4, 5, 6]);

        self::assertSame(ReadingRule::SixChangingLines, $reading->rule);
        self::assertNull($reading->specialText);
        self::assertSame([['resulting', 'judgment', null, true]], self::refTuples($reading));
    }

    public function testForCastRejectsADuplicatePosition(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        CastReading::forCast(self::qian(), [2, 2]);
    }

    public function testForCastRejectsAnOutOfRangePosition(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        CastReading::forCast(self::qian(), [7]);
    }

    public function testToArrayShapeForATwoChangingLineCast(): void
    {
        $reading = CastReading::forCast(self::qian(), [2, 5]);

        self::assertSame([
            'changingLineCount' => 2,
            'rule' => 'two-changing-lines',
            'refs' => [
                ['hexagram' => 'primary', 'kind' => 'line', 'position' => 2, 'governing' => false],
                ['hexagram' => 'primary', 'kind' => 'line', 'position' => 5, 'governing' => true],
            ],
            'specialText' => null,
        ], $reading->toArray());
    }
}
