<?php

declare(strict_types=1);

namespace Yijing\Core;

use Yijing\Core\Data\TrigramCatalog;

final readonly class Trigram
{
    /**
     * @param list<Line> $lines exactly 3 lines, positions 1-3, bottom to top
     */
    private function __construct(
        public array $lines,
        public TrigramId $id,
    ) {
    }

    /**
     * @param list<Line> $lines exactly 3 lines, positions 1-3, bottom to top
     */
    public static function fromLines(array $lines): self
    {
        if (count($lines) !== 3) {
            throw new \InvalidArgumentException(
                sprintf('A trigram requires exactly 3 lines, got %d.', count($lines)),
            );
        }

        $pattern = self::patternOf($lines);
        $id = TrigramCatalog::idForPattern($pattern);

        return new self($lines, $id);
    }

    public function name(): string
    {
        return TrigramCatalog::attributesFor($this->id)['name'];
    }

    public function chineseName(): string
    {
        return TrigramCatalog::attributesFor($this->id)['chineseName'];
    }

    public function pinyin(): string
    {
        return TrigramCatalog::attributesFor($this->id)['pinyin'];
    }

    public function symbol(): string
    {
        return TrigramCatalog::attributesFor($this->id)['symbol'];
    }

    public function element(): string
    {
        return TrigramCatalog::attributesFor($this->id)['element'];
    }

    public function familyMember(): string
    {
        return TrigramCatalog::attributesFor($this->id)['familyMember'];
    }

    public function direction(): string
    {
        return TrigramCatalog::attributesFor($this->id)['direction'];
    }

    public function image(): string
    {
        return TrigramCatalog::attributesFor($this->id)['image'];
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
