<?php

declare(strict_types=1);

namespace App\Casting;

enum Coin
{
    case Heads;
    case Tails;

    /**
     * @return int<2, 3>
     */
    public function value(): int
    {
        return match ($this) {
            self::Heads => 3,
            self::Tails => 2,
        };
    }
}
