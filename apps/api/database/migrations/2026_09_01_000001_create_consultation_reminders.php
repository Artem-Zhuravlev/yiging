<?php

declare(strict_types=1);

return [
    'up' => '
        CREATE TABLE consultation_reminders (
            consultation_id TEXT PRIMARY KEY REFERENCES consultations(id) ON DELETE CASCADE,
            remind_at TEXT NOT NULL,
            created_at TEXT NOT NULL
        );
        CREATE INDEX idx_consultation_reminders_remind_at ON consultation_reminders (remind_at);
    ',
];
