<?php

declare(strict_types=1);

namespace App\Tests\AI;

use App\AI\InterpretationContext;
use App\AI\InterpretationLens;
use App\AI\InterpretationProfile;
use App\AI\MockInterpretationProvider;
use App\AI\ResponseLanguage;
use App\AI\ResponseLength;
use App\AI\Tone;
use App\Tests\Readings\Support\HexagramFixture;
use PHPUnit\Framework\TestCase;

final class MockInterpretationProviderTest extends TestCase
{
    use HexagramFixture;

    public function testInterpretsAConsultationWithChangingLines(): void
    {
        $primary = self::hexagramFromPattern('111111', changingPositions: [1]);
        $resulting = $primary->getResultingHexagram();

        $context = new InterpretationContext(
            'Should I take the offer?',
            $primary,
            [1],
            [1 => $primary->lineStatements[0]],
            $resulting,
            [],
        );

        $interpretation = (new MockInterpretationProvider())->interpret($context, InterpretationLens::General, InterpretationProfile::default(), ResponseLanguage::English);

        self::assertStringContainsString('Should I take the offer?', $interpretation->summary);
        self::assertStringContainsString((string) $primary->kingWenNumber, $interpretation->summary);
        self::assertSame($primary->judgment, $interpretation->coreTheme);
        self::assertSame($primary->image, $interpretation->situation);
        self::assertSame($primary->lineStatements[0], $interpretation->changingLineMeaning);
        self::assertStringContainsString((string) $resulting->kingWenNumber, $interpretation->transition ?? '');
        self::assertNotEmpty($interpretation->practicalReflection);
        self::assertNotEmpty($interpretation->uncertainties);
    }

    public function testChangingLineMeaningAndTransitionAreNullWithNoChangingLines(): void
    {
        $primary = self::hexagramFromPattern('111111');

        $context = new InterpretationContext(
            'Should I take the offer?',
            $primary,
            [],
            [],
            $primary,
            [],
        );

        $interpretation = (new MockInterpretationProvider())->interpret($context, InterpretationLens::General, InterpretationProfile::default(), ResponseLanguage::English);

        self::assertNull($interpretation->changingLineMeaning);
        self::assertNull($interpretation->transition);
    }

    public function testSourceReferencesNameExactlyWhatWasUsed(): void
    {
        $primary = self::hexagramFromPattern('111111', changingPositions: [1, 3]);
        $resulting = $primary->getResultingHexagram();

        $context = new InterpretationContext(
            'Should I take the offer?',
            $primary,
            [1, 3],
            [1 => $primary->lineStatements[0], 3 => $primary->lineStatements[2]],
            $resulting,
            [],
        );

        $interpretation = (new MockInterpretationProvider())->interpret($context, InterpretationLens::General, InterpretationProfile::default(), ResponseLanguage::English);

        $expected = [
            "Hexagram {$primary->kingWenNumber} judgment (Legge, 1899)",
            "Hexagram {$primary->kingWenNumber} image (Legge, 1899)",
            "Hexagram {$primary->kingWenNumber} line 1 (Legge, 1899)",
            "Hexagram {$primary->kingWenNumber} line 3 (Legge, 1899)",
            "Hexagram {$resulting->kingWenNumber} judgment (Legge, 1899) [resulting]",
        ];

        self::assertSame($expected, $interpretation->sourceReferences);
    }

    public function testSourceReferencesOmitResultingHexagramWithNoChangingLines(): void
    {
        $primary = self::hexagramFromPattern('111111');

        $context = new InterpretationContext('Q?', $primary, [], [], $primary, []);

        $interpretation = (new MockInterpretationProvider())->interpret($context, InterpretationLens::General, InterpretationProfile::default(), ResponseLanguage::English);

        self::assertCount(2, $interpretation->sourceReferences);
    }

    public function testGeneralLensAddsNoLensDisclosureToUncertainties(): void
    {
        $primary = self::hexagramFromPattern('111111');
        $context = new InterpretationContext('Q?', $primary, [], [], $primary, []);

        $interpretation = (new MockInterpretationProvider())->interpret($context, InterpretationLens::General, InterpretationProfile::default(), ResponseLanguage::English);

        foreach ($interpretation->uncertainties as $uncertainty) {
            self::assertStringNotContainsString('Requested lens', $uncertainty);
        }
    }

    public function testNonGeneralLensDisclosesItselfInUncertaintiesWithoutChangingOtherFields(): void
    {
        $primary = self::hexagramFromPattern('111111', changingPositions: [1]);
        $resulting = $primary->getResultingHexagram();
        $context = new InterpretationContext(
            'Should I take the offer?',
            $primary,
            [1],
            [1 => $primary->lineStatements[0]],
            $resulting,
            [],
        );

        $general = (new MockInterpretationProvider())->interpret($context, InterpretationLens::General, InterpretationProfile::default(), ResponseLanguage::English);
        $psychological = (new MockInterpretationProvider())->interpret($context, InterpretationLens::Psychological, InterpretationProfile::default(), ResponseLanguage::English);

        self::assertSame($general->summary, $psychological->summary);
        self::assertSame($general->coreTheme, $psychological->coreTheme);
        self::assertSame($general->situation, $psychological->situation);
        self::assertSame($general->changingLineMeaning, $psychological->changingLineMeaning);
        self::assertSame($general->transition, $psychological->transition);
        self::assertSame($general->practicalReflection, $psychological->practicalReflection);

        self::assertStringContainsString(
            'Requested lens: psychological',
            implode(' ', $psychological->uncertainties),
        );
    }

    public function testAnswerFollowUpNamesTheQuestionAndDisclosesItsAMockPlaceholder(): void
    {
        $primary = self::hexagramFromPattern('111111');
        $context = new InterpretationContext('Q?', $primary, [], [], $primary, []);

        $answer = (new MockInterpretationProvider())
            ->answerFollowUp($context, [], 'What should I avoid doing?', InterpretationProfile::default(), ResponseLanguage::English);

        self::assertStringContainsString('What should I avoid doing?', $answer->answer);
        self::assertStringContainsString('mock', strtolower($answer->answer));
        self::assertSame($context->defaultSourceReferences(), $answer->sourceReferences);
    }

    public function testDefaultProfileAddsNoDisclosure(): void
    {
        $primary = self::hexagramFromPattern('111111');
        $context = new InterpretationContext('Q?', $primary, [], [], $primary, []);

        $interpretation = (new MockInterpretationProvider())
            ->interpret($context, InterpretationLens::General, InterpretationProfile::default(), ResponseLanguage::English);

        foreach ($interpretation->uncertainties as $uncertainty) {
            self::assertStringNotContainsString('Active interpretation profile', $uncertainty);
        }
    }

    public function testNonDefaultProfileDisclosesItselfWithoutChangingCanonicalFields(): void
    {
        $primary = self::hexagramFromPattern('111111', changingPositions: [1]);
        $resulting = $primary->getResultingHexagram();
        $context = new InterpretationContext(
            'Should I take the offer?',
            $primary,
            [1],
            [1 => $primary->lineStatements[0]],
            $resulting,
            [],
        );
        $profile = new InterpretationProfile(Tone::Formal, ResponseLength::Detailed);

        $default = (new MockInterpretationProvider())
            ->interpret($context, InterpretationLens::General, InterpretationProfile::default(), ResponseLanguage::English);
        $withProfile = (new MockInterpretationProvider())
            ->interpret($context, InterpretationLens::General, $profile, ResponseLanguage::English);

        self::assertSame($default->summary, $withProfile->summary);
        self::assertSame($default->practicalReflection, $withProfile->practicalReflection);
        self::assertStringContainsString(
            'Active interpretation profile: tone=formal, length=detailed',
            implode(' ', $withProfile->uncertainties),
        );
    }

    public function testAnswerFollowUpDisclosesANonDefaultProfile(): void
    {
        $primary = self::hexagramFromPattern('111111');
        $context = new InterpretationContext('Q?', $primary, [], [], $primary, []);
        $profile = new InterpretationProfile(notes: 'Be direct.');

        $answer = (new MockInterpretationProvider())->answerFollowUp($context, [], 'Q?', $profile, ResponseLanguage::English);

        self::assertStringContainsString('notes set', $answer->answer);
    }
}
