<?php

declare(strict_types=1);

namespace Yijing\Core;

/**
 * The four computable intra-hexagram line relationships (SPEC-053) — correctness of position,
 * centrality, correspondence, and riding/receiving — for all six lines. Pure: derived entirely
 * from the line polarities, no catalog. The ruling line (卦主) is not here; it needs a sourced
 * per-hexagram table.
 */
final readonly class LineDynamics
{
    private const PARTNER = [1 => 4, 2 => 5, 3 => 6, 4 => 1, 5 => 2, 6 => 3];

    /**
     * @param list<LineDynamic> $lines exactly 6, position order
     */
    private function __construct(
        public array $lines,
    ) {
    }

    public static function of(Hexagram $hexagram): self
    {
        /** @var array<int, Line> $byPosition */
        $byPosition = [];
        foreach ($hexagram->lines as $line) {
            $byPosition[$line->position] = $line;
        }

        $dynamics = [];
        for ($position = 1; $position <= 6; $position++) {
            $yang = $byPosition[$position]->isYang();
            $oddPosition = $position % 2 === 1;
            $correctPosition = ($oddPosition && $yang) || (!$oddPosition && !$yang);
            $central = $position === 2 || $position === 5;
            $partner = self::PARTNER[$position];

            $dynamics[] = new LineDynamic(
                position: $position,
                correctPosition: $correctPosition,
                central: $central,
                centralAndCorrect: $central && $correctPosition,
                correspondsWith: $partner,
                corresponds: $byPosition[$partner]->isYang() !== $yang,
                ridesFirmBelow: !$yang && $position > 1 && $byPosition[$position - 1]->isYang(),
                supportsFirmAbove: !$yang && $position < 6 && $byPosition[$position + 1]->isYang(),
            );
        }

        return new self($dynamics);
    }

    /**
     * @return list<array<string, mixed>> the six {@see LineDynamic} arrays, position order
     */
    public function toArray(): array
    {
        return array_map(static fn (LineDynamic $d): array => $d->toArray(), $this->lines);
    }
}
