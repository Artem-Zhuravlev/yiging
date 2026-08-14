<?php

declare(strict_types=1);

namespace App\Readings;

interface Clock
{
    public function now(): \DateTimeImmutable;
}
