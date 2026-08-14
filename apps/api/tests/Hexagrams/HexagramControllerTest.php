<?php

declare(strict_types=1);

namespace App\Tests\Hexagrams;

use App\Core\Config;
use App\Core\Kernel;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class HexagramControllerTest extends TestCase
{
    private Kernel $kernel;

    protected function setUp(): void
    {
        $apiRoot = dirname(__DIR__, 2);
        $config = Config::fromEnv($apiRoot);
        $routeDefinitions = require $apiRoot . '/config/routes.php';

        $this->kernel = new Kernel($config, $routeDefinitions);
    }

    public function testIndexReturnsAllSixtyFourHexagramsInKingWenOrder(): void
    {
        $response = $this->kernel->handle(Request::create('/api/hexagrams', 'GET'));

        self::assertSame(200, $response->getStatusCode());
        $body = $this->decode($response);

        self::assertCount(64, $body);
        self::assertSame(range(1, 64), array_column($body, 'kingWenNumber'));
    }

    public function testIndexIncludesFullStructuralDetail(): void
    {
        $response = $this->kernel->handle(Request::create('/api/hexagrams', 'GET'));
        $body = $this->decode($response);

        $first = $body[0];
        self::assertSame(1, $first['kingWenNumber']);
        self::assertCount(6, $first['lines']);
        self::assertArrayHasKey('id', $first['upperTrigram']);
        self::assertArrayHasKey('id', $first['lowerTrigram']);
        self::assertNull($first['judgment']);
        self::assertNull($first['image']);
        self::assertNull($first['lineStatements']);
    }

    public function testShowReturnsTheFirstHexagram(): void
    {
        $response = $this->kernel->handle(Request::create('/api/hexagrams/1', 'GET'));

        self::assertSame(200, $response->getStatusCode());
        $body = $this->decode($response);
        self::assertSame(1, $body['kingWenNumber']);
        self::assertSame('乾', $body['chineseName']);
    }

    public function testShowReturnsAMiddleHexagram(): void
    {
        $response = $this->kernel->handle(Request::create('/api/hexagrams/32', 'GET'));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(32, $this->decode($response)['kingWenNumber']);
    }

    public function testShowReturnsTheLastHexagram(): void
    {
        $response = $this->kernel->handle(Request::create('/api/hexagrams/64', 'GET'));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(64, $this->decode($response)['kingWenNumber']);
    }

    public function testShowReturns404ForZero(): void
    {
        $response = $this->kernel->handle(Request::create('/api/hexagrams/0', 'GET'));

        self::assertSame(404, $response->getStatusCode());
        self::assertSame(['error' => 'Not Found'], $this->decode($response));
    }

    public function testShowReturns404ForOutOfRange(): void
    {
        $response = $this->kernel->handle(Request::create('/api/hexagrams/65', 'GET'));

        self::assertSame(404, $response->getStatusCode());
    }

    public function testShowReturns404ForANonNumericSegment(): void
    {
        $response = $this->kernel->handle(Request::create('/api/hexagrams/not-a-number', 'GET'));

        self::assertSame(404, $response->getStatusCode());
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
