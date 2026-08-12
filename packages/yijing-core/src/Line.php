<?php

declare(strict_types=1);

namespace Yijing\Core;

final readonly class Line
{
    public function __construct(
        public int $position,
        public LinePolarity $polarity,
        public bool $changing = false,
    ) {
        if ($position < 1) {
            throw new \InvalidArgumentException("Line position must be at least 1, got {$position}.");
        }
    }

    public function withPolarityFlipped(): self
    {
        return new self($this->position, $this->polarity->opposite(), false);
    }

    public function isYang(): bool
    {
        return $this->polarity === LinePolarity::Yang;
    }
}
