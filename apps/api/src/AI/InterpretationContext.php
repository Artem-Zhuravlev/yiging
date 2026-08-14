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

    /**
     * The canonical text actually in scope for this reading, as citation strings. Every
     * InterpretationProvider MUST use this verbatim for its Interpretation's sourceReferences
     * — never a provider-generated citation list, which for a real AI provider could
     * hallucinate a reference to text that was never actually part of the context.
     *
     * @return list<string>
     */
    public function defaultSourceReferences(): array
    {
        $references = [
            sprintf('Hexagram %d judgment (Legge, 1899)', $this->primaryHexagram->kingWenNumber),
            sprintf('Hexagram %d image (Legge, 1899)', $this->primaryHexagram->kingWenNumber),
        ];

        foreach (array_keys($this->changingLineStatements) as $position) {
            $references[] = sprintf(
                'Hexagram %d line %d (Legge, 1899)',
                $this->primaryHexagram->kingWenNumber,
                $position,
            );
        }

        if ($this->changingLinePositions !== []) {
            $references[] = sprintf(
                'Hexagram %d judgment (Legge, 1899) [resulting]',
                $this->resultingHexagram->kingWenNumber,
            );
        }

        return $references;
    }
}
