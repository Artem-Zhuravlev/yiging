<?php

declare(strict_types=1);

namespace App\Tests\Casting;

use App\Casting\Coin;
use App\Casting\ThreeCoinsMethod;
use App\Tests\Casting\Support\FakeCoinTosser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Yijing\Core\LinePolarity;

final class ThreeCoinsMethodTest extends TestCase
{
    /**
     * @return iterable<string, array{list<Coin>, string, bool}>
     */
    public static function coinTripletProvider(): iterable
    {
        // All 8 equally-likely 3-coin outcomes, exhaustively (REQ-CAST-010) -> traditional
        // 6/7/8/9 mapping: 1/8 old yin, 3/8 young yang, 3/8 young yin, 1/8 old yang.
        yield 'tails tails tails -> old yin' => [[Coin::Tails, Coin::Tails, Coin::Tails], 'yin', true];
        yield 'tails tails heads -> young yang' => [[Coin::Tails, Coin::Tails, Coin::Heads], 'yang', false];
        yield 'tails heads tails -> young yang' => [[Coin::Tails, Coin::Heads, Coin::Tails], 'yang', false];
        yield 'heads tails tails -> young yang' => [[Coin::Heads, Coin::Tails, Coin::Tails], 'yang', false];
        yield 'tails heads heads -> young yin' => [[Coin::Tails, Coin::Heads, Coin::Heads], 'yin', false];
        yield 'heads tails heads -> young yin' => [[Coin::Heads, Coin::Tails, Coin::Heads], 'yin', false];
        yield 'heads heads tails -> young yin' => [[Coin::Heads, Coin::Heads, Coin::Tails], 'yin', false];
        yield 'heads heads heads -> old yang' => [[Coin::Heads, Coin::Heads, Coin::Heads], 'yang', true];
    }

    /**
     * @param list<Coin> $triplet
     */
    #[DataProvider('coinTripletProvider')]
    public function testEachCoinTripletResolvesToTheExpectedLineState(
        array $triplet,
        string $expectedPolarity,
        bool $expectedChanging,
    ): void {
        $sequence = array_merge(...array_fill(0, 6, $triplet));
        $method = new ThreeCoinsMethod(new FakeCoinTosser($sequence));

        $hexagram = $method->cast();

        self::assertCount(6, $hexagram->lines);
        foreach ($hexagram->lines as $line) {
            self::assertSame(
                $expectedPolarity === 'yang' ? LinePolarity::Yang : LinePolarity::Yin,
                $line->polarity,
            );
            self::assertSame($expectedChanging, $line->changing);
        }
    }

    public function testCastBuildsLinesBottomToTopFromIndependentTosses(): void
    {
        // Line 1 (bottom): TTT=6 old yin. Line 2: HHH=9 old yang. Lines 3-6: THH=8 young yin.
        $sequence = [
            Coin::Tails, Coin::Tails, Coin::Tails,
            Coin::Heads, Coin::Heads, Coin::Heads,
            Coin::Tails, Coin::Heads, Coin::Heads,
            Coin::Tails, Coin::Heads, Coin::Heads,
            Coin::Tails, Coin::Heads, Coin::Heads,
            Coin::Tails, Coin::Heads, Coin::Heads,
        ];
        $method = new ThreeCoinsMethod(new FakeCoinTosser($sequence));

        $hexagram = $method->cast();

        self::assertSame(1, $hexagram->lines[0]->position);
        self::assertSame(LinePolarity::Yin, $hexagram->lines[0]->polarity);
        self::assertTrue($hexagram->lines[0]->changing);

        self::assertSame(2, $hexagram->lines[1]->position);
        self::assertSame(LinePolarity::Yang, $hexagram->lines[1]->polarity);
        self::assertTrue($hexagram->lines[1]->changing);
    }

    public function testExhaustedTosserThrows(): void
    {
        $this->expectException(\OutOfBoundsException::class);

        (new ThreeCoinsMethod(new FakeCoinTosser([Coin::Heads, Coin::Heads])))->cast();
    }
}
