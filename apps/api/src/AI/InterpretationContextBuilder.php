<?php

declare(strict_types=1);

namespace App\AI;

use App\Readings\Consultation;
use App\Readings\ConsultationNote;

final class InterpretationContextBuilder
{
    public function build(Consultation $consultation): InterpretationContext
    {
        $changingLinePositions = $consultation->changingLinePositions();

        $changingLineStatements = [];
        foreach ($changingLinePositions as $position) {
            $changingLineStatements[$position] = $consultation->primaryHexagram->lineStatements[$position - 1];
        }

        return new InterpretationContext(
            $consultation->question,
            $consultation->primaryHexagram,
            $changingLinePositions,
            $changingLineStatements,
            $consultation->resultingHexagram,
            array_map(static fn (ConsultationNote $note): string => $note->text, $consultation->notes),
        );
    }
}
