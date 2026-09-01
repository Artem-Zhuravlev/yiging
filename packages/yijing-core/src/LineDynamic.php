<?php

declare(strict_types=1);

namespace Yijing\Core;

/**
 * The classical intra-hexagram relationships for a single line (SPEC-053) — the analytical
 * grammar of commentary since Wang Bi. All derived from the six line polarities:
 *
 *  - correctPosition (當位): yang in an odd place (1/3/5) or yin in an even place (2/4/6).
 *  - central (中): positions 2 and 5.
 *  - centralAndCorrect (中正): both of the above.
 *  - corresponds (正應) with its partner (1-4, 2-5, 3-6): partner is the opposite polarity.
 *  - ridesFirmBelow (乘剛): this yin line sits directly above a yang line — inauspicious.
 *  - supportsFirmAbove (承): this yin line sits directly below a yang line — favourable.
 */
final readonly class LineDynamic
{
    public function __construct(
        public int $position,
        public bool $correctPosition,
        public bool $central,
        public bool $centralAndCorrect,
        public int $correspondsWith,
        public bool $corresponds,
        public bool $ridesFirmBelow,
        public bool $supportsFirmAbove,
    ) {
    }

    /**
     * @return array{
     *     position: int, correctPosition: bool, central: bool, centralAndCorrect: bool,
     *     correspondsWith: int, corresponds: bool, ridesFirmBelow: bool, supportsFirmAbove: bool
     * }
     */
    public function toArray(): array
    {
        return [
            'position' => $this->position,
            'correctPosition' => $this->correctPosition,
            'central' => $this->central,
            'centralAndCorrect' => $this->centralAndCorrect,
            'correspondsWith' => $this->correspondsWith,
            'corresponds' => $this->corresponds,
            'ridesFirmBelow' => $this->ridesFirmBelow,
            'supportsFirmAbove' => $this->supportsFirmAbove,
        ];
    }
}
