<?php

declare(strict_types=1);

namespace App\AI;

interface InterpretationProvider
{
    public function interpret(InterpretationContext $context): Interpretation;
}
