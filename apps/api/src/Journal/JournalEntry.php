<?php

declare(strict_types=1);

namespace App\Journal;

final readonly class JournalEntry
{
    private const MAX_TEXT_LENGTH = 5000;

    public function __construct(
        public string $id,
        public string $text,
        public \DateTimeImmutable $createdAt,
    ) {
        if (trim($text) === '') {
            throw new \InvalidArgumentException('A journal entry must not be empty.');
        }

        if (mb_strlen($text) > self::MAX_TEXT_LENGTH) {
            throw new \InvalidArgumentException(
                sprintf('A journal entry must not exceed %d characters.', self::MAX_TEXT_LENGTH),
            );
        }
    }
}
