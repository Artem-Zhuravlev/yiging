<?php

declare(strict_types=1);

namespace App\Journal;

use PDO;

final class SqliteJournalRepository implements JournalRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function save(JournalEntry $entry): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO journal_entries (id, text, created_at) VALUES (:id, :text, :created_at)',
        );
        $statement->execute([
            'id' => $entry->id,
            'text' => $entry->text,
            'created_at' => $entry->createdAt->format(DATE_ATOM),
        ]);
    }

    public function findAll(): array
    {
        // rowid (not id, a UUID with no relation to insertion order) breaks ties for entries
        // created within the same createdAt second — same pattern as
        // SqliteConsultationRepository::findAll().
        $statement = $this->pdo->prepare('SELECT * FROM journal_entries ORDER BY created_at DESC, rowid DESC');
        $statement->execute();

        $entries = [];
        while (is_array($row = $statement->fetch())) {
            $entries[] = new JournalEntry(
                (string) $row['id'],
                (string) $row['text'],
                new \DateTimeImmutable((string) $row['created_at']),
            );
        }

        return $entries;
    }
}
