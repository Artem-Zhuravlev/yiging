<?php

declare(strict_types=1);

namespace Yijing\Core\Tests;

use PHPUnit\Framework\TestCase;
use Yijing\Core\Data\HexagramTextCatalog;

final class HexagramTextCatalogUkTest extends TestCase
{
    private static function hasCyrillic(string $value): bool
    {
        return preg_match('/\p{Cyrillic}/u', $value) === 1;
    }

    public function testEveryHexagramHasCompleteUkrainianText(): void
    {
        for ($number = 1; $number <= 64; $number++) {
            $text = HexagramTextCatalog::textFor($number, 'uk');

            self::assertArrayHasKey('judgment', $text, "Hexagram {$number}");
            self::assertArrayHasKey('image', $text, "Hexagram {$number}");
            self::assertArrayHasKey('lineStatements', $text, "Hexagram {$number}");
            self::assertCount(6, $text['lineStatements'], "Hexagram {$number} line count");

            self::assertTrue(self::hasCyrillic($text['judgment']), "Hexagram {$number} judgment is Ukrainian");
            self::assertTrue(self::hasCyrillic($text['image']), "Hexagram {$number} image is Ukrainian");
            foreach ($text['lineStatements'] as $position => $statement) {
                self::assertNotSame('', trim($statement), "Hexagram {$number} line " . ($position + 1));
                self::assertTrue(
                    self::hasCyrillic($statement),
                    "Hexagram {$number} line " . ($position + 1) . ' is Ukrainian',
                );
            }
        }
    }

    public function testUkrainianTextIsStructurallyParallelToEnglish(): void
    {
        for ($number = 1; $number <= 64; $number++) {
            $en = HexagramTextCatalog::textFor($number, 'en');
            $uk = HexagramTextCatalog::textFor($number, 'uk');

            self::assertSame(array_keys($en), array_keys($uk), "Hexagram {$number} keys");
            self::assertCount(count($en['lineStatements']), $uk['lineStatements'], "Hexagram {$number}");
            self::assertNotSame($en['judgment'], $uk['judgment'], "Hexagram {$number} judgment differs");
        }
    }

    public function testDefaultLocaleAndEnglishAreUnchanged(): void
    {
        $default = HexagramTextCatalog::textFor(1);
        $english = HexagramTextCatalog::textFor(1, 'en');

        self::assertSame($default, $english);
        self::assertStringContainsString('Khien', $default['judgment']);
        self::assertSame($default, HexagramTextCatalog::textFor(1, 'fr'));
    }

    public function testSpecialTextIsUkrainianForHexagramsOneAndTwoOnly(): void
    {
        self::assertTrue(self::hasCyrillic((string) HexagramTextCatalog::specialTextFor(1, 'uk')));
        self::assertTrue(self::hasCyrillic((string) HexagramTextCatalog::specialTextFor(2, 'uk')));
        self::assertNull(HexagramTextCatalog::specialTextFor(3, 'uk'));
        self::assertStringContainsString('NINE', (string) HexagramTextCatalog::specialTextFor(1));
    }

    public function testUnknownHexagramStillThrowsForUk(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        HexagramTextCatalog::textFor(65, 'uk');
    }
}
