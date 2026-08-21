<?php

declare(strict_types=1);

namespace App\Tests\Journal;

use App\Journal\JournalEntry;
use PHPUnit\Framework\TestCase;

final class JournalEntryTest extends TestCase
{
    public function testConstructsWithValidText(): void
    {
        $entry = new JournalEntry('id-1', 'Feeling reflective today.', new \DateTimeImmutable());

        self::assertSame('id-1', $entry->id);
        self::assertSame('Feeling reflective today.', $entry->text);
    }

    public function testRejectsEmptyText(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new JournalEntry('id-1', '', new \DateTimeImmutable());
    }

    public function testRejectsWhitespaceOnlyText(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new JournalEntry('id-1', '   ', new \DateTimeImmutable());
    }

    public function testAcceptsTextAtExactlyTheLengthLimit(): void
    {
        $entry = new JournalEntry('id-1', str_repeat('a', 5000), new \DateTimeImmutable());

        self::assertSame(5000, mb_strlen($entry->text));
    }

    public function testRejectsTextOverTheLengthLimit(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new JournalEntry('id-1', str_repeat('a', 5001), new \DateTimeImmutable());
    }
}
