<?php

declare(strict_types=1);

namespace App\Readings;

final readonly class ConsultationNote
{
    public function __construct(
        public NoteLabel $label,
        public string $text,
        public \DateTimeImmutable $createdAt,
    ) {
        if (trim($text) === '') {
            throw new \InvalidArgumentException('A consultation note must not be empty.');
        }
    }
}
