<?php

declare(strict_types=1);

namespace App\AI;

/**
 * The two languages an interpretation/follow-up answer may be requested in (SPEC-038). Deliberately
 * just these two, not a general locale system - matches() below is a Cyrillic-ratio classifier
 * that is reliable specifically because English and Ukrainian use entirely different scripts; it
 * would not generalize to e.g. English/French, which was never asked for.
 */
enum ResponseLanguage: string
{
    case English = 'en';
    case Ukrainian = 'uk';

    /**
     * An explicit, unambiguous instruction appended to every prompt - never left implicit, since
     * relying on Gemini's default language is exactly the unreliable behavior this spec exists
     * to replace with a verified guarantee (see matches()).
     */
    public function promptInstruction(): string
    {
        return match ($this) {
            self::English => 'Respond entirely in English.',
            self::Ukrainian => 'Respond entirely in Ukrainian (українською мовою). Every piece of '
                . 'text you write - every field - must be in Ukrainian, not English.',
        };
    }

    /**
     * A stronger, corrective instruction used only on a retry after a prior attempt failed the
     * language check - names the concrete failure rather than repeating the same instruction
     * verbatim, since simply repeating an already-ignored instruction is less likely to work.
     */
    public function correctivePromptInstruction(): string
    {
        return match ($this) {
            self::English => 'IMPORTANT: your previous response was not in English. You MUST '
                . 'respond entirely in English this time - every field, no exceptions.',
            self::Ukrainian => 'IMPORTANT: your previous response was not in Ukrainian. You MUST '
                . 'respond entirely in Ukrainian (українською мовою) this time - every field, no '
                . 'exceptions.',
        };
    }

    /**
     * Whether $text is actually written in this language, by the ratio of Cyrillic letters among
     * all letters. Ukrainian prose is overwhelmingly Cyrillic; English prose has essentially none
     * - a deliberately simple, dependency-free, deterministic check for exactly this language
     * pair (see this enum's docblock for why it doesn't need to be a general classifier).
     */
    public function matches(string $text): bool
    {
        $ratio = self::cyrillicRatio($text);

        return match ($this) {
            self::Ukrainian => $ratio >= 0.5,
            self::English => $ratio < 0.1,
        };
    }

    private static function cyrillicRatio(string $text): float
    {
        preg_match_all('/\p{L}/u', $text, $allLetters);
        $totalLetters = count($allLetters[0]);

        if ($totalLetters === 0) {
            // No letters at all (empty/whitespace/punctuation-only) - not Cyrillic by definition,
            // which conservatively fails the Ukrainian check rather than vacuously passing it.
            return 0.0;
        }

        preg_match_all('/\p{Cyrillic}/u', $text, $cyrillicLetters);

        return count($cyrillicLetters[0]) / $totalLetters;
    }
}
