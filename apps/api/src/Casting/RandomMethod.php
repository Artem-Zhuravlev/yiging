<?php

declare(strict_types=1);

namespace App\Casting;

use Yijing\Core\Hexagram;
use Yijing\Core\Line;
use Yijing\Core\LinePolarity;

/**
 * Fast, non-traditional random cast for development/testing/demo use.
 *
 * Uses a uniform 50/50 yin/yang and 50/50 changing/stable per line via the same `CoinTosser`
 * boundary as `ThreeCoinsMethod` — NOT the traditional three-coin distribution. Never present
 * this method's output as a doctrinally accurate casting.
 */
final readonly class RandomMethod implements DivinationMethod
{
    private const LINE_COUNT = 6;

    public function __construct(private CoinTosser $coinTosser)
    {
    }

    public function cast(): Hexagram
    {
        $lines = [];
        for ($position = 1; $position <= self::LINE_COUNT; $position++) {
            $polarity = $this->coinTosser->toss() === Coin::Heads ? LinePolarity::Yang : LinePolarity::Yin;
            $changing = $this->coinTosser->toss() === Coin::Heads;
            $lines[] = new Line($position, $polarity, $changing);
        }

        return Hexagram::fromLines($lines);
    }
}
