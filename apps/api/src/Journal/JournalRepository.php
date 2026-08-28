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

    /**
     * One page of entries, newest-first (SPEC-041). $cursor is an opaque
     * {@see \App\Core\ListCursor} token from a previous page's `nextCursor`; null for page 1.
     */
    public function findPage(int $limit, ?string $cursor): JournalListPage;
}
