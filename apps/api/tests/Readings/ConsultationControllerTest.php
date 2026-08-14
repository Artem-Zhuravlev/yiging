<?php

declare(strict_types=1);

namespace App\Tests\Readings;

use App\Core\Config;
use App\Core\Database;
use App\Core\Kernel;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class ConsultationControllerTest extends TestCase
{
    private string $databasePath;
    private Kernel $kernel;

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

        $routeDefinitions = require $apiRoot . '/config/routes.php';
        $this->kernel = new Kernel($config, $routeDefinitions);
    }

    protected function tearDown(): void
    {
        if (is_file($this->databasePath)) {
            unlink($this->databasePath);
        }
    }

    public function testCreateWithThreeCoinsMethodPersistsAndReturns201(): void
    {
        $response = $this->postJson('/api/consultations', [
            'question' => 'Should I take the offer?',
            'method' => 'three_coins',
        ]);

        self::assertSame(201, $response->getStatusCode());
        $body = $this->decode($response);

        self::assertNotEmpty($body['id']);
        self::assertSame('Should I take the offer?', $body['question']);
        self::assertSame('three_coins', $body['method']);
        self::assertArrayHasKey('kingWenNumber', $body['primaryHexagram']);
        self::assertArrayHasKey('kingWenNumber', $body['resultingHexagram']);
        self::assertSame([], $body['notes']);
        self::assertSame([], $body['tags']);
    }

    public function testCreateWithRandomMethodReturns201(): void
    {
        $response = $this->postJson('/api/consultations', [
            'question' => 'Quick dev check?',
            'method' => 'random',
        ]);

        self::assertSame(201, $response->getStatusCode());
    }

    public function testCreateWithManualMethodBuildsTheExactHexagram(): void
    {
        $allYang = array_fill(0, 6, ['polarity' => 'yang', 'changing' => false]);

        $response = $this->postJson('/api/consultations', [
            'question' => 'Manual cast test',
            'method' => 'manual',
            'lines' => $allYang,
        ]);

        self::assertSame(201, $response->getStatusCode());
        $body = $this->decode($response);

        self::assertSame(1, $body['primaryHexagram']['kingWenNumber']);
        self::assertSame(1, $body['resultingHexagram']['kingWenNumber']);
        self::assertSame([], $body['changingLinePositions']);
    }

    public function testCreateWithEmptyQuestionReturns422(): void
    {
        $response = $this->postJson('/api/consultations', [
            'question' => '   ',
            'method' => 'three_coins',
        ]);

        self::assertSame(422, $response->getStatusCode());
        self::assertArrayHasKey('error', $this->decode($response));
    }

    public function testCreateWithInvalidMethodReturns422(): void
    {
        $response = $this->postJson('/api/consultations', [
            'question' => 'Valid question',
            'method' => 'tarot',
        ]);

        self::assertSame(422, $response->getStatusCode());
    }

    public function testCreateWithManualMethodMissingLinesReturns422(): void
    {
        $response = $this->postJson('/api/consultations', [
            'question' => 'Valid question',
            'method' => 'manual',
        ]);

        self::assertSame(422, $response->getStatusCode());
    }

    public function testCreateWithManualMethodWrongLineCountReturns422(): void
    {
        $response = $this->postJson('/api/consultations', [
            'question' => 'Valid question',
            'method' => 'manual',
            'lines' => array_fill(0, 5, ['polarity' => 'yang', 'changing' => false]),
        ]);

        self::assertSame(422, $response->getStatusCode());
    }

    public function testCreateWithManualMethodInvalidPolarityReturns422(): void
    {
        $lines = array_fill(0, 5, ['polarity' => 'yang', 'changing' => false]);
        $lines[] = ['polarity' => 'sideways', 'changing' => false];

        $response = $this->postJson('/api/consultations', [
            'question' => 'Valid question',
            'method' => 'manual',
            'lines' => $lines,
        ]);

        self::assertSame(422, $response->getStatusCode());
    }

    public function testIndexReturnsAllConsultationsNewestFirst(): void
    {
        $this->postJson('/api/consultations', ['question' => 'First?', 'method' => 'random']);
        $this->postJson('/api/consultations', ['question' => 'Second?', 'method' => 'random']);

        $response = $this->kernel->handle(Request::create('/api/consultations', 'GET'));

        self::assertSame(200, $response->getStatusCode());
        $body = $this->decode($response);

        self::assertCount(2, $body);
        self::assertSame('Second?', $body[0]['question']);
        self::assertSame('First?', $body[1]['question']);
    }

    public function testShowReturnsTheCreatedConsultation(): void
    {
        $created = $this->decode($this->postJson('/api/consultations', [
            'question' => 'Round trip?',
            'method' => 'three_coins',
        ]));

        $response = $this->kernel->handle(Request::create('/api/consultations/' . $created['id'], 'GET'));

        self::assertSame(200, $response->getStatusCode());
        $body = $this->decode($response);
        self::assertSame($created['id'], $body['id']);
        self::assertSame('Round trip?', $body['question']);
    }

    public function testShowReturns404ForAMissingConsultation(): void
    {
        $response = $this->kernel->handle(Request::create('/api/consultations/does-not-exist', 'GET'));

        self::assertSame(404, $response->getStatusCode());
        self::assertSame(['error' => 'Not Found'], $this->decode($response));
    }

    /**
     * @param array<string, mixed> $body
     */
    private function postJson(string $uri, array $body): Response
    {
        $request = Request::create($uri, 'POST', content: json_encode($body, JSON_THROW_ON_ERROR));

        return $this->kernel->handle($request);
    }

    /**
     * @return array<mixed>
     */
    private function decode(Response $response): array
    {
        /** @var array<mixed> $decoded */
        $decoded = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        return $decoded;
    }
}
