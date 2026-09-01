<?php

declare(strict_types=1);

namespace App\Tests\Casting\Support;

use App\Casting\RandomSource;

final class FakeRandomSource implements RandomSource
{
    private int $index = 0;

    /**
     * @param list<int> $values returned in order; bounds passed to intBetween() are ignored
     */
    public function __construct(private readonly array $values)
    {
    }

    public function intBetween(int $min, int $max): int
    {
        if (!array_key_exists($this->index, $this->values)) {
            throw new \OutOfBoundsException('FakeRandomSource sequence exhausted.');
        }

        return $this->values[$this->index++];
    }
}
