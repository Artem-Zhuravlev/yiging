<?php

declare(strict_types=1);

return [
    'up' => '
        ALTER TABLE consultations
            ADD COLUMN follow_up_to_consultation_id TEXT REFERENCES consultations(id) ON DELETE SET NULL;
    ',
];
