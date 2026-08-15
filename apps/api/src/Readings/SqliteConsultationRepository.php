<?php

declare(strict_types=1);

namespace App\Readings;

use PDO;
use Yijing\Core\Hexagram;
use Yijing\Core\Line;

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
            $this->replaceOutcome($consultation);

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
        // rowid (not id, a UUID with no relation to insertion order) breaks ties for
        // consultations created within the same createdAt second.
        $statement = $this->pdo->prepare('SELECT * FROM consultations ORDER BY created_at DESC, rowid DESC');
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
                 resulting_king_wen_number, created_at, context, what_happened_before,
                 what_user_wants_to_understand, background_information, initial_interpretation,
                 follow_up_to_consultation_id)
             VALUES
                (:id, :question, :method, :primary_king_wen_number, :changing_line_positions,
                 :resulting_king_wen_number, :created_at, :context, :what_happened_before,
                 :what_user_wants_to_understand, :background_information, :initial_interpretation,
                 :follow_up_to_consultation_id)
             ON CONFLICT(id) DO UPDATE SET
                question = excluded.question,
                method = excluded.method,
                primary_king_wen_number = excluded.primary_king_wen_number,
                changing_line_positions = excluded.changing_line_positions,
                resulting_king_wen_number = excluded.resulting_king_wen_number,
                created_at = excluded.created_at,
                context = excluded.context,
                what_happened_before = excluded.what_happened_before,
                what_user_wants_to_understand = excluded.what_user_wants_to_understand,
                background_information = excluded.background_information,
                initial_interpretation = excluded.initial_interpretation,
                follow_up_to_consultation_id = excluded.follow_up_to_consultation_id',
        );

        $statement->execute([
            'id' => $consultation->id,
            'question' => $consultation->question,
            'method' => $consultation->method->value,
            'primary_king_wen_number' => $consultation->primaryHexagram->kingWenNumber,
            'changing_line_positions' => json_encode($consultation->changingLinePositions(), JSON_THROW_ON_ERROR),
            'resulting_king_wen_number' => $consultation->resultingHexagram->kingWenNumber,
            'created_at' => $consultation->createdAt->format(DATE_ATOM),
            'context' => $consultation->context,
            'what_happened_before' => $consultation->whatHappenedBefore,
            'what_user_wants_to_understand' => $consultation->whatUserWantsToUnderstand,
            'background_information' => $consultation->backgroundInformation,
            'initial_interpretation' => $consultation->initialInterpretation,
            'follow_up_to_consultation_id' => $consultation->followUpToConsultationId,
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

    private function replaceOutcome(Consultation $consultation): void
    {
        // Untouched (outcome === null) writes nothing — no row means "never recorded," distinct
        // from a row whose fields are all currently blank (see SPEC-020's "Edge cases").
        if ($consultation->outcome === null) {
            return;
        }

        $statement = $this->pdo->prepare(
            'INSERT INTO consultation_outcomes
                (consultation_id, what_actually_happened, outcome, reflection, recorded_at)
             VALUES
                (:consultation_id, :what_actually_happened, :outcome, :reflection, :recorded_at)
             ON CONFLICT(consultation_id) DO UPDATE SET
                what_actually_happened = excluded.what_actually_happened,
                outcome = excluded.outcome,
                reflection = excluded.reflection,
                recorded_at = excluded.recorded_at',
        );

        $statement->execute([
            'consultation_id' => $consultation->id,
            'what_actually_happened' => $consultation->outcome->whatActuallyHappened,
            'outcome' => $consultation->outcome->outcome,
            'reflection' => $consultation->outcome->reflection,
            'recorded_at' => $consultation->outcome->recordedAt->format(DATE_ATOM),
        ]);
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
            context: $row['context'] === null ? null : (string) $row['context'],
            whatHappenedBefore: $row['what_happened_before'] === null ? null : (string) $row['what_happened_before'],
            whatUserWantsToUnderstand: $row['what_user_wants_to_understand'] === null
                ? null
                : (string) $row['what_user_wants_to_understand'],
            backgroundInformation: $row['background_information'] === null
                ? null
                : (string) $row['background_information'],
            initialInterpretation: $row['initial_interpretation'] === null
                ? null
                : (string) $row['initial_interpretation'],
            outcome: $this->loadOutcome($id),
            followUpToConsultationId: $row['follow_up_to_consultation_id'] === null
                ? null
                : (string) $row['follow_up_to_consultation_id'],
        );
    }

    public function findSummaryById(string $id): ?ConsultationSummary
    {
        $statement = $this->pdo->prepare('SELECT id, question FROM consultations WHERE id = :id');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        if (!is_array($row)) {
            return null;
        }

        return new ConsultationSummary((string) $row['id'], (string) $row['question']);
    }

    /**
     * @return list<ConsultationSummary>
     */
    public function findFollowUpSummaries(string $consultationId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, question FROM consultations
             WHERE follow_up_to_consultation_id = :id ORDER BY created_at ASC',
        );
        $statement->execute(['id' => $consultationId]);

        $summaries = [];
        while (is_array($row = $statement->fetch())) {
            $summaries[] = new ConsultationSummary((string) $row['id'], (string) $row['question']);
        }

        return $summaries;
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

    private function loadOutcome(string $consultationId): ?ConsultationOutcome
    {
        $statement = $this->pdo->prepare(
            'SELECT what_actually_happened, outcome, reflection, recorded_at
             FROM consultation_outcomes WHERE consultation_id = :id',
        );
        $statement->execute(['id' => $consultationId]);
        $row = $statement->fetch();

        if (!is_array($row)) {
            return null;
        }

        return new ConsultationOutcome(
            $row['what_actually_happened'] === null ? null : (string) $row['what_actually_happened'],
            $row['outcome'] === null ? null : (string) $row['outcome'],
            $row['reflection'] === null ? null : (string) $row['reflection'],
            new \DateTimeImmutable((string) $row['recorded_at']),
        );
    }

    /**
     * @param list<int> $changingPositions
     */
    private static function hexagramFromKingWenNumber(int $kingWenNumber, array $changingPositions): Hexagram
    {
        $base = Hexagram::fromKingWenNumber($kingWenNumber);

        if ($changingPositions === []) {
            return $base;
        }

        $lines = array_map(
            static fn (Line $line): Line => in_array($line->position, $changingPositions, true)
                ? new Line($line->position, $line->polarity, true)
                : $line,
            $base->lines,
        );

        return Hexagram::fromLines($lines);
    }
}
