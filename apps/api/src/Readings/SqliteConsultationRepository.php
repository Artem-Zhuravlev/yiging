<?php

declare(strict_types=1);

namespace App\Readings;

use App\Core\ListCursor;
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

    public function findListPage(ConsultationListQuery $query): ConsultationListPage
    {
        $where = [];
        $params = [];

        if ($query->cursor !== null) {
            [$cursorAt, $cursorRowid] = ListCursor::decode($query->cursor);
            $where[] = '(c.created_at < :cursor_at OR (c.created_at = :cursor_at AND c.rowid < :cursor_rowid))';
            $params['cursor_at'] = $cursorAt;
            $params['cursor_rowid'] = $cursorRowid;
        }

        if ($query->q !== null) {
            $like = '%' . self::escapeLike($query->q) . '%';
            $where[] = "(c.question LIKE :q ESCAPE '\\' OR EXISTS ("
                . 'SELECT 1 FROM consultation_notes n '
                . "WHERE n.consultation_id = c.id AND n.text LIKE :q ESCAPE '\\'))";
            $params['q'] = $like;
        }

        if ($query->tags !== []) {
            $placeholders = [];
            foreach ($query->tags as $i => $tag) {
                $placeholders[] = ":tag{$i}";
                $params["tag{$i}"] = $tag;
            }
            $where[] = '(SELECT COUNT(DISTINCT t.name) FROM consultation_tags ct '
                . 'JOIN tags t ON t.id = ct.tag_id '
                . 'WHERE ct.consultation_id = c.id AND t.name IN (' . implode(', ', $placeholders) . ')) '
                . '= :tag_count';
            $params['tag_count'] = count($query->tags);
        }

        if ($query->favoriteOnly) {
            $where[] = 'c.is_favorite = 1';
        }

        $whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);

        // limit + 1: the extra row (if present) is not returned — it only tells us a next page
        // exists, so nextCursor is non-null exactly when there is more after this page.
        $sql = 'SELECT c.*, c.rowid AS row_id FROM consultations c'
            . $whereSql
            . ' ORDER BY c.created_at DESC, c.rowid DESC LIMIT :limit_plus_one';

        $statement = $this->pdo->prepare($sql);
        foreach ($params as $name => $value) {
            $statement->bindValue(':' . $name, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $statement->bindValue(':limit_plus_one', $query->limit + 1, PDO::PARAM_INT);
        $statement->execute();

        /** @var list<array<string, mixed>> $rows */
        $rows = $statement->fetchAll();

        $hasMore = count($rows) > $query->limit;
        $pageRows = $hasMore ? array_slice($rows, 0, $query->limit) : $rows;

        $tagsById = $this->loadTagsForIds(array_map(static fn (array $r): string => (string) $r['id'], $pageRows));

        $items = array_map(
            fn (array $row): ConsultationListItem => $this->toListItem($row, $tagsById[(string) $row['id']] ?? []),
            $pageRows,
        );

        $nextCursor = null;
        if ($hasMore && $pageRows !== []) {
            $last = $pageRows[count($pageRows) - 1];
            $nextCursor = ListCursor::encode((string) $last['created_at'], (int) $last['row_id']);
        }

        return new ConsultationListPage($items, $nextCursor);
    }

    public function allTagNames(): array
    {
        $statement = $this->pdo->query(
            'SELECT DISTINCT t.name FROM tags t
             INNER JOIN consultation_tags ct ON ct.tag_id = t.id
             ORDER BY t.name ASC',
        );

        /** @var list<string> $names */
        $names = $statement === false ? [] : array_map(strval(...), $statement->fetchAll(PDO::FETCH_COLUMN));

        return $names;
    }

    /**
     * @param list<string> $ids
     *
     * @return array<string, list<string>> consultation id => sorted tag names
     */
    private function loadTagsForIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($ids), '?'));
        $statement = $this->pdo->prepare(
            "SELECT ct.consultation_id, t.name FROM consultation_tags ct
             INNER JOIN tags t ON t.id = ct.tag_id
             WHERE ct.consultation_id IN ({$placeholders})
             ORDER BY t.name ASC",
        );
        $statement->execute($ids);

        $byId = [];
        while (is_array($row = $statement->fetch())) {
            $byId[(string) $row['consultation_id']][] = (string) $row['name'];
        }

        return $byId;
    }

    /**
     * @param array<string, mixed> $row
     * @param list<string>         $tags
     */
    private function toListItem(array $row, array $tags): ConsultationListItem
    {
        /** @var list<int> $changingLinePositions */
        $changingLinePositions = json_decode((string) $row['changing_line_positions'], true, 512, JSON_THROW_ON_ERROR);

        $primary = Hexagram::fromKingWenNumber((int) $row['primary_king_wen_number']);
        $resulting = Hexagram::fromKingWenNumber((int) $row['resulting_king_wen_number']);

        return new ConsultationListItem(
            id: (string) $row['id'],
            question: (string) $row['question'],
            method: (string) $row['method'],
            primaryKingWenNumber: $primary->kingWenNumber,
            primaryChineseName: $primary->chineseName,
            primaryPinyin: $primary->pinyin,
            changingLinePositions: $changingLinePositions,
            resultingKingWenNumber: $resulting->kingWenNumber,
            resultingChineseName: $resulting->chineseName,
            resultingPinyin: $resulting->pinyin,
            createdAtAtom: (new \DateTimeImmutable((string) $row['created_at']))->format(DATE_ATOM),
            tags: $tags,
            favorite: (bool) $row['is_favorite'],
        );
    }

    private static function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    private function upsertConsultation(Consultation $consultation): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO consultations
                (id, question, method, primary_king_wen_number, changing_line_positions,
                 resulting_king_wen_number, created_at, context, what_happened_before,
                 what_user_wants_to_understand, background_information, initial_interpretation,
                 follow_up_to_consultation_id, is_favorite)
             VALUES
                (:id, :question, :method, :primary_king_wen_number, :changing_line_positions,
                 :resulting_king_wen_number, :created_at, :context, :what_happened_before,
                 :what_user_wants_to_understand, :background_information, :initial_interpretation,
                 :follow_up_to_consultation_id, :is_favorite)
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
                follow_up_to_consultation_id = excluded.follow_up_to_consultation_id,
                is_favorite = excluded.is_favorite',
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
            'is_favorite' => $consultation->favorite ? 1 : 0,
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
                (consultation_id, what_actually_happened, outcome, reflection, recorded_at,
                 interpretation_lens, interpretation_summary)
             VALUES
                (:consultation_id, :what_actually_happened, :outcome, :reflection, :recorded_at,
                 :interpretation_lens, :interpretation_summary)
             ON CONFLICT(consultation_id) DO UPDATE SET
                what_actually_happened = excluded.what_actually_happened,
                outcome = excluded.outcome,
                reflection = excluded.reflection,
                recorded_at = excluded.recorded_at,
                interpretation_lens = excluded.interpretation_lens,
                interpretation_summary = excluded.interpretation_summary',
        );

        $statement->execute([
            'consultation_id' => $consultation->id,
            'what_actually_happened' => $consultation->outcome->whatActuallyHappened,
            'outcome' => $consultation->outcome->outcome,
            'reflection' => $consultation->outcome->reflection,
            'recorded_at' => $consultation->outcome->recordedAt->format(DATE_ATOM),
            'interpretation_lens' => $consultation->outcome->interpretationLens,
            'interpretation_summary' => $consultation->outcome->interpretationSummary,
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
            favorite: (bool) $row['is_favorite'],
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
     * @return list<ConsultationSummary>
     */
    public function findByPrimaryHexagramNumber(int $kingWenNumber, string $excludeId): array
    {
        return $this->findSummariesWhere(
            'primary_king_wen_number = :value',
            $kingWenNumber,
            $excludeId,
        );
    }

    /**
     * @return list<ConsultationSummary>
     */
    public function findByResultingHexagramNumber(int $kingWenNumber, string $excludeId): array
    {
        return $this->findSummariesWhere(
            'resulting_king_wen_number = :value',
            $kingWenNumber,
            $excludeId,
        );
    }

    /**
     * @param list<int> $positions
     *
     * @return list<ConsultationSummary>
     */
    public function findByChangingLinePositions(array $positions, string $excludeId): array
    {
        // Safe as an exact string comparison: changing_line_positions is always stored
        // ascending-by-position (Consultation::changingLinePositions() derives it from the
        // hexagram's own position-ordered lines), so two equal sets always encode identically.
        return $this->findSummariesWhere(
            'changing_line_positions = :value',
            json_encode($positions, JSON_THROW_ON_ERROR),
            $excludeId,
        );
    }

    /**
     * @return list<ConsultationSummary>
     */
    private function findSummariesWhere(string $condition, int|string $value, string $excludeId): array
    {
        $statement = $this->pdo->prepare(
            "SELECT id, question FROM consultations
             WHERE {$condition} AND id != :exclude_id
             ORDER BY created_at DESC, rowid DESC",
        );
        $statement->execute(['value' => $value, 'exclude_id' => $excludeId]);

        $summaries = [];
        while (is_array($row = $statement->fetch())) {
            $summaries[] = new ConsultationSummary((string) $row['id'], (string) $row['question']);
        }

        return $summaries;
    }

    public function existsById(string $id): bool
    {
        $statement = $this->pdo->prepare('SELECT 1 FROM consultations WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $id]);

        return $statement->fetchColumn() !== false;
    }

    public function saveImportBatch(array $consultations): void
    {
        $this->pdo->beginTransaction();

        try {
            foreach ($consultations as $consultation) {
                $this->upsertConsultation($consultation->withFollowUpTo(null));
                $this->replaceNotes($consultation);
                $this->replaceTags($consultation);
                $this->replaceOutcome($consultation);
            }

            foreach ($consultations as $consultation) {
                if ($consultation->followUpToConsultationId !== null) {
                    $this->updateFollowUpLink($consultation->id, $consultation->followUpToConsultationId);
                }
            }

            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();

            throw $e;
        }
    }

    private function updateFollowUpLink(string $id, ?string $followUpToConsultationId): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE consultations SET follow_up_to_consultation_id = :follow_up_to_consultation_id
             WHERE id = :id',
        );
        $statement->execute([
            'id' => $id,
            'follow_up_to_consultation_id' => $followUpToConsultationId,
        ]);
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
            'SELECT what_actually_happened, outcome, reflection, recorded_at,
                    interpretation_lens, interpretation_summary
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
            $row['interpretation_lens'] === null ? null : (string) $row['interpretation_lens'],
            $row['interpretation_summary'] === null ? null : (string) $row['interpretation_summary'],
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
