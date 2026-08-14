<?php

declare(strict_types=1);

namespace App\Casting;

use Yijing\Core\Hexagram;
use Yijing\Core\Line;

final readonly class ManualMethod implements DivinationMethod
{
    private const LINE_COUNT = 6;

    /**
     * @param list<Line> $lines exactly 6 lines, bottom to top
     */
    public function __construct(private array $lines)
    {
        if (count($lines) !== self::LINE_COUNT) {
            throw new \InvalidArgumentException(
                sprintf('Manual casting requires exactly %d lines, got %d.', self::LINE_COUNT, count($lines)),
            );
        }
    }

    public function cast(): Hexagram
    {
        return Hexagram::fromLines($this->lines);
    }
}
