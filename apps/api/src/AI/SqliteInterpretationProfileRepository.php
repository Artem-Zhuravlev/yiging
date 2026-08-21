<?php

declare(strict_types=1);

namespace App\AI;

use PDO;

final class SqliteInterpretationProfileRepository implements InterpretationProfileRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function get(): InterpretationProfile
    {
        $statement = $this->pdo->prepare('SELECT tone, length, notes FROM interpretation_profile WHERE id = 1');
        $statement->execute();
        $row = $statement->fetch();

        if (!is_array($row)) {
            return InterpretationProfile::default();
        }

        return new InterpretationProfile(
            Tone::from((string) $row['tone']),
            ResponseLength::from((string) $row['length']),
            $row['notes'] === null ? null : (string) $row['notes'],
        );
    }

    public function save(InterpretationProfile $profile): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO interpretation_profile (id, tone, length, notes) VALUES (1, :tone, :length, :notes)
             ON CONFLICT(id) DO UPDATE SET tone = excluded.tone, length = excluded.length, notes = excluded.notes',
        );
        $statement->execute([
            'tone' => $profile->tone->value,
            'length' => $profile->length->value,
            'notes' => $profile->notes,
        ]);
    }
}
