<?php

declare(strict_types=1);

namespace App\Tests\AI;

use App\AI\GeminiInterpretationProvider;
use App\AI\HttpGeminiClient;
use App\AI\InterpretationContext;
use App\AI\InterpretationLens;
use App\AI\InterpretationProfile;
use App\AI\InterpretationProviderException;
use App\AI\ResponseLanguage;
use App\Tests\AI\Support\FakeHttpTransport;
use App\Tests\Readings\Support\HexagramFixture;
use PHPUnit\Framework\TestCase;

/**
 * Pins the wire contract of `HttpGeminiClient` (SPEC-040): the request it builds for Google's
 * `POST /v1beta/models/{model}:generateContent` endpoint, and how it unwraps the response. The
 * live-key check stays a manual step (SPEC-011); these run offline over a recorded fixture.
 */
final class HttpGeminiClientTest extends TestCase
{
    use HexagramFixture;

    private const MODEL = 'gemini-3.6-flash';
    private const API_KEY = 'test-key-123';

    /**
     * @var array<string, mixed>
     */
    private const SCHEMA = [
        'type' => 'object',
        'properties' => ['answer' => ['type' => 'string']],
        'required' => ['answer'],
    ];

    private static function fixture(): string
    {
        $raw = file_get_contents(__DIR__ . '/fixtures/gemini-generate-content-response.json');
        self::assertIsString($raw);

        return $raw;
    }

    public function testPostsToTheGenerateContentEndpointForTheExactModelWithNoKeyInTheUrl(): void
    {
        $transport = new FakeHttpTransport(200, self::fixture());

        (new HttpGeminiClient(self::API_KEY, self::MODEL, $transport))->generateJson('prompt', self::SCHEMA);

        self::assertNotNull($transport->lastCall);
        self::assertSame(
            'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.6-flash:generateContent',
            $transport->lastCall['url'],
        );
        self::assertStringNotContainsString('key=', $transport->lastCall['url']);
        self::assertStringNotContainsString(self::API_KEY, $transport->lastCall['url']);
    }

    public function testSendsTheApiKeyAndContentTypeAsHeaders(): void
    {
        $transport = new FakeHttpTransport(200, self::fixture());

        (new HttpGeminiClient(self::API_KEY, self::MODEL, $transport))->generateJson('prompt', self::SCHEMA);

        self::assertNotNull($transport->lastCall);
        self::assertSame('application/json', $transport->lastCall['headers']['Content-Type']);
        self::assertSame(self::API_KEY, $transport->lastCall['headers']['x-goog-api-key']);
    }

    public function testBuildsTheGenerateContentRequestBodyShape(): void
    {
        $transport = new FakeHttpTransport(200, self::fixture());
        $prompt = "Interpret this reading.\nQuestion: Should I go?";

        (new HttpGeminiClient(self::API_KEY, self::MODEL, $transport))->generateJson($prompt, self::SCHEMA);

        self::assertNotNull($transport->lastCall);
        $body = json_decode($transport->lastCall['body'], true);
        self::assertIsArray($body);

        self::assertSame(['contents', 'generationConfig'], array_keys($body));
        self::assertSame($prompt, $body['contents'][0]['parts'][0]['text']);
        self::assertSame('application/json', $body['generationConfig']['responseMimeType']);
        self::assertSame(self::SCHEMA, $body['generationConfig']['responseSchema']);
    }

    public function testUnwrapsAndReDecodesTheInnerJsonFromTheRecordedResponse(): void
    {
        $transport = new FakeHttpTransport(200, self::fixture());

        $result = (new HttpGeminiClient(self::API_KEY, self::MODEL, $transport))
            ->generateJson('prompt', self::SCHEMA);

        // The returned array is the object encoded as a *string* inside
        // candidates[0].content.parts[0].text, decoded one level further — not the envelope.
        self::assertArrayHasKey('summary', $result);
        self::assertArrayHasKey('coreTheme', $result);
        self::assertArrayHasKey('uncertainties', $result);
        self::assertArrayNotHasKey('candidates', $result);
        self::assertIsArray($result['uncertainties']);
        self::assertStringContainsString('measured restraint', (string) $result['summary']);
    }

    public function testThrowsOnANonTwoHundredStatusIncludingCodeAndBody(): void
    {
        $errorBody = '{"error":{"code":400,"message":"Invalid JSON payload"}}';
        $transport = new FakeHttpTransport(400, $errorBody);

        try {
            (new HttpGeminiClient(self::API_KEY, self::MODEL, $transport))->generateJson('prompt', self::SCHEMA);
            self::fail('Expected InterpretationProviderException.');
        } catch (InterpretationProviderException $e) {
            self::assertStringContainsString('HTTP 400', $e->getMessage());
            self::assertStringContainsString('Invalid JSON payload', $e->getMessage());
        }
    }

    public function testThrowsWhenTheEnvelopeHasNoCandidateText(): void
    {
        $transport = new FakeHttpTransport(200, '{"candidates":[{"content":{"parts":[]}}]}');

        $this->expectException(InterpretationProviderException::class);
        $this->expectExceptionMessage('candidates[0].content.parts[0].text');

        (new HttpGeminiClient(self::API_KEY, self::MODEL, $transport))->generateJson('prompt', self::SCHEMA);
    }

    public function testThrowsWhenTheCandidateTextIsAnEmptyString(): void
    {
        $transport = new FakeHttpTransport(200, '{"candidates":[{"content":{"parts":[{"text":""}]}}]}');

        $this->expectException(InterpretationProviderException::class);

        (new HttpGeminiClient(self::API_KEY, self::MODEL, $transport))->generateJson('prompt', self::SCHEMA);
    }

    public function testThrowsWhenTheCandidateTextIsNotValidJson(): void
    {
        $transport = new FakeHttpTransport(200, '{"candidates":[{"content":{"parts":[{"text":"not json at all"}]}}]}');

        $this->expectException(InterpretationProviderException::class);
        $this->expectExceptionMessage('was not valid JSON');

        (new HttpGeminiClient(self::API_KEY, self::MODEL, $transport))->generateJson('prompt', self::SCHEMA);
    }

    public function testPropagatesAnUnreachableHostFailureFromTheTransport(): void
    {
        $transport = new FakeHttpTransport(
            throw: new InterpretationProviderException('Could not reach the Gemini API.'),
        );

        $this->expectException(InterpretationProviderException::class);
        $this->expectExceptionMessage('Could not reach the Gemini API.');

        (new HttpGeminiClient(self::API_KEY, self::MODEL, $transport))->generateJson('prompt', self::SCHEMA);
    }

    public function testGeminiInterpretationProviderComposesOverTheRealClientAndTheRecordedResponse(): void
    {
        $primary = self::hexagramFromPattern('111111', changingPositions: [1]);
        $resulting = $primary->getResultingHexagram();
        $context = new InterpretationContext(
            'Should I take the offer?',
            $primary,
            [1],
            [1 => $primary->lineStatements[0]],
            $resulting,
            ['Feeling uncertain.'],
        );

        $client = new HttpGeminiClient(self::API_KEY, self::MODEL, new FakeHttpTransport(200, self::fixture()));

        $interpretation = (new GeminiInterpretationProvider($client))->interpret(
            $context,
            InterpretationLens::General,
            InterpretationProfile::default(),
            ResponseLanguage::English,
        );

        self::assertNotSame('', trim($interpretation->summary));
        self::assertNotSame('', trim($interpretation->coreTheme));
        self::assertNotSame('', trim($interpretation->situation));
        self::assertNotSame('', trim($interpretation->practicalReflection));
        self::assertNotNull($interpretation->changingLineMeaning);
        self::assertNotNull($interpretation->transition);
        self::assertNotEmpty($interpretation->uncertainties);
        self::assertSame($context->defaultSourceReferences(), $interpretation->sourceReferences);
    }
}
