<?php

declare(strict_types=1);

namespace App\Readings;

/**
 * How many consultations had a given hexagram as their primary (as-cast) hexagram (SPEC-024).
 */
final readonly class HexagramFrequency
{
    public function __construct(
        public int $kingWenNumber,
        public string $chineseName,
        public string $pinyin,
        public int $count,
    ) {
    }
}
