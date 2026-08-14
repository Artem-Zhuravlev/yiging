<?php

declare(strict_types=1);

namespace App\AI;

/**
 * Thrown by any InterpretationProvider (not just Gemini's) when it cannot produce an
 * Interpretation - a transport failure, a rejected request, or a malformed/incomplete
 * response. The message MUST NOT contain secrets (API keys, tokens); it is safe to return to
 * an API client as-is.
 */
final class InterpretationProviderException extends \RuntimeException
{
}
