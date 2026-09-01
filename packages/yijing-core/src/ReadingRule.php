<?php

declare(strict_types=1);

namespace Yijing\Core;

/**
 * Which classical text a cast's answer comes from, by number of changing lines — the standard
 * Song-dynasty synthesis (Zhu Xi, Zhouyi benyi). See {@see CastReading} (SPEC-052).
 */
enum ReadingRule: string
{
    case NoChangingLines = 'no-changing-lines';
    case OneChangingLine = 'one-changing-line';
    case TwoChangingLines = 'two-changing-lines';
    case ThreeChangingLines = 'three-changing-lines';
    case FourChangingLines = 'four-changing-lines';
    case FiveChangingLines = 'five-changing-lines';
    case SixChangingLines = 'six-changing-lines';

    public static function fromCount(int $changingLineCount): self
    {
        return match ($changingLineCount) {
            0 => self::NoChangingLines,
            1 => self::OneChangingLine,
            2 => self::TwoChangingLines,
            3 => self::ThreeChangingLines,
            4 => self::FourChangingLines,
            5 => self::FiveChangingLines,
            6 => self::SixChangingLines,
            default => throw new \InvalidArgumentException(
                sprintf('A cast has between 0 and 6 changing lines, got %d.', $changingLineCount),
            ),
        };
    }
}
