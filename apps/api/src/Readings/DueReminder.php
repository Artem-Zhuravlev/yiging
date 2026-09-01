<?php

declare(strict_types=1);

namespace App\Readings;

/**
 * One entry of `GET /api/consultations/reminders` (SPEC-054) — a consultation whose reflection
 * reminder has come due and which still has no recorded outcome. A lean read model built
 * directly from the due-list query, never a full `Consultation` hydration.
 */
final readonly class DueReminder
{
    public function __construct(
        public string $id,
        public string $question,
        public int $primaryKingWenNumber,
        public string $primaryChineseName,
        public string $primaryPinyin,
        public int $resultingKingWenNumber,
        public string $resultingChineseName,
        public string $resultingPinyin,
        public string $remindAtAtom,
        public string $createdAtAtom,
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
            'primaryHexagram' => [
                'kingWenNumber' => $this->primaryKingWenNumber,
                'chineseName' => $this->primaryChineseName,
                'pinyin' => $this->primaryPinyin,
            ],
            'resultingHexagram' => [
                'kingWenNumber' => $this->resultingKingWenNumber,
                'chineseName' => $this->resultingChineseName,
                'pinyin' => $this->resultingPinyin,
            ],
            'remindAt' => $this->remindAtAtom,
            'createdAt' => $this->createdAtAtom,
        ];
    }
}
