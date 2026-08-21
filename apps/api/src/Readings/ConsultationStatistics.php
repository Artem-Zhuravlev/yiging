<?php

declare(strict_types=1);

namespace App\Readings;

/**
 * One aggregate snapshot over a user's entire consultation history (SPEC-024) — hexagram
 * frequency and yin/yang ratio are both computed over each consultation's primary (as-cast)
 * hexagram, not its resulting hexagram.
 */
final readonly class ConsultationStatistics
{
    /**
     * @param list<HexagramFrequency> $hexagramFrequency
     * @param list<TagFrequency> $tagFrequency
     */
    public function __construct(
        public int $totalConsultations,
        public array $hexagramFrequency,
        public int $yinLineCount,
        public int $yangLineCount,
        public array $tagFrequency,
    ) {
    }
}
