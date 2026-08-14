<?php

declare(strict_types=1);

namespace App\Casting;

interface CoinTosser
{
    public function toss(): Coin;
}
