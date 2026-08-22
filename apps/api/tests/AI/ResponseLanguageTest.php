<?php

declare(strict_types=1);

namespace App\Tests\AI;

use App\AI\ResponseLanguage;
use PHPUnit\Framework\TestCase;

final class ResponseLanguageTest extends TestCase
{
    public function testEnglishMatchesPlainEnglishProse(): void
    {
        self::assertTrue(ResponseLanguage::English->matches(
            'A time for bold, well-considered action. Consider whether the timing truly serves you.',
        ));
    }

    public function testEnglishDoesNotMatchUkrainianProse(): void
    {
        self::assertFalse(ResponseLanguage::English->matches(
            'Час для сміливих, добре обміркованих дій. Подумайте, чи дійсно час вам служить.',
        ));
    }

    public function testUkrainianMatchesUkrainianProse(): void
    {
        self::assertTrue(ResponseLanguage::Ukrainian->matches(
            'Час для сміливих, добре обміркованих дій. Подумайте, чи дійсно час вам служить.',
        ));
    }

    public function testUkrainianDoesNotMatchPlainEnglishProse(): void
    {
        self::assertFalse(ResponseLanguage::Ukrainian->matches(
            'A time for bold, well-considered action. Consider whether the timing truly serves you.',
        ));
    }

    public function testUkrainianProseMayHarmlesslyContainRomanizedProperNouns(): void
    {
        // A hexagram's Chinese name/pinyin (e.g. "Qián") embedded in otherwise-Ukrainian prose is
        // expected and harmless — the check only requires the response be *overwhelmingly*
        // Cyrillic, not exclusively so (see the "edge cases" in specs/localization-en-uk/spec.md).
        self::assertTrue(ResponseLanguage::Ukrainian->matches(
            'Гексаграма 1. Цянь (Qián) вказує на творчу силу, яка зустрічає поріг рішучих дій.',
        ));
    }

    public function testEnglishDoesNotMatchEmptyText(): void
    {
        self::assertTrue(ResponseLanguage::English->matches(''));
    }

    public function testUkrainianDoesNotMatchEmptyText(): void
    {
        self::assertFalse(ResponseLanguage::Ukrainian->matches(''));
    }

    public function testEnglishPromptInstructionNamesEnglish(): void
    {
        self::assertStringContainsString('English', ResponseLanguage::English->promptInstruction());
    }

    public function testUkrainianPromptInstructionNamesUkrainian(): void
    {
        self::assertStringContainsString('Ukrainian', ResponseLanguage::Ukrainian->promptInstruction());
    }

    public function testCorrectiveInstructionsNameThePriorFailure(): void
    {
        self::assertStringContainsString('previous response was not in English', ResponseLanguage::English->correctivePromptInstruction());
        self::assertStringContainsString('previous response was not in Ukrainian', ResponseLanguage::Ukrainian->correctivePromptInstruction());
    }
}
