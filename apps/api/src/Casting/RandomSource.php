<?php

declare(strict_types=1);

namespace App\Casting;

interface RandomSource
{
    /**
     * A uniform random integer in [$min, $max], both bounds inclusive.
     */
    public function intBetween(int $min, int $max): int;
}
