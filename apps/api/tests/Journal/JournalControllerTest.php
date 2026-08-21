<?php

declare(strict_types=1);

namespace App\Tests\Journal;

use App\Core\Config;
use App\Core\Database;
use App\Core\Kernel;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class JournalControllerTest extends TestCase
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

    public function testCreatePersistsAndReturns201(): void
    {
        $response = $this->postJson('/api/journal', ['text' => 'Feeling reflective today.']);

        self::assertSame(201, $response->getStatusCode());
        $body = $this->decode($response);

        self::assertNotEmpty($body['id']);
        self::assertSame('Feeling reflective today.', $body['text']);
        self::assertNotEmpty($body['createdAt']);
    }

    public function testCreateWithEmptyTextReturns422(): void
    {
        $response = $this->postJson('/api/journal', ['text' => '']);

        self::assertSame(422, $response->getStatusCode());
    }

    public function testCreateWithMissingTextReturns422(): void
    {
        $response = $this->postJson('/api/journal', []);

        self::assertSame(422, $response->getStatusCode());
    }

    public function testIndexReturnsAllEntriesNewestFirst(): void
    {
        $this->postJson('/api/journal', ['text' => 'First.']);
        $this->postJson('/api/journal', ['text' => 'Second.']);

        $response = $this->kernel->handle(Request::create('/api/journal', 'GET'));

        self::assertSame(200, $response->getStatusCode());
        $body = $this->decode($response);

        self::assertCount(2, $body);
        self::assertSame('Second.', $body[0]['text']);
        self::assertSame('First.', $body[1]['text']);
    }

    public function testIndexOnAnEmptyJournalReturnsEmptyArray(): void
    {
        $response = $this->kernel->handle(Request::create('/api/journal', 'GET'));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame([], $this->decode($response));
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
