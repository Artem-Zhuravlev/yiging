<?php

declare(strict_types=1);

namespace Yijing\Core;

/**
 * Which text is the operative answer for a cast, by number of changing lines — the standard
 * Song-dynasty synthesis (Zhu Xi, Zhouyi benyi), the version reproduced in the Wilhelm/Baynes
 * introduction and most modern manuals (SPEC-052):
 *
 *   0  -> the Judgment of the primary hexagram
 *   1  -> the text of that changing line (primary)
 *   2  -> the texts of both changing lines (primary); the upper governs
 *   3  -> the Judgments of both hexagrams; the primary governs
 *   4  -> the two *unchanged* lines of the resulting hexagram; the lower governs
 *   5  -> the one *unchanged* line of the resulting hexagram
 *   6  -> primary is Qian -> "Use Nine"; primary is Kun -> "Use Six";
 *         otherwise the Judgment of the resulting hexagram
 *
 * Pure — no I/O. The API resolves the actual text for each {@see CastReadingRef}.
 */
final readonly class CastReading
{
    private const QIAN_KING_WEN_NUMBER = 1;
    private const KUN_KING_WEN_NUMBER = 2;

    /**
     * @param list<CastReadingRef>       $refs        judgment(s) / line(s) to read, in order
     * @param 'use-nine'|'use-six'|null  $specialText only set for the all-changing Qian/Kun case
     */
    private function __construct(
        public int $changingLineCount,
        public ReadingRule $rule,
        public array $refs,
        public ?string $specialText,
    ) {
    }

    /**
     * @param list<int> $changingPositions positions 1-6 (any order); deduped and sorted here
     */
    public static function forCast(Hexagram $primary, array $changingPositions): self
    {
        $positions = array_values(array_unique($changingPositions));
        sort($positions);

        if (count($positions) !== count($changingPositions)) {
            throw new \InvalidArgumentException('Changing-line positions must be unique.');
        }

        foreach ($positions as $position) {
            if ($position < 1 || $position > 6) {
                throw new \InvalidArgumentException(
                    sprintf('Changing-line position must be 1-6, got %d.', $position),
                );
            }
        }

        $count = count($positions);
        $rule = ReadingRule::fromCount($count);

        return match ($count) {
            0 => new self($count, $rule, [CastReadingRef::judgment('primary', true)], null),
            1 => new self($count, $rule, [CastReadingRef::line('primary', $positions[0], true)], null),
            2 => new self($count, $rule, [
                CastReadingRef::line('primary', $positions[0], false),
                CastReadingRef::line('primary', $positions[1], true),
            ], null),
            3 => new self($count, $rule, [
                CastReadingRef::judgment('primary', true),
                CastReadingRef::judgment('resulting', false),
            ], null),
            4, 5 => self::fromUnchangedLines($count, $rule, $positions),
            default => self::forAllSixChanging($count, $rule, $primary),
        };
    }

    /**
     * @return array{changingLineCount: int, rule: string, refs: list<array<string, mixed>>, specialText: string|null}
     */
    public function toArray(): array
    {
        return [
            'changingLineCount' => $this->changingLineCount,
            'rule' => $this->rule->value,
            'refs' => array_map(static fn (CastReadingRef $ref): array => $ref->toArray(), $this->refs),
            'specialText' => $this->specialText,
        ];
    }

    /**
     * n=4: the two unchanged lines of the resulting hexagram, ascending, the lower governing.
     * n=5: the one unchanged line of the resulting hexagram.
     *
     * @param list<int> $changingPositions ascending
     */
    private static function fromUnchangedLines(int $count, ReadingRule $rule, array $changingPositions): self
    {
        $unchanged = array_values(array_diff([1, 2, 3, 4, 5, 6], $changingPositions));

        $refs = [];
        foreach ($unchanged as $index => $position) {
            $refs[] = CastReadingRef::line('resulting', $position, $index === 0);
        }

        return new self($count, $rule, $refs, null);
    }

    private static function forAllSixChanging(int $count, ReadingRule $rule, Hexagram $primary): self
    {
        return match ($primary->kingWenNumber) {
            self::QIAN_KING_WEN_NUMBER => new self($count, $rule, [], 'use-nine'),
            self::KUN_KING_WEN_NUMBER => new self($count, $rule, [], 'use-six'),
            default => new self($count, $rule, [CastReadingRef::judgment('resulting', true)], null),
        };
    }
}
