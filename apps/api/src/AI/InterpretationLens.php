<?php

declare(strict_types=1);

namespace App\AI;

/**
 * What an interpretation should focus on (SPEC-033) — orthogonal to which provider answers
 * (`AI_PROVIDER`, still exactly `mock` or `gemini`). `General` is the default and reproduces
 * this app's pre-SPEC-033 behavior exactly.
 */
enum InterpretationLens: string
{
    case General = 'general';
    case Psychological = 'psychological';
    case Practical = 'practical';
    case Symbolic = 'symbolic';
}
