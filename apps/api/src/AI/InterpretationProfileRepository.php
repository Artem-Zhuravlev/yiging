<?php

declare(strict_types=1);

namespace App\AI;

interface InterpretationProfileRepository
{
    /**
     * Returns InterpretationProfile::default() when nothing has ever been saved — a missing
     * profile is a normal, expected state, never an error.
     */
    public function get(): InterpretationProfile;

    public function save(InterpretationProfile $profile): void;
}
