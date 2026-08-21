<?php

declare(strict_types=1);

namespace App\Readings;

/**
 * How many consultations carry a given tag (SPEC-024).
 */
final readonly class TagFrequency
{
    public function __construct(
        public string $name,
        public int $count,
    ) {
    }
}
