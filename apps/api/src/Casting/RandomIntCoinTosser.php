<?php

declare(strict_types=1);

namespace App\Casting;

final class RandomIntCoinTosser implements CoinTosser
{
    public function toss(): Coin
    {
        return random_int(0, 1) === 1 ? Coin::Heads : Coin::Tails;
    }
}
