<?php

declare(strict_types=1);

namespace App\Readings;

use PDO;
use Yijing\Core\Data\HexagramCatalog;
use Yijing\Core\Hexagram;
use Yijing\Core\Line;
use Yijing\Core\LinePolarity;

final class SqliteConsultationRepository implements ConsultationRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function save(Consultation $consultation): void
    {
        $this->pdo->beginTransaction();

        try {
            $this->upsertConsultation($consultation);
            $this->replaceNotes($consultation);
            $this->replaceTags($consultation);

            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();

            throw $e;
        }
    }

    public function findById(string $id): ?Consultation
    {
        $statement = $this->pdo->prepare('SELECT * FROM consultations WHERE id = :id');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        if (!is_array($row)) {
            return null;
        }

        return $this->hydrate($row);
    }

    public function findAll(): array
    {
        $statement = $this->pdo->prepare('SELECT * FROM consultations ORDER BY created_at DESC, id DESC');
        $statement->execute();

        $consultations = [];
        while (is_array($row = $statement->fetch())) {
            $consultations[] = $this->hydrate($row);
        }

        return $consultations;
    }

    private function upsertConsultation(Consultation $consultation): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO consultations
                (id, question, method, primary_king_wen_number, changing_line_positions,
                 resulting_king_wen_number, created_at)
             VALUES
                (:id, :question, :method, :primary_king_wen_number, :changing_line_positions,
                 :resulting_king_wen_number, :created_at)
             ON CONFLICT(id) DO UPDATE SET
                question = excluded.question,
                method = excluded.method,
                primary_king_wen_number = excluded.primary_king_wen_number,
                changing_line_positions = excluded.changing_line_positions,
                resulting_king_wen_number = excluded.resulting_king_wen_number,
                created_at = excluded.created_at',
        );

        $statement->execute([
            'id' => $consultation->id,
            'question' => $consultation->question,
            'method' => $consultation->method->value,
            'primary_king_wen_number' => $consultation->primaryHexagram->kingWenNumber,
            'changing_line_positions' => json_encode($consultation->changingLinePositions(), JSON_THROW_ON_ERROR),
            'resulting_king_wen_number' => $consultation->resultingHexagram->kingWenNumber,
            'created_at' => $consultation->createdAt->format(DATE_ATOM),
        ]);
    }

    private function replaceNotes(Consultation $consultation): void
    {
        $delete = $this->pdo->prepare('DELETE FROM consultation_notes WHERE consultation_id = :id');
        $delete->execute(['id' => $consultation->id]);

        $insert = $this->pdo->prepare(
            'INSERT INTO consultation_notes (consultation_id, label, text, created_at, sort_order)
             VALUES (:consultation_id, :label, :text, :created_at, :sort_order)',
        );

        foreach ($consultation->notes as $index => $note) {
            $insert->execute([
                'consultation_id' => $consultation->id,
                'label' => $note->label->value,
                'text' => $note->text,
                'created_at' => $note->createdAt->format(DATE_ATOM),
                'sort_order' => $index,
            ]);
        }
    }

    private function replaceTags(Consultation $consultation): void
    {
        $delete = $this->pdo->prepare('DELETE FROM consultation_tags WHERE consultation_id = :id');
        $delete->execute(['id' => $consultation->id]);

        $insertTag = $this->pdo->prepare('INSERT OR IGNORE INTO tags (name) VALUES (:name)');
        $selectTagId = $this->pdo->prepare('SELECT id FROM tags WHERE name = :name');
        $link = $this->pdo->prepare(
            'INSERT INTO consultation_tags (consultation_id, tag_id) VALUES (:consultation_id, :tag_id)',
        );

        foreach ($consultation->tags as $tag) {
            $insertTag->execute(['name' => $tag]);
            $selectTagId->execute(['name' => $tag]);
            $tagId = $selectTagId->fetchColumn();

            $link->execute([
                'consultation_id' => $consultation->id,
                'tag_id' => $tagId,
            ]);
        }
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): Consultation
    {
        $id = (string) $row['id'];

        /** @var list<int> $changingLinePositions */
        $changingLinePositions = json_decode(
            (string) $row['changing_line_positions'],
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        $primaryHexagram = self::hexagramFromKingWenNumber(
            (int) $row['primary_king_wen_number'],
            $changingLinePositions,
        );
        $resultingHexagram = self::hexagramFromKingWenNumber((int) $row['resulting_king_wen_number'], []);

        return Consultation::reconstitute(
            $id,
            (string) $row['question'],
            CastingMethodName::from((string) $row['method']),
            $primaryHexagram,
            $resultingHexagram,
            new \DateTimeImmutable((string) $row['created_at']),
            $this->loadNotes($id),
            $this->loadTags($id),
        );
    }

    /**
     * @return list<ConsultationNote>
     */
    private function loadNotes(string $consultationId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT label, text, created_at FROM consultation_notes
             WHERE consultation_id = :id ORDER BY sort_order ASC',
        );
        $statement->execute(['id' => $consultationId]);

        $notes = [];
        while (is_array($row = $statement->fetch())) {
            $notes[] = new ConsultationNote(
                NoteLabel::from((string) $row['label']),
                (string) $row['text'],
                new \DateTimeImmutable((string) $row['created_at']),
            );
        }

        return $notes;
    }

    /**
     * @return list<string>
     */
    private function loadTags(string $consultationId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT t.name FROM tags t
             INNER JOIN consultation_tags ct ON ct.tag_id = t.id
             WHERE ct.consultation_id = :id
             ORDER BY t.name ASC',
        );
        $statement->execute(['id' => $consultationId]);

        $tags = [];
        while (is_array($row = $statement->fetch())) {
            $tags[] = (string) $row['name'];
        }

        return $tags;
    }

    /**
     * @param list<int> $changingPositions
     */
    private static function hexagramFromKingWenNumber(int $kingWenNumber, array $changingPositions): Hexagram
    {
        $pattern = HexagramCatalog::entryFor($kingWenNumber)['pattern'];

        $lines = [];
        foreach (str_split($pattern) as $index => $char) {
            $position = $index + 1;
            $lines[] = new Line(
                $position,
                $char === '1' ? LinePolarity::Yang : LinePolarity::Yin,
                in_array($position, $changingPositions, true),
            );
        }

        return Hexagram::fromLines($lines);
    }
}
