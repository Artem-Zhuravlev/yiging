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
        self::assertNotEmpty($first['judgment']);
        self::assertNotEmpty($first['image']);
        self::assertCount(6, $first['lineStatements']);
    }

    public function testShowReturnsTheFirstHexagram(): void
    {
        $response = $this->kernel->handle(Request::create('/api/hexagrams/1', 'GET'));

        self::assertSame(200, $response->getStatusCode());
        $body = $this->decode($response);
        self::assertSame(1, $body['kingWenNumber']);
        self::assertSame('乾', $body['chineseName']);
    }

    public function testShowIncludesRelationships(): void
    {
        // Hexagram 11 (Tai): nuclear is 54 (Gui Mei), reversed and complement both land on 12
        // (Pi) — a real coincidence for this specific pattern, independently verified in
        // YijingRelationsTest.
        $response = $this->kernel->handle(Request::create('/api/hexagrams/11', 'GET'));
        $body = $this->decode($response);

        self::assertSame(54, $body['relationships']['nuclear']['kingWenNumber']);
        self::assertSame(12, $body['relationships']['reversed']['kingWenNumber']);
        self::assertSame(12, $body['relationships']['complement']['kingWenNumber']);
        self::assertArrayHasKey('chineseName', $body['relationships']['nuclear']);
        self::assertArrayHasKey('pinyin', $body['relationships']['nuclear']);
    }

    public function testShowReportsSelfReferentialRelationshipsRatherThanOmittingThem(): void
    {
        // Hexagram 1 (Qian, all yang): its own nuclear hexagram is itself.
        $response = $this->kernel->handle(Request::create('/api/hexagrams/1', 'GET'));
        $body = $this->decode($response);

        self::assertSame(1, $body['relationships']['nuclear']['kingWenNumber']);
    }

    public function testIndexIncludesRelationshipsPerEntry(): void
    {
        $response = $this->kernel->handle(Request::create('/api/hexagrams', 'GET'));
        $body = $this->decode($response);

        $tai = $body[10]; // king wen number 11, 0-indexed
        self::assertSame(11, $tai['kingWenNumber']);
        self::assertSame(54, $tai['relationships']['nuclear']['kingWenNumber']);
    }

    public function testFromLinesComputesTaiFromItsPattern(): void
    {
        $response = $this->kernel->handle(
            Request::create('/api/hexagrams/from-lines?lines=yang,yang,yang,yin,yin,yin', 'GET'),
        );

        self::assertSame(200, $response->getStatusCode());
        $body = $this->decode($response);
        self::assertSame(11, $body['kingWenNumber']);
        self::assertSame('泰', $body['chineseName']);
        self::assertSame(54, $body['relationships']['nuclear']['kingWenNumber']);
    }

    public function testFromLinesMatchesShowForTheSamePattern(): void
    {
        $fromLines = $this->decode($this->kernel->handle(
            Request::create('/api/hexagrams/from-lines?lines=yang,yang,yang,yin,yin,yin', 'GET'),
        ));
        $show = $this->decode($this->kernel->handle(Request::create('/api/hexagrams/11', 'GET')));

        self::assertSame($show, $fromLines);
    }

    public function testFromLinesReturns422ForWrongCount(): void
    {
        $response = $this->kernel->handle(
            Request::create('/api/hexagrams/from-lines?lines=yang,yang,yang', 'GET'),
        );

        self::assertSame(422, $response->getStatusCode());
    }

    public function testFromLinesReturns422ForAnInvalidPolarity(): void
    {
        $response = $this->kernel->handle(
            Request::create('/api/hexagrams/from-lines?lines=yang,yang,yang,yin,yin,maybe', 'GET'),
        );

        self::assertSame(422, $response->getStatusCode());
    }

    public function testFromLinesReturns422WhenLinesIsMissing(): void
    {
        $response = $this->kernel->handle(Request::create('/api/hexagrams/from-lines', 'GET'));

        self::assertSame(422, $response->getStatusCode());
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
