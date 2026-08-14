<?php

declare(strict_types=1);

namespace App\AI;

use Yijing\Core\Hexagram;

final readonly class InterpretationContext
{
    /**
     * @param list<int> $changingLinePositions
     * @param array<int, string> $changingLineStatements position (1-6) => that line's statement
     * @param list<string> $userNotes
     */
    public function __construct(
        public string $question,
        public Hexagram $primaryHexagram,
        public array $changingLinePositions,
        public array $changingLineStatements,
        public Hexagram $resultingHexagram,
        public array $userNotes,
    ) {
    }
}
