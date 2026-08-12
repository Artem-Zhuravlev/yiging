<?php

declare(strict_types=1);

namespace Yijing\Core\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Yijing\Core\Tests\Support\LinePatternTrait;
use Yijing\Core\Trigram;
use Yijing\Core\TrigramId;

final class TrigramTest extends TestCase
{
    use LinePatternTrait;

    /**
     * @return iterable<string, array{string, TrigramId}>
     */
    public static function patternProvider(): iterable
    {
        yield 'Qian (111)' => ['111', TrigramId::Qian];
        yield 'Kun (000)' => ['000', TrigramId::Kun];
        yield 'Zhen (100)' => ['100', TrigramId::Zhen];
        yield 'Kan (010)' => ['010', TrigramId::Kan];
        yield 'Gen (001)' => ['001', TrigramId::Gen];
        yield 'Xun (011)' => ['011', TrigramId::Xun];
        yield 'Li (101)' => ['101', TrigramId::Li];
        yield 'Dui (110)' => ['110', TrigramId::Dui];
    }

    #[DataProvider('patternProvider')]
    public function testFromLinesIdentifiesEachOfTheEightTrigrams(string $pattern, TrigramId $expected): void
    {
        $trigram = Trigram::fromLines(self::linesFromPattern($pattern));

        self::assertSame($expected, $trigram->id);
    }

    public function testAllEightPatternsAreDistinct(): void
    {
        $ids = array_map(
            static fn (array $case): TrigramId => Trigram::fromLines(self::linesFromPattern($case[0]))->id,
            iterator_to_array(self::patternProvider()),
        );

        self::assertCount(8, array_unique(array_map(static fn (TrigramId $id): string => $id->name, $ids)));
    }

    public function testQianAttributes(): void
    {
        $qian = Trigram::fromLines(self::linesFromPattern('111'));

        self::assertSame('乾', $qian->chineseName());
        self::assertSame('Father', $qian->familyMember());
        self::assertSame('Heaven', $qian->image());
        self::assertSame('☰', $qian->symbol());
    }

    public function testKunAttributes(): void
    {
        $kun = Trigram::fromLines(self::linesFromPattern('000'));

        self::assertSame('坤', $kun->chineseName());
        self::assertSame('Mother', $kun->familyMember());
        self::assertSame('Earth', $kun->image());
    }

    public function testRejectsWrongLineCount(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Trigram::fromLines(self::linesFromPattern('11'));
    }
}
