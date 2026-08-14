<?php

declare(strict_types=1);

namespace App\Tests\Readings\Support;

use Yijing\Core\Hexagram;
use Yijing\Core\Line;
use Yijing\Core\LinePolarity;

trait HexagramFixture
{
    /**
     * @param list<int> $changingPositions 1-based positions to mark changing
     */
    private static function hexagramFromPattern(string $pattern, array $changingPositions = []): Hexagram
    {
        $lines = [];

        foreach (str_split($pattern) as $index => $char) {
            $position = $index + 1;
            $lines[] = new Line(
                $position,
                $char === '1' ? LinePolarity::Yang : LinePolarity::Yin,
                in_array($position, $changingPositions, true),
            );
        }

        return Hexagram::fromLines($lines);
    }
}
