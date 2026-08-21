<?php

declare(strict_types=1);

namespace App\AI;

/**
 * One prior round of a follow-up conversation about an interpretation (SPEC-034) — sent by the
 * client with each new follow-up request, since conversations aren't persisted server-side
 * (matches SPEC-008's "AI output isn't persisted" stance).
 */
final readonly class ConversationExchange
{
    public function __construct(
        public string $question,
        public string $answer,
    ) {
    }
}
