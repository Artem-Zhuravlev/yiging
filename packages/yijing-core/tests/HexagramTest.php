<?php

declare(strict_types=1);

namespace Yijing\Core\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Yijing\Core\Data\HexagramCatalog;
use Yijing\Core\Data\TrigramCatalog;
use Yijing\Core\Hexagram;
use Yijing\Core\Tests\Support\LinePatternTrait;

final class HexagramTest extends TestCase
{
    use LinePatternTrait;

    public function testAllSixtyFourKingWenNumbersArePresentExactlyOnce(): void
    {
        $numbers = array_keys(HexagramCatalog::all());
        sort($numbers);

        self::assertSame(range(1, 64), $numbers);
    }

    public function testAllSixtyFourPatternsAreDistinct(): void
    {
        $patterns = array_map(
            static fn (array $entry): string => $entry['pattern'],
            HexagramCatalog::all(),
        );

        self::assertCount(64, array_unique($patterns));
    }

    public function testEveryPatternIsASixCharacterBinaryString(): void
    {
        foreach (HexagramCatalog::all() as $number => $entry) {
            self::assertMatchesRegularExpression('/^[01]{6}$/', $entry['pattern'], "Hexagram {$number}");
        }
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function knownHexagramProvider(): iterable
    {
        yield 'Hexagram 1 - Qian, all yang' => ['111111', 1];
        yield 'Hexagram 2 - Kun, all yin' => ['000000', 2];
        yield 'Hexagram 11 - Tai (Heaven below, Earth above)' => ['111000', 11];
        yield 'Hexagram 12 - Pi (Earth below, Heaven above)' => ['000111', 12];
        yield 'Hexagram 29 - doubled Kan' => ['010010', 29];
        yield 'Hexagram 30 - doubled Li' => ['101101', 30];
        yield 'Hexagram 51 - doubled Zhen' => ['100100', 51];
        yield 'Hexagram 52 - doubled Gen' => ['001001', 52];
        yield 'Hexagram 57 - doubled Xun' => ['011011', 57];
        yield 'Hexagram 58 - doubled Dui' => ['110110', 58];
        yield 'Hexagram 63 - Jiji (Fire below, Water above)' => ['101010', 63];
        yield 'Hexagram 64 - Weiji (Water below, Fire above)' => ['010101', 64];
    }

    #[DataProvider('knownHexagramProvider')]
    public function testFromLinesIdentifiesKnownHexagrams(string $pattern, int $expectedNumber): void
    {
        $hexagram = Hexagram::fromLines(self::linesFromPattern($pattern));

        self::assertSame($expectedNumber, $hexagram->kingWenNumber);
    }

    public function testUpperAndLowerTrigramsAreStructurallyConsistentForAllSixtyFour(): void
    {
        foreach (HexagramCatalog::all() as $number => $entry) {
            $hexagram = Hexagram::fromLines(self::linesFromPattern($entry['pattern']));

            $lowerPattern = TrigramCatalog::patternFor($hexagram->getLowerTrigram()->id);
            $upperPattern = TrigramCatalog::patternFor($hexagram->getUpperTrigram()->id);

            self::assertSame(substr($entry['pattern'], 0, 3), $lowerPattern, "Hexagram {$number} lower trigram");
            self::assertSame(substr($entry['pattern'], 3, 3), $upperPattern, "Hexagram {$number} upper trigram");
        }
    }

    public function testRejectsWrongLineCount(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Hexagram::fromLines(self::linesFromPattern('11111'));
    }

    public function testFromKingWenNumberBuildsTheCorrectStructuralHexagramForAllSixtyFour(): void
    {
        foreach (HexagramCatalog::all() as $number => $entry) {
            $hexagram = Hexagram::fromKingWenNumber($number);

            self::assertSame($number, $hexagram->kingWenNumber, "Hexagram {$number}");
            self::assertSame($entry['chineseName'], $hexagram->chineseName, "Hexagram {$number}");
            self::assertNotSame('', $hexagram->judgment, "Hexagram {$number} judgment");
            self::assertNotSame('', $hexagram->image, "Hexagram {$number} image");
            self::assertCount(6, $hexagram->lineStatements, "Hexagram {$number} line statements");

            foreach ($hexagram->lines as $line) {
                self::assertFalse($line->changing, "Hexagram {$number} line {$line->position}");
            }
        }
    }

    public function testFromKingWenNumberThrowsForAnOutOfRangeNumber(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Hexagram::fromKingWenNumber(65);
    }
}
