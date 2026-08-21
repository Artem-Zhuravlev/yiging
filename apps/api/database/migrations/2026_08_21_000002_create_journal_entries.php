<?php

declare(strict_types=1);

return [
    'up' => '
        CREATE TABLE journal_entries (
            id TEXT PRIMARY KEY,
            text TEXT NOT NULL,
            created_at TEXT NOT NULL
        );
    ',
];
