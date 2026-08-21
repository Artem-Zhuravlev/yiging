<?php

declare(strict_types=1);

namespace App\Readings;

use PDO;
use Yijing\Core\Hexagram;
use Yijing\Core\Line;
use Yijing\Core\LinePolarity;

final class SqliteStatisticsRepository implements StatisticsRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function compute(): ConsultationStatistics
    {
        $countStatement = $this->pdo->prepare('SELECT COUNT(*) FROM consultations');
        $countStatement->execute();
        $total = (int) $countStatement->fetchColumn();

        [$yin, $yang] = $this->computeYinYangCounts();

        return new ConsultationStatistics(
            $total,
            $this->computeHexagramFrequency(),
            $yin,
            $yang,
            $this->computeTagFrequency(),
        );
    }

    /**
     * @return list<HexagramFrequency>
     */
    private function computeHexagramFrequency(): array
    {
        $statement = $this->pdo->prepare(
            'SELECT primary_king_wen_number, COUNT(*) AS cnt FROM consultations
             GROUP BY primary_king_wen_number
             ORDER BY cnt DESC, primary_king_wen_number ASC',
        );
        $statement->execute();

        $result = [];
        while (is_array($row = $statement->fetch())) {
            $hexagram = Hexagram::fromKingWenNumber((int) $row['primary_king_wen_number']);
            $result[] = new HexagramFrequency(
                $hexagram->kingWenNumber,
                $hexagram->chineseName,
                $hexagram->pinyin,
                (int) $row['cnt'],
            );
        }

        return $result;
    }

    /**
     * @return array{0: int, 1: int} [yinLineCount, yangLineCount]
     */
    private function computeYinYangCounts(): array
    {
        $statement = $this->pdo->prepare('SELECT primary_king_wen_number FROM consultations');
        $statement->execute();

        $yin = 0;
        $yang = 0;

        while (is_array($row = $statement->fetch())) {
            $hexagram = Hexagram::fromKingWenNumber((int) $row['primary_king_wen_number']);

            foreach ($hexagram->lines as $line) {
                /** @var Line $line */
                if ($line->polarity === LinePolarity::Yin) {
                    ++$yin;
                } else {
                    ++$yang;
                }
            }
        }

        return [$yin, $yang];
    }

    /**
     * @return list<TagFrequency>
     */
    private function computeTagFrequency(): array
    {
        $statement = $this->pdo->prepare(
            'SELECT t.name, COUNT(*) AS cnt FROM tags t
             INNER JOIN consultation_tags ct ON ct.tag_id = t.id
             GROUP BY t.name
             ORDER BY cnt DESC, t.name ASC',
        );
        $statement->execute();

        $result = [];
        while (is_array($row = $statement->fetch())) {
            $result[] = new TagFrequency((string) $row['name'], (int) $row['cnt']);
        }

        return $result;
    }
}
