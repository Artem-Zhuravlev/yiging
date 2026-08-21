<?php

declare(strict_types=1);

namespace App\Tests\Readings;

use App\Core\Config;
use App\Core\Database;
use App\Core\Kernel;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class StatisticsControllerTest extends TestCase
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

    public function testIndexOnAnEmptyHistoryReturnsZeroesAndEmptyLists(): void
    {
        $response = $this->kernel->handle(Request::create('/api/statistics', 'GET'));

        self::assertSame(200, $response->getStatusCode());
        $body = $this->decode($response);

        self::assertSame(0, $body['totalConsultations']);
        self::assertSame([], $body['hexagramFrequency']);
        self::assertSame(['yin' => 0, 'yang' => 0], $body['yinYangRatio']);
        self::assertSame([], $body['tagFrequency']);
    }

    public function testIndexAggregatesRealConsultations(): void
    {
        $allYang = array_fill(0, 6, ['polarity' => 'yang', 'changing' => false]);
        $allYin = array_fill(0, 6, ['polarity' => 'yin', 'changing' => false]);

        $this->postJson('/api/consultations', ['question' => 'First?', 'method' => 'manual', 'lines' => $allYang]);
        $second = $this->decode($this->postJson('/api/consultations', [
            'question' => 'Second?',
            'method' => 'manual',
            'lines' => $allYang,
        ]));
        $this->postJson('/api/consultations', ['question' => 'Third?', 'method' => 'manual', 'lines' => $allYin]);

        $this->patchJson('/api/consultations/' . $second['id'], ['tag' => 'career']);

        $response = $this->kernel->handle(Request::create('/api/statistics', 'GET'));
        $body = $this->decode($response);

        self::assertSame(3, $body['totalConsultations']);
        self::assertSame(
            [1, 2],
            array_column($body['hexagramFrequency'], 'kingWenNumber'),
        );
        self::assertSame(2, $body['hexagramFrequency'][0]['count']);
        self::assertSame(1, $body['hexagramFrequency'][1]['count']);
        self::assertSame(6, $body['yinYangRatio']['yin']);
        self::assertSame(12, $body['yinYangRatio']['yang']);
        self::assertSame([['name' => 'career', 'count' => 1]], $body['tagFrequency']);
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
     * @param array<string, mixed> $body
     */
    private function patchJson(string $uri, array $body): Response
    {
        $request = Request::create($uri, 'PATCH', content: json_encode($body, JSON_THROW_ON_ERROR));

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
