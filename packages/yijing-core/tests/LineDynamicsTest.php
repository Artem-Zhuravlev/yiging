<?php

declare(strict_types=1);

namespace Yijing\Core\Tests;

use PHPUnit\Framework\TestCase;
use Yijing\Core\Hexagram;
use Yijing\Core\LineDynamic;
use Yijing\Core\LineDynamics;
use Yijing\Core\Tests\Support\LinePatternTrait;

final class LineDynamicsTest extends TestCase
{
    use LinePatternTrait;

    private static function dynamics(string $pattern): LineDynamics
    {
        return LineDynamics::of(Hexagram::fromLines(self::linesFromPattern($pattern)));
    }

    private static function at(LineDynamics $d, int $position): LineDynamic
    {
        return $d->lines[$position - 1];
    }

    public function testJiJiHasEveryLineCorrectlyPlacedAndEveryPairCorresponding(): void
    {
        // Hexagram 63 (Ji Ji): yang in every odd place, yin in every even place.
        $d = self::dynamics('101010');

        foreach ($d->lines as $line) {
            self::assertTrue($line->correctPosition, "position {$line->position} should be correct");
            self::assertTrue($line->corresponds, "position {$line->position} should correspond");
        }
        self::assertTrue(self::at($d, 2)->centralAndCorrect);
        self::assertTrue(self::at($d, 5)->centralAndCorrect);
    }

    public function testWeiJiHasNoLineCorrectlyPlacedButEveryPairStillCorresponds(): void
    {
        // Hexagram 64 (Wei Ji): the mirror of Ji Ji.
        $d = self::dynamics('010101');

        foreach ($d->lines as $line) {
            self::assertFalse($line->correctPosition, "position {$line->position} should be improper");
            self::assertTrue($line->corresponds, "position {$line->position} should still correspond");
        }
        self::assertFalse(self::at($d, 2)->centralAndCorrect);
        self::assertFalse(self::at($d, 5)->centralAndCorrect);
    }

    public function testQianHasNoCorrespondencesAndCentralityOnlyAtTheCorrectFifthLine(): void
    {
        $d = self::dynamics('111111');

        foreach ($d->lines as $line) {
            self::assertFalse($line->corresponds);
            self::assertFalse($line->ridesFirmBelow);
            self::assertFalse($line->supportsFirmAbove);
        }
        // Line 5: yang in an odd place, central -> 中正.
        self::assertTrue(self::at($d, 5)->centralAndCorrect);
        // Line 2: central but yang in an even place -> not correct, not 中正.
        self::assertTrue(self::at($d, 2)->central);
        self::assertFalse(self::at($d, 2)->correctPosition);
        self::assertFalse(self::at($d, 2)->centralAndCorrect);
    }

    public function testCorrespondsWithMapsThePairs(): void
    {
        $d = self::dynamics('111111');

        self::assertSame(4, self::at($d, 1)->correspondsWith);
        self::assertSame(5, self::at($d, 2)->correspondsWith);
        self::assertSame(6, self::at($d, 3)->correspondsWith);
        self::assertSame(1, self::at($d, 4)->correspondsWith);
        self::assertSame(2, self::at($d, 5)->correspondsWith);
        self::assertSame(3, self::at($d, 6)->correspondsWith);
    }

    public function testAYinLineBelowAYangLineSupportsTheFirm(): void
    {
        // Hexagram 44 (Gou): yin at position 1, yang at 2-6.
        $d = self::dynamics('011111');

        self::assertTrue(self::at($d, 1)->supportsFirmAbove);
        self::assertFalse(self::at($d, 1)->ridesFirmBelow); // nothing below position 1
    }

    public function testAYinLineAboveAYangLineRidesTheFirm(): void
    {
        // yang at position 1, yin at 2-6.
        $d = self::dynamics('100000');

        self::assertTrue(self::at($d, 2)->ridesFirmBelow);
        self::assertFalse(self::at($d, 2)->supportsFirmAbove); // yin above it, not yang
    }

    public function testToArrayIsSixPositionOrderedEntries(): void
    {
        $rows = self::dynamics('101010')->toArray();

        self::assertCount(6, $rows);
        self::assertSame(1, $rows[0]['position']);
        self::assertSame(6, $rows[5]['position']);
        self::assertArrayHasKey('centralAndCorrect', $rows[0]);
    }
}
