<?php

declare(strict_types=1);

namespace Yijing\Core\Tests;

use PHPUnit\Framework\TestCase;
use Yijing\Core\Line;
use Yijing\Core\LinePolarity;

final class LineTest extends TestCase
{
    public function testWithPolarityFlippedTogglesYangToYin(): void
    {
        $line = new Line(1, LinePolarity::Yang, changing: true);

        $flipped = $line->withPolarityFlipped();

        self::assertSame(LinePolarity::Yin, $flipped->polarity);
        self::assertSame(1, $flipped->position);
        self::assertFalse($flipped->changing);
    }

    public function testWithPolarityFlippedTogglesYinToYang(): void
    {
        $line = new Line(3, LinePolarity::Yin);

        self::assertSame(LinePolarity::Yang, $line->withPolarityFlipped()->polarity);
    }

    public function testWithPolarityFlippedDoesNotMutateOriginal(): void
    {
        $line = new Line(1, LinePolarity::Yang);
        $line->withPolarityFlipped();

        self::assertSame(LinePolarity::Yang, $line->polarity);
    }

    public function testIsYang(): void
    {
        self::assertTrue((new Line(1, LinePolarity::Yang))->isYang());
        self::assertFalse((new Line(1, LinePolarity::Yin))->isYang());
    }

    public function testRejectsNonPositivePosition(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Line(0, LinePolarity::Yang);
    }
}
