<?php

declare(strict_types=1);

namespace Yijing\Core\Tests;

use PHPUnit\Framework\TestCase;
use Yijing\Core\Data\HexagramTextCatalog;

final class HexagramTextCatalogTest extends TestCase
{
    public function testAllSixtyFourHexagramsHaveCompleteClassicalText(): void
    {
        for ($number = 1; $number <= 64; $number++) {
            $text = HexagramTextCatalog::textFor($number);

            self::assertNotSame('', trim($text['judgment']), "Hexagram {$number} judgment");
            self::assertNotSame('', trim($text['image']), "Hexagram {$number} image");
            self::assertCount(6, $text['lineStatements'], "Hexagram {$number} line count");

            foreach ($text['lineStatements'] as $position => $statement) {
                self::assertNotSame('', trim($statement), "Hexagram {$number} line " . ($position + 1));
            }
        }
    }

    public function testThrowsForAnOutOfRangeNumber(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        HexagramTextCatalog::textFor(65);
    }

    /**
     * Regression guard against accidental corruption of the transcribed text: pins the
     * exact wording of a well-known hexagram (1, Qian/Khien), cross-checked against an
     * independent public-domain digitization (ctext.org) during transcription.
     */
    public function testHexagramOneMatchesTheKnownLeggeText(): void
    {
        $text = HexagramTextCatalog::textFor(1);

        self::assertSame(
            'Khien (represents) what is great and originating, penetrating, advantageous, correct and firm.',
            $text['judgment'],
        );
        self::assertSame(
            'Heaven, in its motion, (gives the idea of) strength. The superior man, in accordance with this, '
                . 'nerves himself to ceaseless activity.',
            $text['image'],
        );
        self::assertStringContainsString('dragon lying hid', $text['lineStatements'][0]);
        self::assertStringContainsString('dragon exceeding the proper limits', $text['lineStatements'][5]);
    }
}
