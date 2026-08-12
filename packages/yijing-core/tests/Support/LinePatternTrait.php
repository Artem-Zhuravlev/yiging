<?php

declare(strict_types=1);

namespace Yijing\Core\Tests\Support;

use Yijing\Core\Line;
use Yijing\Core\LinePolarity;

trait LinePatternTrait
{
    /**
     * @param array<int> $changingPositions 1-based positions that should be marked changing
     * @return list<Line>
     */
    private static function linesFromPattern(string $pattern, array $changingPositions = []): array
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

        return $lines;
    }
}
