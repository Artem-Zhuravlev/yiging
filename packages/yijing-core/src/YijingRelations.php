<?php

declare(strict_types=1);

namespace Yijing\Core;

/**
 * Classical relationships between hexagrams. Stateless — every method takes a Hexagram and
 * returns a new one, none of the derived hexagrams carry over the source's changing-line flags
 * (they aren't the product of a casting).
 */
final class YijingRelations
{
    /**
     * Nuclear (互卦/hùguà) hexagram: new lower trigram from lines 2-3-4, new upper trigram from
     * lines 3-4-5 of the source hexagram.
     */
    public static function getNuclearHexagram(Hexagram $hexagram): Hexagram
    {
        $source = $hexagram->lines;

        $lines = [
            self::staticLine(1, $source[1]),
            self::staticLine(2, $source[2]),
            self::staticLine(3, $source[3]),
            self::staticLine(4, $source[2]),
            self::staticLine(5, $source[3]),
            self::staticLine(6, $source[4]),
        ];

        return Hexagram::fromLines($lines);
    }

    /**
     * Upside-down hexagram: line order reversed (1↔6, 2↔5, 3↔4).
     */
    public static function getOpposite(Hexagram $hexagram): Hexagram
    {
        $reversed = array_reverse($hexagram->lines);

        $lines = array_map(
            static fn (int $index, Line $line): Line => self::staticLine($index + 1, $line),
            array_keys($reversed),
            $reversed,
        );

        return Hexagram::fromLines($lines);
    }

    /**
     * Complement (錯卦/cuòguà): every line's polarity flipped.
     */
    public static function getComplement(Hexagram $hexagram): Hexagram
    {
        $lines = array_map(
            static fn (Line $line): Line => $line->withPolarityFlipped(),
            $hexagram->lines,
        );

        return Hexagram::fromLines($lines);
    }

    private static function staticLine(int $position, Line $source): Line
    {
        return new Line($position, $source->polarity, false);
    }
}
