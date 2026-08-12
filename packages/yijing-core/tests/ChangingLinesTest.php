<?php

declare(strict_types=1);

namespace Yijing\Core\Tests;

use PHPUnit\Framework\TestCase;
use Yijing\Core\Hexagram;
use Yijing\Core\Tests\Support\LinePatternTrait;
use Yijing\Core\YijingRelations;

final class ChangingLinesTest extends TestCase
{
    use LinePatternTrait;

    public function testChangeLineDoesNotMutateTheOriginalHexagram(): void
    {
        $hexagram = Hexagram::fromLines(self::linesFromPattern('111111'));
        $hexagram->changeLine(1);

        self::assertSame(1, $hexagram->kingWenNumber);
    }

    public function testChangeLineFlipsOnlyTheGivenPosition(): void
    {
        // Hexagram 1 (Qian, all yang) with line 1 changed to yin -> 011111 -> Hexagram 44 (Gou).
        $hexagram = Hexagram::fromLines(self::linesFromPattern('111111'));

        $changed = $hexagram->changeLine(1);

        self::assertSame(44, $changed->kingWenNumber);
    }

    public function testGetResultingHexagramWithNoChangingLinesEqualsThePrimary(): void
    {
        $hexagram = Hexagram::fromLines(self::linesFromPattern('101010'));

        $resulting = $hexagram->getResultingHexagram();

        self::assertTrue($hexagram->equals($resulting));
    }

    public function testGetResultingHexagramWithAllSixChangingEqualsTheComplement(): void
    {
        $hexagram = Hexagram::fromLines(self::linesFromPattern('101010', changingPositions: [1, 2, 3, 4, 5, 6]));

        $resulting = $hexagram->getResultingHexagram();
        $complement = YijingRelations::getComplement($hexagram);

        self::assertTrue($resulting->equals($complement));
    }

    public function testGetResultingHexagramFlipsOnlyTheChangingLines(): void
    {
        // Hexagram 1 (all yang) with only line 1 marked changing -> same as changeLine(1) -> Hexagram 44.
        $hexagram = Hexagram::fromLines(self::linesFromPattern('111111', changingPositions: [1]));

        $resulting = $hexagram->getResultingHexagram();

        self::assertSame(44, $resulting->kingWenNumber);
    }
}
