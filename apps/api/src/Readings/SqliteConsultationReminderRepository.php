<?php

declare(strict_types=1);

namespace App\Readings;

use PDO;
use Yijing\Core\Hexagram;

final class SqliteConsultationReminderRepository implements ConsultationReminderRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function set(
        string $consultationId,
        \DateTimeImmutable $remindAt,
        \DateTimeImmutable $createdAt,
    ): void {
        $statement = $this->pdo->prepare(
            'INSERT INTO consultation_reminders (consultation_id, remind_at, created_at)
             VALUES (:consultation_id, :remind_at, :created_at)
             ON CONFLICT(consultation_id) DO UPDATE SET remind_at = excluded.remind_at',
        );

        $statement->execute([
            'consultation_id' => $consultationId,
            'remind_at' => $remindAt->format(DATE_ATOM),
            'created_at' => $createdAt->format(DATE_ATOM),
        ]);
    }

    public function clear(string $consultationId): void
    {
        $statement = $this->pdo->prepare(
            'DELETE FROM consultation_reminders WHERE consultation_id = :consultation_id',
        );
        $statement->execute(['consultation_id' => $consultationId]);
    }

    public function findRemindAt(string $consultationId): ?\DateTimeImmutable
    {
        $statement = $this->pdo->prepare(
            'SELECT remind_at FROM consultation_reminders WHERE consultation_id = :consultation_id',
        );
        $statement->execute(['consultation_id' => $consultationId]);
        $value = $statement->fetchColumn();

        return $value === false ? null : new \DateTimeImmutable((string) $value);
    }

    public function findDue(\DateTimeImmutable $now): array
    {
        // remind_at is always stored as a fixed-width DATE_ATOM string, so the lexical `<=`
        // comparison is a chronological one (the same assumption findListPage makes of
        // created_at). The LEFT JOIN + IS NULL drops any consultation that already has an
        // outcome — its reminder has served its purpose.
        $statement = $this->pdo->prepare(
            'SELECT r.consultation_id, r.remind_at, r.created_at,
                    c.question, c.primary_king_wen_number, c.resulting_king_wen_number
             FROM consultation_reminders r
             JOIN consultations c ON c.id = r.consultation_id
             LEFT JOIN consultation_outcomes o ON o.consultation_id = c.id
             WHERE o.consultation_id IS NULL AND r.remind_at <= :now
             ORDER BY r.remind_at ASC',
        );
        $statement->execute(['now' => $now->format(DATE_ATOM)]);

        $due = [];
        while (is_array($row = $statement->fetch())) {
            $primary = Hexagram::fromKingWenNumber((int) $row['primary_king_wen_number']);
            $resulting = Hexagram::fromKingWenNumber((int) $row['resulting_king_wen_number']);

            $due[] = new DueReminder(
                id: (string) $row['consultation_id'],
                question: (string) $row['question'],
                primaryKingWenNumber: $primary->kingWenNumber,
                primaryChineseName: $primary->chineseName,
                primaryPinyin: $primary->pinyin,
                resultingKingWenNumber: $resulting->kingWenNumber,
                resultingChineseName: $resulting->chineseName,
                resultingPinyin: $resulting->pinyin,
                remindAtAtom: (new \DateTimeImmutable((string) $row['remind_at']))->format(DATE_ATOM),
                createdAtAtom: (new \DateTimeImmutable((string) $row['created_at']))->format(DATE_ATOM),
            );
        }

        return $due;
    }
}
