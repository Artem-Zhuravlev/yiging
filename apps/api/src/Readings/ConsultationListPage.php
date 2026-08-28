<?php

declare(strict_types=1);

namespace App\Readings;

/**
 * One page of the consultation list (SPEC-041): the items, plus an opaque `nextCursor` that is
 * non-null exactly when more rows exist after this page.
 */
final class ConsultationListPage
{
    /**
     * @param list<ConsultationListItem> $items
     */
    public function __construct(
        public readonly array $items,
        public readonly ?string $nextCursor,
    ) {
    }
}
