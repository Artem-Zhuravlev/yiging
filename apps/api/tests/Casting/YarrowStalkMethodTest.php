<?php

declare(strict_types=1);

namespace App\Tests\Casting;

use App\Casting\YarrowStalkMethod;
use App\Tests\Casting\Support\FakeRandomSource;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Yijing\Core\LinePolarity;

final class YarrowStalkMethodTest extends TestCase
{
    /**
     * @return iterable<string, array{int, LinePolarity, bool}>
     */
    public static function drawProvider(): iterable
    {
        // 1/16 old yin · 5/16 young yang · 7/16 young yin · 3/16 old yang, plus each boundary.
        yield '1 -> old yin (changing)' => [1, LinePolarity::Yin, true];
        yield '2 -> young yang' => [2, LinePolarity::Yang, false];
        yield '6 -> young yang (upper bound)' => [6, LinePolarity::Yang, false];
        yield '7 -> young yin (lower bound)' => [7, LinePolarity::Yin, false];
        yield '13 -> young yin (upper bound)' => [13, LinePolarity::Yin, false];
        yield '14 -> old yang (lower bound, changing)' => [14, LinePolarity::Yang, true];
        yield '16 -> old yang (changing)' => [16, LinePolarity::Yang, true];
    }

    #[DataProvider('drawProvider')]
    public function testEachDrawResolvesToTheExpectedLineState(
        int $draw,
        LinePolarity $expectedPolarity,
        bool $expectedChanging,
    ): void {
        $method = new YarrowStalkMethod(new FakeRandomSource(array_fill(0, 6, $draw)));

        $hexagram = $method->cast();

        self::assertCount(6, $hexagram->lines);
        foreach ($hexagram->lines as $line) {
            self::assertSame($expectedPolarity, $line->polarity);
            self::assertSame($expectedChanging, $line->changing);
        }
    }

    public function testCastBuildsSixLinesInPositionOrder(): void
    {
        $method = new YarrowStalkMethod(new FakeRandomSource([1, 6, 7, 13, 14, 16]));

        $hexagram = $method->cast();

        self::assertSame([1, 2, 3, 4, 5, 6], array_map(
            static fn ($line): int => $line->position,
            $hexagram->lines,
        ));
        self::assertSame(LinePolarity::Yin, $hexagram->lines[0]->polarity);
        self::assertTrue($hexagram->lines[0]->changing);
        self::assertSame(LinePolarity::Yang, $hexagram->lines[1]->polarity);
        self::assertFalse($hexagram->lines[1]->changing);
        self::assertSame(LinePolarity::Yang, $hexagram->lines[5]->polarity);
        self::assertTrue($hexagram->lines[5]->changing);
    }

    public function testExhaustedSourceThrows(): void
    {
        $this->expectException(\OutOfBoundsException::class);

        (new YarrowStalkMethod(new FakeRandomSource([7, 7])))->cast();
    }
}
