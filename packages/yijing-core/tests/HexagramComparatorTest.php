<?php

declare(strict_types=1);

namespace Yijing\Core\Tests;

use PHPUnit\Framework\TestCase;
use Yijing\Core\Hexagram;
use Yijing\Core\HexagramComparator;
use Yijing\Core\LinePolarity;
use Yijing\Core\Tests\Support\LinePatternTrait;

final class HexagramComparatorTest extends TestCase
{
    use LinePatternTrait;

    public function testCompareLinesMarksMatchingAndDifferingPositions(): void
    {
        // Tai (111000) vs Pi (000111) — every position differs.
        $tai = Hexagram::fromLines(self::linesFromPattern('111000'));
        $pi = Hexagram::fromLines(self::linesFromPattern('000111'));

        $comparison = HexagramComparator::compareLines($tai, $pi);

        self::assertCount(6, $comparison);
        foreach ($comparison as $index => $lineComparison) {
            self::assertSame($index + 1, $lineComparison->position);
            self::assertTrue($lineComparison->changed);
        }
    }

    public function testCompareLinesReportsUnchangedForIdenticalHexagrams(): void
    {
        $qian = Hexagram::fromLines(self::linesFromPattern('111111'));

        $comparison = HexagramComparator::compareLines($qian, $qian);

        foreach ($comparison as $lineComparison) {
            self::assertFalse($lineComparison->changed);
            self::assertSame($lineComparison->aPolarity, $lineComparison->bPolarity);
        }
    }

    public function testCompareLinesReportsAMixOfChangedAndUnchangedPositions(): void
    {
        // Positions 1-3 match (yang), positions 4-6 differ (b is yin where a is yang).
        $a = Hexagram::fromLines(self::linesFromPattern('111111'));
        $b = Hexagram::fromLines(self::linesFromPattern('111000'));

        $comparison = HexagramComparator::compareLines($a, $b);

        self::assertFalse($comparison[0]->changed);
        self::assertFalse($comparison[1]->changed);
        self::assertFalse($comparison[2]->changed);

        self::assertTrue($comparison[3]->changed);
        self::assertSame(LinePolarity::Yang, $comparison[3]->aPolarity);
        self::assertSame(LinePolarity::Yin, $comparison[3]->bPolarity);

        self::assertTrue($comparison[4]->changed);
        self::assertTrue($comparison[5]->changed);
    }
}
