<?php

declare(strict_types=1);

namespace App\Readings;

/**
 * The lean projection `GET /api/consultations` returns per row (SPEC-041): everything the
 * History page's cards and date grouping need, and nothing else — no notes, context, outcome,
 * follow-up links, or repeats. Built directly from one list row (plus its batched tags), never
 * via a full `Consultation` hydration.
 */
final class ConsultationListItem
{
    /**
     * @param list<int>    $changingLinePositions
     * @param list<string> $tags
     */
    public function __construct(
        public readonly string $id,
        public readonly string $question,
        public readonly string $method,
        public readonly int $primaryKingWenNumber,
        public readonly string $primaryChineseName,
        public readonly string $primaryPinyin,
        public readonly array $changingLinePositions,
        public readonly int $resultingKingWenNumber,
        public readonly string $resultingChineseName,
        public readonly string $resultingPinyin,
        public readonly string $createdAtAtom,
        public readonly array $tags,
        public readonly bool $favorite,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toJson(): array
    {
        return [
            'id' => $this->id,
            'question' => $this->question,
            'method' => $this->method,
            'primaryHexagram' => [
                'kingWenNumber' => $this->primaryKingWenNumber,
                'chineseName' => $this->primaryChineseName,
                'pinyin' => $this->primaryPinyin,
            ],
            'changingLinePositions' => $this->changingLinePositions,
            'resultingHexagram' => [
                'kingWenNumber' => $this->resultingKingWenNumber,
                'chineseName' => $this->resultingChineseName,
                'pinyin' => $this->resultingPinyin,
            ],
            'createdAt' => $this->createdAtAtom,
            'tags' => $this->tags,
            'favorite' => $this->favorite,
        ];
    }
}
