<?php

declare(strict_types=1);

namespace Yijing\Core\Tests;

use PHPUnit\Framework\TestCase;
use Yijing\Core\Data\HexagramSequenceCatalog;

final class HexagramSequenceCatalogTest extends TestCase
{
    public function testHexagramsOneAndTwoHaveNoPrecedent(): void
    {
        self::assertNull(HexagramSequenceCatalog::precedentFor(1));
        self::assertNull(HexagramSequenceCatalog::precedentFor(2));
    }

    public function testEveryHexagramFromThreeToSixtyFourHasANonEmptyPrecedent(): void
    {
        for ($number = 3; $number <= 64; $number++) {
            $precedent = HexagramSequenceCatalog::precedentFor($number);

            self::assertIsString($precedent, "Hexagram {$number}");
            self::assertNotSame('', trim((string) $precedent), "Hexagram {$number}");
        }
    }

    public function testOutOfRangeNumberReturnsNull(): void
    {
        self::assertNull(HexagramSequenceCatalog::precedentFor(65));
        self::assertNull(HexagramSequenceCatalog::precedentFor(0));
    }

    public function testSectionOneEntriesNameTheHexagramAndItsPredecessor(): void
    {
        self::assertStringContainsString('Zhun', (string) HexagramSequenceCatalog::precedentFor(3));

        $four = (string) HexagramSequenceCatalog::precedentFor(4);
        self::assertStringContainsString('Zhun', $four);
        self::assertStringContainsString('Meng', $four);
        self::assertStringContainsString('followed by Meng', $four);
    }

    public function testHexagramThirtyCarriesTheSectionOneClosingClause(): void
    {
        self::assertStringEndsWith(
            'Li denotes being attached, or adhering, to.',
            (string) HexagramSequenceCatalog::precedentFor(30),
        );
    }

    public function testHexagramThirtyOneCarriesTheSectionTwoPreamble(): void
    {
        $preamble = (string) HexagramSequenceCatalog::precedentFor(31);

        self::assertStringContainsString('husband and wife', $preamble);
        self::assertStringContainsString('propriety and righteousness', $preamble);
        self::assertStringNotContainsString('is followed by', $preamble);
    }

    public function testTheClosingEntryPairsJiJiWithWeiJi(): void
    {
        $last = (string) HexagramSequenceCatalog::precedentFor(64);

        self::assertStringContainsString('Ji Ji', $last);
        self::assertStringContainsString('Wei Ji', $last);
        self::assertStringContainsString('come to a close', $last);
    }
}
