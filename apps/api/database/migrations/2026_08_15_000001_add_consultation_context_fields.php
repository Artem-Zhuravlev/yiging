<?php

declare(strict_types=1);

return [
    'up' => '
        ALTER TABLE consultations ADD COLUMN context TEXT;
        ALTER TABLE consultations ADD COLUMN what_happened_before TEXT;
        ALTER TABLE consultations ADD COLUMN what_user_wants_to_understand TEXT;
        ALTER TABLE consultations ADD COLUMN background_information TEXT;
        ALTER TABLE consultations ADD COLUMN initial_interpretation TEXT;
    ',
];
