<?php

declare(strict_types=1);

namespace App\Readings;

use Yijing\Core\Hexagram;
use Yijing\Core\Line;

final readonly class Consultation
{
    private const MAX_QUESTION_LENGTH = 2000;

    /**
     * @param list<ConsultationNote> $notes
     * @param list<string> $tags
     */
    private function __construct(
        public string $id,
        public string $question,
        public CastingMethodName $method,
        public Hexagram $primaryHexagram,
        public Hexagram $resultingHexagram,
        public \DateTimeImmutable $createdAt,
        public array $notes = [],
        public array $tags = [],
    ) {
    }

    public static function create(
        string $id,
        string $question,
        CastingMethodName $method,
        Hexagram $primaryHexagram,
        \DateTimeImmutable $createdAt,
    ): self {
        if (trim($question) === '') {
            throw new \InvalidArgumentException('A consultation question must not be empty.');
        }

        if (mb_strlen($question) > self::MAX_QUESTION_LENGTH) {
            throw new \InvalidArgumentException(
                sprintf('A consultation question must not exceed %d characters.', self::MAX_QUESTION_LENGTH),
            );
        }

        return new self(
            $id,
            $question,
            $method,
            $primaryHexagram,
            $primaryHexagram->getResultingHexagram(),
            $createdAt,
        );
    }

    /**
     * Rebuilds a Consultation from already-validated, already-persisted state (e.g. a
     * repository read). Skips create()'s validation since that state was validated once,
     * at the original create() call, and is trusted here.
     *
     * @param list<ConsultationNote> $notes
     * @param list<string> $tags
     */
    public static function reconstitute(
        string $id,
        string $question,
        CastingMethodName $method,
        Hexagram $primaryHexagram,
        Hexagram $resultingHexagram,
        \DateTimeImmutable $createdAt,
        array $notes,
        array $tags,
    ): self {
        return new self($id, $question, $method, $primaryHexagram, $resultingHexagram, $createdAt, $notes, $tags);
    }

    public function withAddedNote(ConsultationNote $note): self
    {
        return new self(
            $this->id,
            $this->question,
            $this->method,
            $this->primaryHexagram,
            $this->resultingHexagram,
            $this->createdAt,
            [...$this->notes, $note],
            $this->tags,
        );
    }

    public function withAddedTag(string $tag): self
    {
        if (in_array($tag, $this->tags, true)) {
            return $this;
        }

        return new self(
            $this->id,
            $this->question,
            $this->method,
            $this->primaryHexagram,
            $this->resultingHexagram,
            $this->createdAt,
            $this->notes,
            [...$this->tags, $tag],
        );
    }

    /**
     * @return list<int>
     */
    public function changingLinePositions(): array
    {
        $changing = array_filter(
            $this->primaryHexagram->lines,
            static fn (Line $line): bool => $line->changing,
        );

        return array_values(array_map(
            static fn (Line $line): int => $line->position,
            $changing,
        ));
    }
}
