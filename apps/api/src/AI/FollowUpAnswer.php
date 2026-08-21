<?php

declare(strict_types=1);

namespace App\AI;

final readonly class FollowUpAnswer
{
    /**
     * @param list<string> $sourceReferences
     */
    public function __construct(
        public string $answer,
        public array $sourceReferences,
    ) {
    }
}
