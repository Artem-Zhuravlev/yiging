<?php

declare(strict_types=1);

namespace Yijing\Core\Tests;

use PHPUnit\Framework\TestCase;
use Yijing\Core\Data\HexagramSequenceCatalog;

final class HexagramSequenceCatalogUkTest extends TestCase
{
    private static function hasCyrillic(string $value): bool
    {
        return preg_match('/\p{Cyrillic}/u', $value) === 1;
    }

    public function testEveryPrecedentFromThreeToSixtyFourIsUkrainian(): void
    {
        for ($number = 3; $number <= 64; $number++) {
            $uk = HexagramSequenceCatalog::precedentFor($number, 'uk');

            self::assertIsString($uk, "Hexagram {$number}");
            self::assertNotSame('', trim((string) $uk), "Hexagram {$number}");
            self::assertTrue(self::hasCyrillic((string) $uk), "Hexagram {$number} is Ukrainian");
            self::assertNotSame(
                HexagramSequenceCatalog::precedentFor($number, 'en'),
                $uk,
                "Hexagram {$number} differs from English",
            );
        }
    }

    public function testHexagramsOneAndTwoHaveNoUkrainianPrecedent(): void
    {
        self::assertNull(HexagramSequenceCatalog::precedentFor(1, 'uk'));
        self::assertNull(HexagramSequenceCatalog::precedentFor(2, 'uk'));
    }

    public function testDefaultAndEnglishAreUnchanged(): void
    {
        self::assertSame(
            HexagramSequenceCatalog::precedentFor(3),
            HexagramSequenceCatalog::precedentFor(3, 'en'),
        );
        self::assertStringContainsString('Zhun', (string) HexagramSequenceCatalog::precedentFor(3));
        self::assertSame(
            HexagramSequenceCatalog::precedentFor(3),
            HexagramSequenceCatalog::precedentFor(3, 'fr'),
        );
    }
}
