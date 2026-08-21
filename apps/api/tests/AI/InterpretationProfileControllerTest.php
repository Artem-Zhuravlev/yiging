<?php

declare(strict_types=1);

namespace App\Tests\AI;

use App\Core\Config;
use App\Core\Database;
use App\Core\Kernel;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class InterpretationProfileControllerTest extends TestCase
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

    public function testShowReturnsTheDefaultProfileBeforeAnythingIsEverSaved(): void
    {
        $response = $this->kernel->handle(Request::create('/api/interpretation-profile', 'GET'));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(
            ['tone' => 'neutral', 'length' => 'standard', 'notes' => null],
            $this->decode($response),
        );
    }

    public function testUpdateSetsASubsetOfFieldsAndPersistsThem(): void
    {
        $response = $this->kernel->handle(Request::create(
            '/api/interpretation-profile',
            'PATCH',
            content: json_encode(['tone' => 'poetic', 'notes' => 'Be vivid.'], JSON_THROW_ON_ERROR),
        ));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(
            ['tone' => 'poetic', 'length' => 'standard', 'notes' => 'Be vivid.'],
            $this->decode($response),
        );

        $second = $this->kernel->handle(Request::create('/api/interpretation-profile', 'GET'));
        self::assertSame(
            ['tone' => 'poetic', 'length' => 'standard', 'notes' => 'Be vivid.'],
            $this->decode($second),
        );
    }

    public function testUpdateWithAnInvalidToneReturns422(): void
    {
        $response = $this->kernel->handle(Request::create(
            '/api/interpretation-profile',
            'PATCH',
            content: json_encode(['tone' => 'sarcastic'], JSON_THROW_ON_ERROR),
        ));

        self::assertSame(422, $response->getStatusCode());
    }

    public function testUpdateWithAnInvalidLengthReturns422(): void
    {
        $response = $this->kernel->handle(Request::create(
            '/api/interpretation-profile',
            'PATCH',
            content: json_encode(['length' => 'epic'], JSON_THROW_ON_ERROR),
        ));

        self::assertSame(422, $response->getStatusCode());
    }

    public function testUpdateWithOverLengthNotesReturns422(): void
    {
        $response = $this->kernel->handle(Request::create(
            '/api/interpretation-profile',
            'PATCH',
            content: json_encode(['notes' => str_repeat('a', 1001)], JSON_THROW_ON_ERROR),
        ));

        self::assertSame(422, $response->getStatusCode());
    }

    public function testUpdateWithExplicitNullNotesClearsIt(): void
    {
        $this->kernel->handle(Request::create(
            '/api/interpretation-profile',
            'PATCH',
            content: json_encode(['notes' => 'Something.'], JSON_THROW_ON_ERROR),
        ));

        $response = $this->kernel->handle(Request::create(
            '/api/interpretation-profile',
            'PATCH',
            content: json_encode(['notes' => null], JSON_THROW_ON_ERROR),
        ));

        self::assertSame(200, $response->getStatusCode());
        self::assertNull($this->decode($response)['notes']);
    }

    public function testUpdateWithAnEmptyBodyIsAHarmlessNoOp(): void
    {
        $response = $this->kernel->handle(Request::create('/api/interpretation-profile', 'PATCH', content: '{}'));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(
            ['tone' => 'neutral', 'length' => 'standard', 'notes' => null],
            $this->decode($response),
        );
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
