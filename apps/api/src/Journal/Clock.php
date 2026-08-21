<?php

declare(strict_types=1);

namespace App\Journal;

interface Clock
{
    public function now(): \DateTimeImmutable;
}
