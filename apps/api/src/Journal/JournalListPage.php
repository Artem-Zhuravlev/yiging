<?php

declare(strict_types=1);

namespace App\Journal;

/**
 * One page of journal entries (SPEC-041): the entries, plus an opaque `nextCursor` that is
 * non-null exactly when older entries exist after this page.
 */
final class JournalListPage
{
    /**
     * @param list<JournalEntry> $items
     */
    public function __construct(
        public readonly array $items,
        public readonly ?string $nextCursor,
    ) {
    }
}
