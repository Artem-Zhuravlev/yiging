<?php

declare(strict_types=1);

namespace App\AI;

final readonly class Interpretation
{
    /**
     * @param list<string> $uncertainties
     * @param list<string> $sourceReferences
     */
    public function __construct(
        public string $summary,
        public string $coreTheme,
        public string $situation,
        public ?string $changingLineMeaning,
        public ?string $transition,
        public string $practicalReflection,
        public array $uncertainties,
        public array $sourceReferences,
    ) {
    }
}
