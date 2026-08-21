<?php

declare(strict_types=1);

namespace App\Tests\AI;

use App\Core\Config;
use App\Core\Database;
use App\Core\Kernel;
use App\Readings\CastingMethodName;
use App\Readings\Consultation;
use App\Readings\SqliteConsultationRepository;
use App\Tests\Readings\Support\HexagramFixture;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class InterpretationControllerTest extends TestCase
{
    use HexagramFixture;

    private string $databasePath;
    private Kernel $kernel;
    private SqliteConsultationRepository $repository;

    protected function setUp(): void
    {
        $apiRoot = dirname(__DIR__, 2);
        $tempName = tempnam(sys_get_temp_dir(), 'yijing_test_');
        self::assertNotFalse($tempName);
        $this->databasePath = $tempName . '.sqlite';

        $config = new Config(['app_env' => 'testing', 'database_path' => $this->databasePath]);

        $pdo = Database::connect($config);
        $migrationsDir = $apiRoot . '/database/migrations';
        $files = glob($migrationsDir . '/*.php') ?: [];
        sort($files);

        foreach ($files as $file) {
            /** @var array{up: string} $migration */
            $migration = require $file;
            $pdo->exec($migration['up']);
        }

        $this->repository = new SqliteConsultationRepository($pdo);

        $routeDefinitions = require $apiRoot . '/config/routes.php';
        $this->kernel = new Kernel($config, $routeDefinitions);
    }

    protected function tearDown(): void
    {
        // Release the PDO connection first - on Windows, unlink() fails while any handle to
        // the SQLite file is still open, and $this->repository would otherwise outlive this
        // method (it's a property of the still-live test instance).
        unset($this->repository);

        if (is_file($this->databasePath)) {
            unlink($this->databasePath);
        }
    }

    public function testReturnsAnInterpretationForAnExistingConsultation(): void
    {
        $primary = self::hexagramFromPattern('111111', changingPositions: [1]);
        $consultation = Consultation::create(
            'consult-1',
            'Should I take the offer?',
            CastingMethodName::ThreeCoins,
            $primary,
            new \DateTimeImmutable(),
        );
        $this->repository->save($consultation);

        $response = $this->kernel->handle(Request::create('/api/interpretations/consult-1', 'POST'));

        self::assertSame(200, $response->getStatusCode());
        $body = $this->decode($response);

        self::assertStringContainsString('Should I take the offer?', $body['summary']);
        self::assertNotEmpty($body['coreTheme']);
        self::assertNotEmpty($body['situation']);
        self::assertNotNull($body['changingLineMeaning']);
        self::assertNotNull($body['transition']);
        self::assertNotEmpty($body['sourceReferences']);
        self::assertSame('general', $body['lens']);
    }

    public function testAcceptsAnExplicitLensAndEchoesItInTheResponse(): void
    {
        $primary = self::hexagramFromPattern('111111', changingPositions: [1]);
        $consultation = Consultation::create(
            'consult-1',
            'Should I take the offer?',
            CastingMethodName::ThreeCoins,
            $primary,
            new \DateTimeImmutable(),
        );
        $this->repository->save($consultation);

        $response = $this->kernel->handle(Request::create(
            '/api/interpretations/consult-1',
            'POST',
            content: json_encode(['lens' => 'psychological'], JSON_THROW_ON_ERROR),
        ));

        self::assertSame(200, $response->getStatusCode());
        $body = $this->decode($response);
        self::assertSame('psychological', $body['lens']);
        self::assertStringContainsString('Requested lens: psychological', implode(' ', $body['uncertainties']));
    }

    public function testRejectsAnInvalidLensWith422BeforeTouchingTheConsultation(): void
    {
        $response = $this->kernel->handle(Request::create(
            '/api/interpretations/does-not-exist',
            'POST',
            content: json_encode(['lens' => 'not-a-real-lens'], JSON_THROW_ON_ERROR),
        ));

        // 422, not 404 - proves lens validation happens before the repository lookup, even
        // though the id in this test doesn't exist either.
        self::assertSame(422, $response->getStatusCode());
    }

    public function testRejectsMalformedJsonBodyWith422(): void
    {
        $response = $this->kernel->handle(Request::create(
            '/api/interpretations/does-not-exist',
            'POST',
            content: 'not json',
        ));

        self::assertSame(422, $response->getStatusCode());
    }

    public function testReturns404ForAMissingConsultation(): void
    {
        $response = $this->kernel->handle(Request::create('/api/interpretations/does-not-exist', 'POST'));

        self::assertSame(404, $response->getStatusCode());
        self::assertSame(['error' => 'Not Found'], $this->decode($response));
    }

    public function testFailsCleanlyWhenGeminiIsSelectedWithNoApiKey(): void
    {
        // A misconfigured provider throws at controller-construction time (inside
        // Kernel::invoke()), before this endpoint's own code runs at all - proving the
        // Kernel-level catch-all (not just this controller) is what keeps it clean.
        $config = new Config([
            'app_env' => 'testing',
            'database_path' => $this->databasePath,
            'ai_provider' => 'gemini',
            'ai_api_key' => '',
        ]);
        $apiRoot = dirname(__DIR__, 2);
        $routeDefinitions = require $apiRoot . '/config/routes.php';
        $kernel = new Kernel($config, $routeDefinitions);

        $response = $kernel->handle(Request::create('/api/interpretations/does-not-exist', 'POST'));

        self::assertSame(500, $response->getStatusCode());
        self::assertSame(['error' => 'Internal Server Error'], $this->decode($response));
    }

    public function testReturns429AfterExceedingTheRateLimit(): void
    {
        $config = new Config([
            'app_env' => 'testing',
            'database_path' => $this->databasePath,
            'ai_rate_limit_max' => 1,
            'ai_rate_limit_window_seconds' => 3600,
        ]);
        $apiRoot = dirname(__DIR__, 2);
        $routeDefinitions = require $apiRoot . '/config/routes.php';
        $kernel = new Kernel($config, $routeDefinitions);

        $first = $kernel->handle(Request::create('/api/interpretations/does-not-exist', 'POST'));
        $second = $kernel->handle(Request::create('/api/interpretations/does-not-exist', 'POST'));

        self::assertSame(404, $first->getStatusCode(), 'the first request should reach the normal 404 path');
        self::assertSame(429, $second->getStatusCode(), 'the second request must be rejected before reaching it');
        self::assertSame('3600', $second->headers->get('Retry-After'));
        self::assertSame(
            ['error' => 'Too many interpretation requests. Please try again later.'],
            $this->decode($second),
        );
    }

    public function testFollowUpReturnsAnAnswerForAnExistingConsultation(): void
    {
        $primary = self::hexagramFromPattern('111111', changingPositions: [1]);
        $consultation = Consultation::create(
            'consult-1',
            'Should I take the offer?',
            CastingMethodName::ThreeCoins,
            $primary,
            new \DateTimeImmutable(),
        );
        $this->repository->save($consultation);

        $response = $this->kernel->handle(Request::create(
            '/api/interpretations/consult-1/followup',
            'POST',
            content: json_encode(['question' => 'What should I avoid?'], JSON_THROW_ON_ERROR),
        ));

        self::assertSame(200, $response->getStatusCode());
        $body = $this->decode($response);
        self::assertNotEmpty($body['answer']);
        self::assertNotEmpty($body['sourceReferences']);
    }

    public function testFollowUpAcceptsHistory(): void
    {
        $primary = self::hexagramFromPattern('111111', changingPositions: [1]);
        $consultation = Consultation::create(
            'consult-1',
            'Should I take the offer?',
            CastingMethodName::ThreeCoins,
            $primary,
            new \DateTimeImmutable(),
        );
        $this->repository->save($consultation);

        $response = $this->kernel->handle(Request::create(
            '/api/interpretations/consult-1/followup',
            'POST',
            content: json_encode([
                'question' => 'And the transition?',
                'history' => [['question' => 'What does line 1 mean?', 'answer' => 'Patience.']],
            ], JSON_THROW_ON_ERROR),
        ));

        self::assertSame(200, $response->getStatusCode());
    }

    public function testFollowUpWithAnEmptyQuestionReturns422BeforeTouchingTheRepository(): void
    {
        $response = $this->kernel->handle(Request::create(
            '/api/interpretations/does-not-exist/followup',
            'POST',
            content: json_encode(['question' => ''], JSON_THROW_ON_ERROR),
        ));

        // 422, not 404 - proves question validation happens before the repository lookup.
        self::assertSame(422, $response->getStatusCode());
    }

    public function testFollowUpWithAnOverLengthQuestionReturns422(): void
    {
        $response = $this->kernel->handle(Request::create(
            '/api/interpretations/does-not-exist/followup',
            'POST',
            content: json_encode(['question' => str_repeat('a', 2001)], JSON_THROW_ON_ERROR),
        ));

        self::assertSame(422, $response->getStatusCode());
    }

    public function testFollowUpWithMalformedHistoryReturns422(): void
    {
        $response = $this->kernel->handle(Request::create(
            '/api/interpretations/does-not-exist/followup',
            'POST',
            content: json_encode(['question' => 'Q?', 'history' => [['question' => 'only a question']]], JSON_THROW_ON_ERROR),
        ));

        self::assertSame(422, $response->getStatusCode());
    }

    public function testFollowUpReturns404ForAMissingConsultation(): void
    {
        $response = $this->kernel->handle(Request::create(
            '/api/interpretations/does-not-exist/followup',
            'POST',
            content: json_encode(['question' => 'Q?'], JSON_THROW_ON_ERROR),
        ));

        self::assertSame(404, $response->getStatusCode());
    }

    public function testFollowUpSharesTheRateLimitWithCreate(): void
    {
        $config = new Config([
            'app_env' => 'testing',
            'database_path' => $this->databasePath,
            'ai_rate_limit_max' => 1,
            'ai_rate_limit_window_seconds' => 3600,
        ]);
        $apiRoot = dirname(__DIR__, 2);
        $routeDefinitions = require $apiRoot . '/config/routes.php';
        $kernel = new Kernel($config, $routeDefinitions);

        $first = $kernel->handle(Request::create('/api/interpretations/does-not-exist', 'POST'));
        $second = $kernel->handle(Request::create(
            '/api/interpretations/does-not-exist/followup',
            'POST',
            content: json_encode(['question' => 'Q?'], JSON_THROW_ON_ERROR),
        ));

        self::assertSame(404, $first->getStatusCode());
        self::assertSame(429, $second->getStatusCode(), 'followup must share create()\'s rate limit budget');
    }

    /**
     * @return array<mixed>
     */
    private function decode(\Symfony\Component\HttpFoundation\Response $response): array
    {
        /** @var array<mixed> $decoded */
        $decoded = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        return $decoded;
    }
}
