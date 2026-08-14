<?php

declare(strict_types=1);

namespace Yijing\Core;

final readonly class LineComparison
{
    public function __construct(
        public int $position,
        public LinePolarity $aPolarity,
        public LinePolarity $bPolarity,
        public bool $changed,
    ) {
    }
}
