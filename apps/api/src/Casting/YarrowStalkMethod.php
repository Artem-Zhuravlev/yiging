<?php

declare(strict_types=1);

namespace App\Casting;

use Yijing\Core\Hexagram;
use Yijing\Core\Line;
use Yijing\Core\LinePolarity;

/**
 * The traditional yarrow-stalk method (蓍法): 6 lines, bottom to top, each drawn on the
 * Zhu Xi (朱熹) distribution.
 *
 * In sixteenths, per line:
 *   6 old yin (changing)  1   ·   7 young yang   5
 *   8 young yin           7   ·   9 old yang (changing)   3
 *
 * Unlike {@see ThreeCoinsMethod}'s flatter 2 / 6 / 6 / 2, moving lines are rarer overall and a
 * moving yang (9) is three times as likely as a moving yin (6) — the asymmetry the classical
 * commentaries assume. This is the idealised distribution: the physical three-"changes"
 * procedure is idealised to probabilities of 3/4 and 1/2 per change, as in standard treatments
 * of Zhu Xi's method.
 */
final readonly class YarrowStalkMethod implements DivinationMethod
{
    private const LINE_COUNT = 6;

    public function __construct(private RandomSource $random)
    {
    }

    public function cast(): Hexagram
    {
        $lines = [];
        for ($position = 1; $position <= self::LINE_COUNT; $position++) {
            $lines[] = $this->drawLine($position);
        }

        return Hexagram::fromLines($lines);
    }

    private function drawLine(int $position): Line
    {
        $draw = $this->random->intBetween(1, 16);

        return match (true) {
            $draw <= 1 => new Line($position, LinePolarity::Yin, changing: true),
            $draw <= 6 => new Line($position, LinePolarity::Yang, changing: false),
            $draw <= 13 => new Line($position, LinePolarity::Yin, changing: false),
            default => new Line($position, LinePolarity::Yang, changing: true),
        };
    }
}
