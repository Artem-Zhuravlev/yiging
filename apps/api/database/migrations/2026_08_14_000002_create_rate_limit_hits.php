<?php

declare(strict_types=1);

return [
    'up' => '
        CREATE TABLE rate_limit_hits (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            rate_limit_key TEXT NOT NULL,
            created_at TEXT NOT NULL
        );

        CREATE INDEX idx_rate_limit_hits_key_created_at ON rate_limit_hits(rate_limit_key, created_at);
    ',
];
