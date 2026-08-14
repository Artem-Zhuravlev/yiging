<?php

declare(strict_types=1);

namespace App\Readings;

final readonly class ConsultationNote
{
    private const MAX_TEXT_LENGTH = 5000;

    public function __construct(
        public NoteLabel $label,
        public string $text,
        public \DateTimeImmutable $createdAt,
    ) {
        if (trim($text) === '') {
            throw new \InvalidArgumentException('A consultation note must not be empty.');
        }

        if (mb_strlen($text) > self::MAX_TEXT_LENGTH) {
            throw new \InvalidArgumentException(
                sprintf('A consultation note must not exceed %d characters.', self::MAX_TEXT_LENGTH),
            );
        }
    }
}
