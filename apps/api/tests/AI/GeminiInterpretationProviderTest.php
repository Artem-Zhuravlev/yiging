<?php

declare(strict_types=1);

namespace App\Tests\AI;

use App\AI\GeminiInterpretationProvider;
use App\AI\InterpretationContext;
use App\AI\InterpretationProviderException;
use App\Tests\AI\Support\FakeGeminiClient;
use App\Tests\Readings\Support\HexagramFixture;
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

        $interpretation = (new GeminiInterpretationProvider($client))->interpret($this->contextWithChangingLine());

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

        $interpretation = (new GeminiInterpretationProvider($client))->interpret($context);

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

        $interpretation = (new GeminiInterpretationProvider($client))->interpret($context);

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

        (new GeminiInterpretationProvider($client))->interpret($this->contextWithChangingLine());
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

        (new GeminiInterpretationProvider($client))->interpret($this->contextWithChangingLine());
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

        (new GeminiInterpretationProvider($client))->interpret($this->contextWithChangingLine());
    }

    public function testPropagatesAClientFailure(): void
    {
        $client = new FakeGeminiClient(failureMessage: 'Gemini API returned HTTP 401: invalid key');

        $this->expectException(InterpretationProviderException::class);
        $this->expectExceptionMessage('Gemini API returned HTTP 401: invalid key');

        (new GeminiInterpretationProvider($client))->interpret($this->contextWithChangingLine());
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

        (new GeminiInterpretationProvider($client))->interpret($context);

        self::assertNotNull($client->lastCall);
        $prompt = $client->lastCall['prompt'];

        self::assertStringContainsString($context->question, $prompt);
        self::assertStringContainsString($context->primaryHexagram->judgment, $prompt);
        self::assertStringContainsString($context->primaryHexagram->image, $prompt);
        self::assertStringContainsString($context->changingLineStatements[1], $prompt);
        self::assertStringContainsString('Feeling uncertain.', $prompt);
    }
}
