<?php

declare(strict_types=1);

namespace App\Hexagrams;

use PDO;

final class SqliteHexagramFavoritesRepository implements HexagramFavoritesRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function isFavorite(int $kingWenNumber): bool
    {
        $statement = $this->pdo->prepare(
            'SELECT 1 FROM favorite_hexagrams WHERE king_wen_number = :n LIMIT 1',
        );
        $statement->execute(['n' => $kingWenNumber]);

        return $statement->fetchColumn() !== false;
    }

    public function add(int $kingWenNumber): void
    {
        $statement = $this->pdo->prepare(
            'INSERT OR IGNORE INTO favorite_hexagrams (king_wen_number) VALUES (:n)',
        );
        $statement->execute(['n' => $kingWenNumber]);
    }

    public function remove(int $kingWenNumber): void
    {
        $statement = $this->pdo->prepare('DELETE FROM favorite_hexagrams WHERE king_wen_number = :n');
        $statement->execute(['n' => $kingWenNumber]);
    }

    public function allFavoriteNumbers(): array
    {
        $statement = $this->pdo->prepare('SELECT king_wen_number FROM favorite_hexagrams ORDER BY king_wen_number ASC');
        $statement->execute();

        /** @var list<int> */
        return array_map(intval(...), $statement->fetchAll(PDO::FETCH_COLUMN));
    }
}
