<?php

declare(strict_types=1);

return [
    'up' => '
        CREATE TABLE interpretation_profile (
            id INTEGER PRIMARY KEY CHECK (id = 1),
            tone TEXT NOT NULL DEFAULT \'neutral\',
            length TEXT NOT NULL DEFAULT \'standard\',
            notes TEXT
        );
    ',
];
