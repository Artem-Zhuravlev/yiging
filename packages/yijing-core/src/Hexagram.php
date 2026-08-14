<?php

declare(strict_types=1);

namespace Yijing\Core;

use Yijing\Core\Data\HexagramCatalog;
use Yijing\Core\Data\HexagramTextCatalog;

final readonly class Hexagram
{
    /**
     * @param list<Line> $lines exactly 6 lines, positions 1-6, bottom to top
     * @param list<string> $lineStatements exactly 6 entries, positions 1-6
     */
    private function __construct(
        public array $lines,
        public int $kingWenNumber,
        public string $chineseName,
        public string $pinyin,
        public string $judgment,
        public string $image,
        public array $lineStatements,
    ) {
    }

    /**
     * @param list<Line> $lines exactly 6 lines, positions 1-6, bottom to top
     */
    public static function fromLines(array $lines): self
    {
        if (count($lines) !== 6) {
            throw new \InvalidArgumentException(
                sprintf('A hexagram requires exactly 6 lines, got %d.', count($lines)),
            );
        }

        $lines = self::renumbered($lines);
        $pattern = self::patternOf($lines);
        $kingWenNumber = HexagramCatalog::kingWenNumberForPattern($pattern);
        $entry = HexagramCatalog::entryFor($kingWenNumber);
        $text = HexagramTextCatalog::textFor($kingWenNumber);

        return new self(
            $lines,
            $kingWenNumber,
            $entry['chineseName'],
            $entry['pinyin'],
            $text['judgment'],
            $text['image'],
            $text['lineStatements'],
        );
    }

    /**
     * The hexagram for a King Wen number alone, with all 6 lines non-changing — structural
     * identity only. Casting-specific changing-line state is layered on separately by callers
     * that need it (e.g. a persisted Consultation reapplying its stored changing positions).
     */
    public static function fromKingWenNumber(int $kingWenNumber): self
    {
        $entry = HexagramCatalog::entryFor($kingWenNumber);

        $lines = array_map(
            static fn (int $index, string $char): Line => new Line(
                $index + 1,
                $char === '1' ? LinePolarity::Yang : LinePolarity::Yin,
                false,
            ),
            array_keys(str_split($entry['pattern'])),
            str_split($entry['pattern']),
        );

        return self::fromLines($lines);
    }

    public function getLowerTrigram(): Trigram
    {
        return Trigram::fromLines(self::renumbered(array_slice($this->lines, 0, 3)));
    }

    /**
     * The hexagram's Unicode glyph (U+4DC0-U+4DFF, "Yijing Hexagram Symbols"), a block ordered
     * exactly by King Wen sequence — U+4DC0 is hexagram 1, U+4DFF is hexagram 64.
     */
    public function symbol(): string
    {
        return mb_chr(0x4DC0 + $this->kingWenNumber - 1);
    }

    public function getUpperTrigram(): Trigram
    {
        return Trigram::fromLines(self::renumbered(array_slice($this->lines, 3, 3)));
    }

    public function changeLine(int $position): self
    {
        $lines = array_map(
            static fn (Line $line): Line => $line->position === $position
                ? $line->withPolarityFlipped()
                : $line,
            $this->lines,
        );

        return self::fromLines($lines);
    }

    public function getResultingHexagram(): self
    {
        $lines = array_map(
            static fn (Line $line): Line => $line->changing ? $line->withPolarityFlipped() : $line,
            $this->lines,
        );

        return self::fromLines($lines);
    }

    public function equals(self $other): bool
    {
        return $this->kingWenNumber === $other->kingWenNumber;
    }

    /**
     * @param list<Line> $lines
     * @return list<Line>
     */
    private static function renumbered(array $lines): array
    {
        return array_map(
            static fn (int $index, Line $line): Line => new Line($index + 1, $line->polarity, $line->changing),
            array_keys($lines),
            $lines,
        );
    }

    /**
     * @param list<Line> $lines
     */
    private static function patternOf(array $lines): string
    {
        return implode('', array_map(
            static fn (Line $line): string => $line->isYang() ? '1' : '0',
            $lines,
        ));
    }
}
