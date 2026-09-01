<?php

declare(strict_types=1);

namespace App\Casting;

final class SystemRandomSource implements RandomSource
{
    public function intBetween(int $min, int $max): int
    {
        return random_int($min, $max);
    }
}
