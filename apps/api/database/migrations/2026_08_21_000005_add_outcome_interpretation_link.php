<?php

declare(strict_types=1);

return [
    'up' => '
        ALTER TABLE consultation_outcomes ADD COLUMN interpretation_lens TEXT;
        ALTER TABLE consultation_outcomes ADD COLUMN interpretation_summary TEXT;
    ',
];
