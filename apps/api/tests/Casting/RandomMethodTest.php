<?php

declare(strict_types=1);

namespace App\Tests\Casting;

use App\Casting\Coin;
use App\Casting\RandomMethod;
use App\Tests\Casting\Support\FakeCoinTosser;
use PHPUnit\Framework\TestCase;
use Yijing\Core\LinePolarity;

final class RandomMethodTest extends TestCase
{
    public function testCastConsumesTwoTossesPerLineForPolarityAndChanging(): void
    {
        // Line 1: heads,heads -> yang, changing. Lines 2-6: tails,tails -> yin, stable.
        $sequence = array_merge(
            [Coin::Heads, Coin::Heads],
            ...array_fill(0, 5, [Coin::Tails, Coin::Tails]),
        );
        $method = new RandomMethod(new FakeCoinTosser($sequence));

        $hexagram = $method->cast();

        self::assertSame(LinePolarity::Yang, $hexagram->lines[0]->polarity);
        self::assertTrue($hexagram->lines[0]->changing);

        for ($i = 1; $i < 6; $i++) {
            self::assertSame(LinePolarity::Yin, $hexagram->lines[$i]->polarity);
            self::assertFalse($hexagram->lines[$i]->changing);
        }
    }

    public function testCastReturnsAValidSixLineHexagram(): void
    {
        $sequence = array_fill(0, 12, Coin::Heads);
        $method = new RandomMethod(new FakeCoinTosser($sequence));

        $hexagram = $method->cast();

        self::assertCount(6, $hexagram->lines);
        self::assertSame(1, $hexagram->kingWenNumber);
    }
}
