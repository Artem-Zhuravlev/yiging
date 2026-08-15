<?php

declare(strict_types=1);

return [
    'up' => '
        CREATE TABLE consultation_outcomes (
            consultation_id TEXT PRIMARY KEY REFERENCES consultations(id) ON DELETE CASCADE,
            what_actually_happened TEXT,
            outcome TEXT,
            reflection TEXT,
            recorded_at TEXT NOT NULL
        );
    ',
];
