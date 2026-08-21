<?php

declare(strict_types=1);

return [
    'up' => '
        ALTER TABLE consultations
            ADD COLUMN is_favorite INTEGER NOT NULL DEFAULT 0;
    ',
];
