<?php

declare(strict_types=1);

namespace App\Journal;

use App\Core\ListCursor;
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
            $entries[] = $this->hydrate($row);
        }

        return $entries;
    }

    public function findPage(int $limit, ?string $cursor): JournalListPage
    {
        $where = '';
        $params = [];

        if ($cursor !== null) {
            [$cursorAt, $cursorRowid] = ListCursor::decode($cursor);
            $where = ' WHERE (created_at < :cursor_at OR (created_at = :cursor_at AND rowid < :cursor_rowid))';
            $params['cursor_at'] = [$cursorAt, PDO::PARAM_STR];
            $params['cursor_rowid'] = [$cursorRowid, PDO::PARAM_INT];
        }

        // limit + 1: the probe row (if present) is dropped — it only signals a next page exists.
        $statement = $this->pdo->prepare(
            'SELECT *, rowid AS row_id FROM journal_entries'
            . $where
            . ' ORDER BY created_at DESC, rowid DESC LIMIT :limit_plus_one',
        );
        foreach ($params as $name => [$value, $type]) {
            $statement->bindValue(':' . $name, $value, $type);
        }
        $statement->bindValue(':limit_plus_one', $limit + 1, PDO::PARAM_INT);
        $statement->execute();

        /** @var list<array<string, mixed>> $rows */
        $rows = $statement->fetchAll();

        $hasMore = count($rows) > $limit;
        $pageRows = $hasMore ? array_slice($rows, 0, $limit) : $rows;

        $items = array_map(fn (array $row): JournalEntry => $this->hydrate($row), $pageRows);

        $nextCursor = null;
        if ($hasMore && $pageRows !== []) {
            $last = $pageRows[count($pageRows) - 1];
            $nextCursor = ListCursor::encode((string) $last['created_at'], (int) $last['row_id']);
        }

        return new JournalListPage($items, $nextCursor);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): JournalEntry
    {
        return new JournalEntry(
            (string) $row['id'],
            (string) $row['text'],
            new \DateTimeImmutable((string) $row['created_at']),
        );
    }
}
