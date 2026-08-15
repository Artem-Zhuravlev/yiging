<?php

declare(strict_types=1);

namespace App\Readings;

/**
 * A minimal display shape for a consultation referenced by another one (SPEC-021's follow-up
 * link) — deliberately not a full Consultation, to avoid loading hexagrams/notes/tags/context/
 * outcome (and recursively, that consultation's own follow-up links) just to show a question.
 */
final readonly class ConsultationSummary
{
    public function __construct(
        public string $id,
        public string $question,
    ) {
    }
}
