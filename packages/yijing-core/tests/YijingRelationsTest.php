<?php

declare(strict_types=1);

namespace Yijing\Core\Tests;

use PHPUnit\Framework\TestCase;
use Yijing\Core\Hexagram;
use Yijing\Core\Tests\Support\LinePatternTrait;
use Yijing\Core\YijingRelations;

final class YijingRelationsTest extends TestCase
{
    use LinePatternTrait;

    public function testComplementOfAllYangIsAllYin(): void
    {
        // Hexagram 1 (Qian, all yang) complement is Hexagram 2 (Kun, all yin) — well documented.
        $qian = Hexagram::fromLines(self::linesFromPattern('111111'));

        $complement = YijingRelations::getComplement($qian);

        self::assertSame(2, $complement->kingWenNumber);
    }

    public function testComplementIsItsOwnInverse(): void
    {
        $hexagram = Hexagram::fromLines(self::linesFromPattern('101010'));

        $roundTrip = YijingRelations::getComplement(YijingRelations::getComplement($hexagram));

        self::assertTrue($hexagram->equals($roundTrip));
    }

    public function testOppositeOfTaiIsPi(): void
    {
        // Hexagram 11 (Tai) and 12 (Pi) are the well-known "upside down" pair.
        $tai = Hexagram::fromLines(self::linesFromPattern('111000'));

        $opposite = YijingRelations::getOpposite($tai);

        self::assertSame(12, $opposite->kingWenNumber);
    }

    public function testOppositeIsItsOwnInverse(): void
    {
        $hexagram = Hexagram::fromLines(self::linesFromPattern('100110'));

        $roundTrip = YijingRelations::getOpposite(YijingRelations::getOpposite($hexagram));

        self::assertTrue($hexagram->equals($roundTrip));
    }

    public function testNuclearHexagramOfTaiIsGuiMei(): void
    {
        // Independently verified: hexagram 11 (Tai)'s nuclear hexagram is 54 (Gui Mei).
        $tai = Hexagram::fromLines(self::linesFromPattern('111000'));

        $nuclear = YijingRelations::getNuclearHexagram($tai);

        self::assertSame(54, $nuclear->kingWenNumber);
    }

    public function testNuclearHexagramOfAllYangIsItself(): void
    {
        $qian = Hexagram::fromLines(self::linesFromPattern('111111'));

        $nuclear = YijingRelations::getNuclearHexagram($qian);

        self::assertTrue($qian->equals($nuclear));
    }
}
