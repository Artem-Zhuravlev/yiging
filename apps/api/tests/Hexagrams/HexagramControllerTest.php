<?php

declare(strict_types=1);

namespace App\Tests\Hexagrams;

use App\Core\Config;
use App\Core\Database;
use App\Core\Kernel;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class HexagramControllerTest extends TestCase
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

    public function testShowIncludesTheUnicodeSymbol(): void
    {
        $response = $this->kernel->handle(Request::create('/api/hexagrams/11', 'GET'));

        self::assertSame("\u{4DCA}", $this->decode($response)['symbol']);
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

    public function testShowIncludesLineDynamics(): void
    {
        // Hexagram 63 (Ji Ji): every line is correctly placed and every pair corresponds.
        $body = $this->decode($this->kernel->handle(Request::create('/api/hexagrams/63', 'GET')));

        self::assertCount(6, $body['lineDynamics']);
        foreach ($body['lineDynamics'] as $line) {
            self::assertTrue($line['correctPosition']);
            self::assertTrue($line['corresponds']);
        }
        self::assertSame(1, $body['lineDynamics'][0]['position']);
        self::assertSame(6, $body['lineDynamics'][5]['position']);
    }

    public function testShowLineDynamicsMarkCentralityCorrectly(): void
    {
        // Hexagram 1 (Qian): line 5 is central and correct; line 2 is central but not correct.
        $body = $this->decode($this->kernel->handle(Request::create('/api/hexagrams/1', 'GET')));

        self::assertTrue($body['lineDynamics'][4]['centralAndCorrect']);  // position 5
        self::assertTrue($body['lineDynamics'][1]['central']);            // position 2
        self::assertFalse($body['lineDynamics'][1]['centralAndCorrect']);
    }

    public function testIndexDoesNotIncludeLineDynamics(): void
    {
        $body = $this->decode($this->kernel->handle(Request::create('/api/hexagrams', 'GET')));

        self::assertArrayNotHasKey('lineDynamics', $body[0]);
    }

    public function testFromLinesIncludesLineDynamics(): void
    {
        $body = $this->decode($this->kernel->handle(
            Request::create('/api/hexagrams/from-lines?lines=yang,yin,yang,yin,yang,yin', 'GET'),
        ));

        self::assertCount(6, $body['lineDynamics']);
        self::assertTrue($body['lineDynamics'][0]['correctPosition']); // yang at position 1
    }

    public function testShowIncludesTheSequencePrecedent(): void
    {
        $body = $this->decode($this->kernel->handle(Request::create('/api/hexagrams/3', 'GET')));

        self::assertIsString($body['sequencePrecedent']);
        self::assertStringContainsString('Zhun', $body['sequencePrecedent']);
    }

    public function testSequencePrecedentIsNullForHexagramOne(): void
    {
        $body = $this->decode($this->kernel->handle(Request::create('/api/hexagrams/1', 'GET')));

        self::assertArrayHasKey('sequencePrecedent', $body);
        self::assertNull($body['sequencePrecedent']);
    }

    public function testIndexDoesNotIncludeTheSequencePrecedent(): void
    {
        $body = $this->decode($this->kernel->handle(Request::create('/api/hexagrams', 'GET')));

        self::assertArrayNotHasKey('sequencePrecedent', $body[0]);
    }

    public function testFromLinesIncludesTheSequencePrecedent(): void
    {
        // yin,yang,yin,yin,yin,yang bottom-to-top (Kan below, Gen above) is hexagram 4 (Meng).
        $body = $this->decode($this->kernel->handle(
            Request::create('/api/hexagrams/from-lines?lines=yin,yang,yin,yin,yin,yang', 'GET'),
        ));

        self::assertSame(4, $body['kingWenNumber']);
        self::assertIsString($body['sequencePrecedent']);
        self::assertStringContainsString('Meng', $body['sequencePrecedent']);
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

    public function testCompareReturnsBothHexagramsWithLineComparisonsAndTrigramFlags(): void
    {
        $response = $this->kernel->handle(Request::create('/api/hexagrams/compare?a=11&b=44', 'GET'));

        self::assertSame(200, $response->getStatusCode());
        $body = $this->decode($response);

        self::assertSame(11, $body['a']['kingWenNumber']);
        self::assertSame(44, $body['b']['kingWenNumber']);
        self::assertArrayHasKey('relationships', $body['a']);
        self::assertArrayHasKey('judgment', $body['b']);

        self::assertCount(6, $body['lineComparisons']);
        self::assertSame(
            [1, 2, 3, 4, 5, 6],
            array_column($body['lineComparisons'], 'position'),
        );
        self::assertIsBool($body['upperTrigramDiffers']);
        self::assertIsBool($body['lowerTrigramDiffers']);
    }

    public function testCompareOfAHexagramWithItselfReportsNoChangesAndNoTrigramDifferences(): void
    {
        $response = $this->kernel->handle(Request::create('/api/hexagrams/compare?a=11&b=11', 'GET'));
        $body = $this->decode($response);

        self::assertSame(200, $response->getStatusCode());
        foreach ($body['lineComparisons'] as $lineComparison) {
            self::assertFalse($lineComparison['changed']);
        }
        self::assertFalse($body['upperTrigramDiffers']);
        self::assertFalse($body['lowerTrigramDiffers']);
    }

    public function testCompareReturns422ForNonNumericA(): void
    {
        $response = $this->kernel->handle(Request::create('/api/hexagrams/compare?a=abc&b=11', 'GET'));

        self::assertSame(422, $response->getStatusCode());
    }

    public function testCompareReturns422WhenBIsMissing(): void
    {
        $response = $this->kernel->handle(Request::create('/api/hexagrams/compare?a=11', 'GET'));

        self::assertSame(422, $response->getStatusCode());
    }

    public function testCompareReturns404ForAnOutOfRangeA(): void
    {
        $response = $this->kernel->handle(Request::create('/api/hexagrams/compare?a=0&b=11', 'GET'));

        self::assertSame(404, $response->getStatusCode());
    }

    public function testCompareReturns404ForAnOutOfRangeB(): void
    {
        $response = $this->kernel->handle(Request::create('/api/hexagrams/compare?a=11&b=65', 'GET'));

        self::assertSame(404, $response->getStatusCode());
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

    public function testHexagramsAreNotFavoriteByDefault(): void
    {
        $body = $this->decode($this->kernel->handle(Request::create('/api/hexagrams/1', 'GET')));

        self::assertFalse($body['favorite']);
    }

    public function testMarkAndUnmarkFavoriteToggleTheFlag(): void
    {
        $marked = $this->kernel->handle(Request::create('/api/hexagrams/1/favorite', 'PUT'));
        self::assertSame(204, $marked->getStatusCode());

        $afterMark = $this->decode($this->kernel->handle(Request::create('/api/hexagrams/1', 'GET')));
        self::assertTrue($afterMark['favorite']);

        $unmarked = $this->kernel->handle(Request::create('/api/hexagrams/1/favorite', 'DELETE'));
        self::assertSame(204, $unmarked->getStatusCode());

        $afterUnmark = $this->decode($this->kernel->handle(Request::create('/api/hexagrams/1', 'GET')));
        self::assertFalse($afterUnmark['favorite']);
    }

    public function testMarkingAnAlreadyFavoriteHexagramIsIdempotent(): void
    {
        $this->kernel->handle(Request::create('/api/hexagrams/1/favorite', 'PUT'));
        $response = $this->kernel->handle(Request::create('/api/hexagrams/1/favorite', 'PUT'));

        self::assertSame(204, $response->getStatusCode());
    }

    public function testUnmarkingAHexagramThatWasNeverFavoriteIsIdempotent(): void
    {
        $response = $this->kernel->handle(Request::create('/api/hexagrams/1/favorite', 'DELETE'));

        self::assertSame(204, $response->getStatusCode());
    }

    public function testMarkFavoriteReturns404ForAnOutOfRangeNumber(): void
    {
        $response = $this->kernel->handle(Request::create('/api/hexagrams/999/favorite', 'PUT'));

        self::assertSame(404, $response->getStatusCode());
    }

    public function testUnmarkFavoriteReturns404ForAnOutOfRangeNumber(): void
    {
        $response = $this->kernel->handle(Request::create('/api/hexagrams/999/favorite', 'DELETE'));

        self::assertSame(404, $response->getStatusCode());
    }

    public function testIndexReflectsFavoriteStatusPerHexagram(): void
    {
        $this->kernel->handle(Request::create('/api/hexagrams/1/favorite', 'PUT'));

        $body = $this->decode($this->kernel->handle(Request::create('/api/hexagrams', 'GET')));

        self::assertTrue($body[0]['favorite']);
        self::assertFalse($body[1]['favorite']);
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
