<?php

declare(strict_types=1);

namespace App\Tests\Trigrams;

use App\Core\Config;
use App\Core\Kernel;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class TrigramControllerTest extends TestCase
{
    private Kernel $kernel;

    protected function setUp(): void
    {
        $apiRoot = dirname(__DIR__, 2);
        $config = Config::fromEnv($apiRoot);
        $routeDefinitions = require $apiRoot . '/config/routes.php';

        $this->kernel = new Kernel($config, $routeDefinitions);
    }

    public function testIndexReturnsAllEightTrigrams(): void
    {
        $response = $this->kernel->handle(Request::create('/api/trigrams', 'GET'));

        self::assertSame(200, $response->getStatusCode());
        $body = $this->decode($response);

        self::assertCount(8, $body);
        self::assertSame(
            ['Qian', 'Kun', 'Zhen', 'Kan', 'Gen', 'Xun', 'Li', 'Dui'],
            array_column($body, 'id'),
        );
    }

    public function testEveryTrigramHasAllDocumentedFieldsPopulated(): void
    {
        $response = $this->kernel->handle(Request::create('/api/trigrams', 'GET'));
        $body = $this->decode($response);

        foreach ($body as $trigram) {
            foreach (['id', 'name', 'chineseName', 'pinyin', 'symbol', 'element', 'familyMember', 'direction', 'image'] as $field) {
                self::assertArrayHasKey($field, $trigram);
                self::assertNotSame('', $trigram[$field], "{$field} on {$trigram['id']}");
            }
        }
    }

    public function testQianIsAllYangAttributes(): void
    {
        $response = $this->kernel->handle(Request::create('/api/trigrams', 'GET'));
        $body = $this->decode($response);

        $qian = $body[0];
        self::assertSame('Qian', $qian['id']);
        self::assertSame('乾', $qian['chineseName']);
        self::assertSame('☰', $qian['symbol']);
        self::assertSame('Heaven', $qian['image']);
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
