<?php

declare(strict_types=1);

namespace Yijing\Core;

/**
 * Position-by-position line comparison between two hexagrams. Stateless, like YijingRelations.
 */
final class HexagramComparator
{
    /**
     * @return list<LineComparison>
     */
    public static function compareLines(Hexagram $a, Hexagram $b): array
    {
        return array_map(
            static fn (Line $lineA, Line $lineB): LineComparison => new LineComparison(
                $lineA->position,
                $lineA->polarity,
                $lineB->polarity,
                $lineA->polarity !== $lineB->polarity,
            ),
            $a->lines,
            $b->lines,
        );
    }
}
