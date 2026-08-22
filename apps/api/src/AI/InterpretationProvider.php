<?php

declare(strict_types=1);

namespace App\AI;

interface InterpretationProvider
{
    public function interpret(
        InterpretationContext $context,
        InterpretationLens $lens,
        InterpretationProfile $profile,
        ResponseLanguage $language,
    ): Interpretation;

    /**
     * @param list<ConversationExchange> $history
     */
    public function answerFollowUp(
        InterpretationContext $context,
        array $history,
        string $question,
        InterpretationProfile $profile,
        ResponseLanguage $language,
    ): FollowUpAnswer;
}
