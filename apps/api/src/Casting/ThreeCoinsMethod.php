<?php

declare(strict_types=1);

namespace App\Casting;

use Yijing\Core\Hexagram;
use Yijing\Core\Line;
use Yijing\Core\LinePolarity;

/**
 * The traditional three-coin casting method: 6 lines, bottom to top, each resolved from the
 * sum of 3 independent coin tosses (heads = 3, tails = 2).
 *
 * 6 -> old yin (changing), 7 -> young yang, 8 -> young yin, 9 -> old yang (changing).
 */
final readonly class ThreeCoinsMethod implements DivinationMethod
{
    private const LINE_COUNT = 6;

    public function __construct(private CoinTosser $coinTosser)
    {
    }

    public function cast(): Hexagram
    {
        $lines = [];
        for ($position = 1; $position <= self::LINE_COUNT; $position++) {
            $lines[] = $this->tossLine($position);
        }

        return Hexagram::fromLines($lines);
    }

    private function tossLine(int $position): Line
    {
        $sum = $this->coinTosser->toss()->value()
            + $this->coinTosser->toss()->value()
            + $this->coinTosser->toss()->value();

        return match ($sum) {
            6 => new Line($position, LinePolarity::Yin, changing: true),
            7 => new Line($position, LinePolarity::Yang, changing: false),
            8 => new Line($position, LinePolarity::Yin, changing: false),
            9 => new Line($position, LinePolarity::Yang, changing: true),
        };
    }
}
