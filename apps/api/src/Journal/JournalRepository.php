<?php

declare(strict_types=1);

namespace App\Journal;

interface JournalRepository
{
    public function save(JournalEntry $entry): void;

    /**
     * @return list<JournalEntry> ordered newest-first (createdAt descending)
     */
    public function findAll(): array;
}
