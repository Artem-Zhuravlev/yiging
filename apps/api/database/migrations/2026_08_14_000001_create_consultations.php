<?php

declare(strict_types=1);

return [
    'up' => '
        CREATE TABLE consultations (
            id TEXT PRIMARY KEY,
            question TEXT NOT NULL,
            method TEXT NOT NULL,
            primary_king_wen_number INTEGER NOT NULL,
            changing_line_positions TEXT NOT NULL,
            resulting_king_wen_number INTEGER NOT NULL,
            created_at TEXT NOT NULL
        );

        CREATE TABLE consultation_notes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            consultation_id TEXT NOT NULL REFERENCES consultations(id) ON DELETE CASCADE,
            label TEXT NOT NULL,
            text TEXT NOT NULL,
            created_at TEXT NOT NULL,
            sort_order INTEGER NOT NULL
        );

        CREATE TABLE tags (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL UNIQUE
        );

        CREATE TABLE consultation_tags (
            consultation_id TEXT NOT NULL REFERENCES consultations(id) ON DELETE CASCADE,
            tag_id INTEGER NOT NULL REFERENCES tags(id) ON DELETE CASCADE,
            PRIMARY KEY (consultation_id, tag_id)
        );

        CREATE INDEX idx_consultation_notes_consultation_id ON consultation_notes(consultation_id);
        CREATE INDEX idx_consultation_tags_tag_id ON consultation_tags(tag_id);
    ',
];
