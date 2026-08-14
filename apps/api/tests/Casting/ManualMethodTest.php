<?php

declare(strict_types=1);

namespace App\Tests\Casting;

use App\Casting\ManualMethod;
use PHPUnit\Framework\TestCase;
use Yijing\Core\Line;
use Yijing\Core\LinePolarity;

final class ManualMethodTest extends TestCase
{
    public function testCastBuildsTheExactHexagramFromGivenLines(): void
    {
        // All yang, no changes -> Hexagram 1 (Qian).
        $lines = array_map(
            static fn (int $position): Line => new Line($position, LinePolarity::Yang, false),
            range(1, 6),
        );

        $hexagram = (new ManualMethod($lines))->cast();

        self::assertSame(1, $hexagram->kingWenNumber);
    }

    public function testCastPreservesChangingLines(): void
    {
        $lines = array_map(
            static fn (int $position): Line => new Line($position, LinePolarity::Yang, $position === 1),
            range(1, 6),
        );

        $hexagram = (new ManualMethod($lines))->cast();
        $resulting = $hexagram->getResultingHexagram();

        self::assertSame(44, $resulting->kingWenNumber);
    }

    public function testConstructorRejectsTooFewLines(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $lines = array_map(
            static fn (int $position): Line => new Line($position, LinePolarity::Yang, false),
            range(1, 5),
        );

        new ManualMethod($lines);
    }

    public function testConstructorRejectsTooManyLines(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $lines = array_map(
            static fn (int $position): Line => new Line($position, LinePolarity::Yang, false),
            range(1, 7),
        );

        new ManualMethod($lines);
    }
}
