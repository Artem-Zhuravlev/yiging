<?php

declare(strict_types=1);

namespace App\Tests\Casting\Support;

use App\Casting\Coin;
use App\Casting\CoinTosser;

final class FakeCoinTosser implements CoinTosser
{
    private int $index = 0;

    /**
     * @param list<Coin> $sequence
     */
    public function __construct(private readonly array $sequence)
    {
    }

    public function toss(): Coin
    {
        if (!array_key_exists($this->index, $this->sequence)) {
            throw new \OutOfBoundsException('FakeCoinTosser sequence exhausted.');
        }

        return $this->sequence[$this->index++];
    }
}
