<?php

declare(strict_types=1);

namespace App\Tests\AI;

use App\AI\ConversationExchange;
use App\AI\GeminiInterpretationProvider;
use App\AI\InterpretationContext;
use App\AI\InterpretationLens;
use App\AI\InterpretationProfile;
use App\AI\InterpretationProviderException;
use App\AI\ResponseLanguage;
use App\AI\ResponseLength;
use App\AI\Tone;
use App\Tests\AI\Support\FakeGeminiClient;
use App\Tests\Readings\Support\HexagramFixture;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class GeminiInterpretationProviderTest extends TestCase
{
    use HexagramFixture;

    private function contextWithChangingLine(): InterpretationContext
    {
        $primary = self::hexagramFromPattern('111111', changingPositions: [1]);
        $resulting = $primary->getResultingHexagram();

        return new InterpretationContext(
            'Should I take the offer?',
            $primary,
            [1],
            [1 => $primary->lineStatements[0]],
            $resulting,
            ['Feeling uncertain.'],
        );
    }

    public function testMapsAWellFormedResponseToAnInterpretation(): void
    {
        $client = new FakeGeminiClient([
            'summary' => 'A time for bold, well-considered action.',
            'coreTheme' => 'Creative strength meeting a threshold.',
            'situation' => 'The situation calls for readiness, not yet action.',
            'changingLineMeaning' => 'A hidden potential not yet ready to act.',
            'transition' => 'Moving toward a phase of quiet withdrawal.',
            'practicalReflection' => 'Consider whether the timing truly serves you.',
            'uncertainties' => ['Timing is genuinely ambiguous here.'],
            'sourceReferences' => ['this should be ignored'],
        ]);

        $interpretation = (new GeminiInterpretationProvider($client))
            ->interpret($this->contextWithChangingLine(), InterpretationLens::General, InterpretationProfile::default(), ResponseLanguage::English);

        self::assertSame('A time for bold, well-considered action.', $interpretation->summary);
        self::assertSame('Creative strength meeting a threshold.', $interpretation->coreTheme);
        self::assertSame('The situation calls for readiness, not yet action.', $interpretation->situation);
        self::assertSame('A hidden potential not yet ready to act.', $interpretation->changingLineMeaning);
        self::assertSame('Moving toward a phase of quiet withdrawal.', $interpretation->transition);
        self::assertSame('Consider whether the timing truly serves you.', $interpretation->practicalReflection);
        self::assertSame(['Timing is genuinely ambiguous here.'], $interpretation->uncertainties);
    }

    public function testSourceReferencesIsAlwaysTheContextsOwnComputationNeverTheModels(): void
    {
        $context = $this->contextWithChangingLine();
        $client = new FakeGeminiClient([
            'summary' => 's',
            'coreTheme' => 'c',
            'situation' => 'si',
            'practicalReflection' => 'p',
            'uncertainties' => [],
            'sourceReferences' => ['a fabricated citation the model made up'],
        ]);

        $interpretation = (new GeminiInterpretationProvider($client))->interpret($context, InterpretationLens::General, InterpretationProfile::default(), ResponseLanguage::English);

        self::assertSame($context->defaultSourceReferences(), $interpretation->sourceReferences);
        self::assertNotContains('a fabricated citation the model made up', $interpretation->sourceReferences);
    }

    public function testTreatsMissingChangingLineMeaningAndTransitionAsNull(): void
    {
        $primary = self::hexagramFromPattern('111111');
        $context = new InterpretationContext('Q?', $primary, [], [], $primary, []);

        $client = new FakeGeminiClient([
            'summary' => 's',
            'coreTheme' => 'c',
            'situation' => 'si',
            'practicalReflection' => 'p',
            'uncertainties' => [],
        ]);

        $interpretation = (new GeminiInterpretationProvider($client))->interpret($context, InterpretationLens::General, InterpretationProfile::default(), ResponseLanguage::English);

        self::assertNull($interpretation->changingLineMeaning);
        self::assertNull($interpretation->transition);
    }

    public function testThrowsWhenARequiredFieldIsMissing(): void
    {
        $client = new FakeGeminiClient([
            'coreTheme' => 'c',
            'situation' => 'si',
            'practicalReflection' => 'p',
            'uncertainties' => [],
            // 'summary' missing
        ]);

        $this->expectException(InterpretationProviderException::class);

        (new GeminiInterpretationProvider($client))->interpret($this->contextWithChangingLine(), InterpretationLens::General, InterpretationProfile::default(), ResponseLanguage::English);
    }

    public function testThrowsWhenARequiredFieldIsEmpty(): void
    {
        $client = new FakeGeminiClient([
            'summary' => '   ',
            'coreTheme' => 'c',
            'situation' => 'si',
            'practicalReflection' => 'p',
            'uncertainties' => [],
        ]);

        $this->expectException(InterpretationProviderException::class);

        (new GeminiInterpretationProvider($client))->interpret($this->contextWithChangingLine(), InterpretationLens::General, InterpretationProfile::default(), ResponseLanguage::English);
    }

    public function testThrowsWhenUncertaintiesIsNotAnArray(): void
    {
        $client = new FakeGeminiClient([
            'summary' => 's',
            'coreTheme' => 'c',
            'situation' => 'si',
            'practicalReflection' => 'p',
            'uncertainties' => 'not an array',
        ]);

        $this->expectException(InterpretationProviderException::class);

        (new GeminiInterpretationProvider($client))->interpret($this->contextWithChangingLine(), InterpretationLens::General, InterpretationProfile::default(), ResponseLanguage::English);
    }

    public function testPropagatesAClientFailure(): void
    {
        $client = new FakeGeminiClient(failureMessage: 'Gemini API returned HTTP 401: invalid key');

        $this->expectException(InterpretationProviderException::class);
        $this->expectExceptionMessage('Gemini API returned HTTP 401: invalid key');

        (new GeminiInterpretationProvider($client))->interpret($this->contextWithChangingLine(), InterpretationLens::General, InterpretationProfile::default(), ResponseLanguage::English);
    }

    public function testPromptGroundsOnlyInTheContextsOwnCanonicalText(): void
    {
        $client = new FakeGeminiClient([
            'summary' => 's',
            'coreTheme' => 'c',
            'situation' => 'si',
            'practicalReflection' => 'p',
            'uncertainties' => [],
        ]);
        $context = $this->contextWithChangingLine();

        (new GeminiInterpretationProvider($client))->interpret($context, InterpretationLens::General, InterpretationProfile::default(), ResponseLanguage::English);

        self::assertNotNull($client->lastCall);
        $prompt = $client->lastCall['prompt'];

        self::assertStringContainsString($context->question, $prompt);
        self::assertStringContainsString($context->primaryHexagram->judgment, $prompt);
        self::assertStringContainsString($context->primaryHexagram->image, $prompt);
        self::assertStringContainsString($context->changingLineStatements[1], $prompt);
        self::assertStringContainsString('Feeling uncertain.', $prompt);
    }

    public function testGeneralLensPromptIsByteIdenticalRegardlessOfBeingPassedExplicitly(): void
    {
        $client = new FakeGeminiClient([
            'summary' => 's', 'coreTheme' => 'c', 'situation' => 'si',
            'practicalReflection' => 'p', 'uncertainties' => [],
        ]);
        $context = $this->contextWithChangingLine();

        (new GeminiInterpretationProvider($client))->interpret($context, InterpretationLens::General, InterpretationProfile::default(), ResponseLanguage::English);

        self::assertNotNull($client->lastCall);
        self::assertStringNotContainsString('Focus especially', $client->lastCall['prompt']);
    }

    /**
     * @return list<array{InterpretationLens, string}>
     */
    public static function nonGeneralLenses(): array
    {
        return [
            [InterpretationLens::Psychological, 'psychological'],
            [InterpretationLens::Practical, 'concrete, actionable practical'],
            [InterpretationLens::Symbolic, 'symbolic and archetypal'],
        ];
    }

    #[DataProvider('nonGeneralLenses')]
    public function testEachNonGeneralLensAddsExactlyOneDistinctFramingSentence(
        InterpretationLens $lens,
        string $expectedFragment,
    ): void {
        $client = new FakeGeminiClient([
            'summary' => 's', 'coreTheme' => 'c', 'situation' => 'si',
            'practicalReflection' => 'p', 'uncertainties' => [],
        ]);
        $context = $this->contextWithChangingLine();

        (new GeminiInterpretationProvider($client))->interpret($context, InterpretationLens::General, InterpretationProfile::default(), ResponseLanguage::English);
        self::assertNotNull($client->lastCall);
        $generalPrompt = $client->lastCall['prompt'];

        (new GeminiInterpretationProvider($client))->interpret($context, $lens, InterpretationProfile::default(), ResponseLanguage::English);
        self::assertNotNull($client->lastCall);
        $lensPrompt = $client->lastCall['prompt'];

        self::assertStringContainsString('Focus especially', $lensPrompt);
        self::assertStringContainsStringIgnoringCase($expectedFragment, $lensPrompt);
        // The lens prompt is the general prompt plus exactly one appended sentence — every
        // context-grounding line (question, judgment, image, changing lines, notes) still
        // present, nothing about the base prompt altered.
        self::assertStringContainsString($context->question, $lensPrompt);
        self::assertGreaterThan(mb_strlen($generalPrompt), mb_strlen($lensPrompt));
    }

    public function testAnswerFollowUpMapsAWellFormedResponse(): void
    {
        $client = new FakeGeminiClient(['answer' => 'The dragon suggests holding back for now.']);
        $context = $this->contextWithChangingLine();

        $answer = (new GeminiInterpretationProvider($client))->answerFollowUp($context, [], 'What should I avoid?', InterpretationProfile::default(), ResponseLanguage::English);

        self::assertSame('The dragon suggests holding back for now.', $answer->answer);
        self::assertSame($context->defaultSourceReferences(), $answer->sourceReferences);
    }

    public function testAnswerFollowUpThrowsWhenAnswerIsMissing(): void
    {
        $client = new FakeGeminiClient([]);
        $context = $this->contextWithChangingLine();

        $this->expectException(InterpretationProviderException::class);

        (new GeminiInterpretationProvider($client))->answerFollowUp($context, [], 'What should I avoid?', InterpretationProfile::default(), ResponseLanguage::English);
    }

    public function testAnswerFollowUpPromptIncludesContextHistoryAndNewQuestion(): void
    {
        $client = new FakeGeminiClient(['answer' => 'a']);
        $context = $this->contextWithChangingLine();
        $history = [new ConversationExchange('What does line 1 mean?', 'It means patience.')];

        (new GeminiInterpretationProvider($client))->answerFollowUp($context, $history, 'And the transition?', InterpretationProfile::default(), ResponseLanguage::English);

        self::assertNotNull($client->lastCall);
        $prompt = $client->lastCall['prompt'];

        self::assertStringContainsString($context->question, $prompt);
        self::assertStringContainsString($context->primaryHexagram->judgment, $prompt);
        self::assertStringContainsString('What does line 1 mean?', $prompt);
        self::assertStringContainsString('It means patience.', $prompt);
        self::assertStringContainsString('And the transition?', $prompt);
    }

    public function testAnswerFollowUpPropagatesAClientFailure(): void
    {
        $client = new FakeGeminiClient(failureMessage: 'Gemini API returned HTTP 401: invalid key');
        $context = $this->contextWithChangingLine();

        $this->expectException(InterpretationProviderException::class);
        $this->expectExceptionMessage('Gemini API returned HTTP 401: invalid key');

        (new GeminiInterpretationProvider($client))->answerFollowUp($context, [], 'Q?', InterpretationProfile::default(), ResponseLanguage::English);
    }

    public function testDefaultProfilePromptIsByteIdenticalToNoProfileCase(): void
    {
        $client = new FakeGeminiClient([
            'summary' => 's', 'coreTheme' => 'c', 'situation' => 'si',
            'practicalReflection' => 'p', 'uncertainties' => [],
        ]);
        $context = $this->contextWithChangingLine();

        (new GeminiInterpretationProvider($client))
            ->interpret($context, InterpretationLens::General, InterpretationProfile::default(), ResponseLanguage::English);

        self::assertNotNull($client->lastCall);
        self::assertStringNotContainsString('Personal preferences', $client->lastCall['prompt']);
    }

    public function testNonDefaultProfileAddsExactlyOnePreferencesBlockNamingOnlyChangedFields(): void
    {
        $client = new FakeGeminiClient([
            'summary' => 's', 'coreTheme' => 'c', 'situation' => 'si',
            'practicalReflection' => 'p', 'uncertainties' => [],
        ]);
        $context = $this->contextWithChangingLine();
        $profile = new InterpretationProfile(Tone::Poetic);

        (new GeminiInterpretationProvider($client))->interpret($context, InterpretationLens::General, $profile, ResponseLanguage::English);

        self::assertNotNull($client->lastCall);
        $prompt = $client->lastCall['prompt'];

        self::assertSame(1, substr_count($prompt, 'Personal preferences'));
        self::assertStringContainsString('poetic', $prompt);
        self::assertStringNotContainsString('keep the response', $prompt);
    }

    public function testProfileNotesAppearInThePromptWhenSet(): void
    {
        $client = new FakeGeminiClient([
            'summary' => 's', 'coreTheme' => 'c', 'situation' => 'si',
            'practicalReflection' => 'p', 'uncertainties' => [],
        ]);
        $context = $this->contextWithChangingLine();
        $profile = new InterpretationProfile(notes: 'I appreciate directness.');

        (new GeminiInterpretationProvider($client))->interpret($context, InterpretationLens::General, $profile, ResponseLanguage::English);

        self::assertNotNull($client->lastCall);
        self::assertStringContainsString('I appreciate directness.', $client->lastCall['prompt']);
    }

    public function testNonDefaultProfileAlsoAppliesToFollowUpPrompts(): void
    {
        $client = new FakeGeminiClient(['answer' => 'a']);
        $context = $this->contextWithChangingLine();
        $profile = new InterpretationProfile(length: ResponseLength::Brief);

        (new GeminiInterpretationProvider($client))->answerFollowUp($context, [], 'Q?', $profile, ResponseLanguage::English);

        self::assertNotNull($client->lastCall);
        self::assertStringContainsString('brief', $client->lastCall['prompt']);
    }

    public function testPromptAlwaysNamesTheRequestedLanguageExplicitly(): void
    {
        $client = new FakeGeminiClient([
            'summary' => 'Час для дій.', 'coreTheme' => 'Тема.', 'situation' => 'Ситуація.',
            'practicalReflection' => 'Порада.', 'uncertainties' => [],
        ]);
        $context = $this->contextWithChangingLine();

        (new GeminiInterpretationProvider($client))
            ->interpret($context, InterpretationLens::General, InterpretationProfile::default(), ResponseLanguage::Ukrainian);

        self::assertNotNull($client->lastCall);
        self::assertStringContainsString('Ukrainian', $client->lastCall['prompt']);
    }

    public function testRetriesOnceWhenTheFirstResponseIsInTheWrongLanguageThenReturnsTheMatchingOne(): void
    {
        $client = new FakeGeminiClient(responses: [
            [
                // Wrong language — English, though Ukrainian was requested.
                'summary' => 'A time for bold action.', 'coreTheme' => 'c', 'situation' => 'si',
                'practicalReflection' => 'p', 'uncertainties' => [],
            ],
            [
                'summary' => 'Час для сміливих дій.', 'coreTheme' => 'Творча сила.',
                'situation' => 'Ситуація вимагає готовності.',
                'practicalReflection' => 'Подумайте, чи служить вам цей час.', 'uncertainties' => [],
            ],
        ]);
        $context = $this->contextWithChangingLine();

        $interpretation = (new GeminiInterpretationProvider($client))
            ->interpret($context, InterpretationLens::General, InterpretationProfile::default(), ResponseLanguage::Ukrainian);

        self::assertSame('Час для сміливих дій.', $interpretation->summary);
        self::assertCount(2, $client->calls);
        self::assertStringContainsString('previous response was not in Ukrainian', $client->calls[1]['prompt']);
    }

    public function testThrowsAfterExhaustingAllLanguageAttempts(): void
    {
        // Every attempt comes back in English, but Ukrainian was requested — never satisfied.
        $englishResponse = [
            'summary' => 's', 'coreTheme' => 'c', 'situation' => 'si',
            'practicalReflection' => 'p', 'uncertainties' => [],
        ];
        $client = new FakeGeminiClient(responses: [$englishResponse, $englishResponse, $englishResponse]);
        $context = $this->contextWithChangingLine();

        $this->expectException(InterpretationProviderException::class);
        $this->expectExceptionMessage('Gemini failed to respond in the requested language (uk) after 3 attempts.');

        try {
            (new GeminiInterpretationProvider($client))
                ->interpret($context, InterpretationLens::General, InterpretationProfile::default(), ResponseLanguage::Ukrainian);
        } finally {
            self::assertCount(3, $client->calls);
        }
    }

    public function testAnswerFollowUpRetriesOnceWhenTheFirstAnswerIsInTheWrongLanguage(): void
    {
        $client = new FakeGeminiClient(responses: [
            ['answer' => 'You should avoid rushing.'],
            ['answer' => 'Вам слід уникати поспіху.'],
        ]);
        $context = $this->contextWithChangingLine();

        $answer = (new GeminiInterpretationProvider($client))
            ->answerFollowUp($context, [], 'What should I avoid?', InterpretationProfile::default(), ResponseLanguage::Ukrainian);

        self::assertSame('Вам слід уникати поспіху.', $answer->answer);
        self::assertCount(2, $client->calls);
    }

    public function testAnswerFollowUpThrowsAfterExhaustingAllLanguageAttempts(): void
    {
        $englishAnswer = ['answer' => 'You should avoid rushing.'];
        $client = new FakeGeminiClient(responses: [$englishAnswer, $englishAnswer, $englishAnswer]);
        $context = $this->contextWithChangingLine();

        $this->expectException(InterpretationProviderException::class);

        try {
            (new GeminiInterpretationProvider($client))
                ->answerFollowUp($context, [], 'Q?', InterpretationProfile::default(), ResponseLanguage::Ukrainian);
        } finally {
            self::assertCount(3, $client->calls);
        }
    }
}
