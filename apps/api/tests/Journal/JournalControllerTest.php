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

    public function testIndexReturnsAPageOfEntriesNewestFirst(): void
    {
        $this->postJson('/api/journal', ['text' => 'First.']);
        $this->postJson('/api/journal', ['text' => 'Second.']);

        $response = $this->kernel->handle(Request::create('/api/journal', 'GET'));

        self::assertSame(200, $response->getStatusCode());
        $body = $this->decode($response);

        self::assertArrayHasKey('items', $body);
        self::assertNull($body['nextCursor']);
        self::assertCount(2, $body['items']);
        self::assertSame('Second.', $body['items'][0]['text']);
        self::assertSame('First.', $body['items'][1]['text']);
    }

    public function testIndexOnAnEmptyJournalReturnsAnEmptyPage(): void
    {
        $response = $this->kernel->handle(Request::create('/api/journal', 'GET'));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(['items' => [], 'nextCursor' => null], $this->decode($response));
    }

    public function testIndexPaginatesWithTheCursorAcrossPagesWithNoGapsOrDuplicates(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->postJson('/api/journal', ['text' => "Entry {$i}."]);
        }

        $seen = [];
        $cursor = null;
        $pages = 0;

        do {
            $url = '/api/journal?limit=2' . ($cursor !== null ? '&cursor=' . urlencode($cursor) : '');
            $body = $this->decode($this->kernel->handle(Request::create($url, 'GET')));
            self::assertLessThanOrEqual(2, count($body['items']));
            foreach ($body['items'] as $item) {
                $seen[] = $item['text'];
            }
            $cursor = $body['nextCursor'];
            $pages++;
        } while ($cursor !== null && $pages < 10);

        self::assertSame(['Entry 5.', 'Entry 4.', 'Entry 3.', 'Entry 2.', 'Entry 1.'], $seen);
        self::assertSame(count($seen), count(array_unique($seen)));
    }

    public function testIndexRejectsAMalformedCursorWith422(): void
    {
        $response = $this->kernel->handle(Request::create('/api/journal?cursor=%%%not-valid%%%', 'GET'));

        self::assertSame(422, $response->getStatusCode());
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
