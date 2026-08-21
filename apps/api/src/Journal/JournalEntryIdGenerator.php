<?php

declare(strict_types=1);

namespace App\Journal;

interface JournalEntryIdGenerator
{
    public function generate(): string;
}
