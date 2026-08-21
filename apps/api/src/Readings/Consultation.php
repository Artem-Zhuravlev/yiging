<?php

declare(strict_types=1);

namespace App\Readings;

use Yijing\Core\Hexagram;
use Yijing\Core\Line;

final readonly class Consultation
{
    private const MAX_QUESTION_LENGTH = 2000;
    private const MAX_CONTEXT_FIELD_LENGTH = 5000;

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
        public ?string $context = null,
        public ?string $whatHappenedBefore = null,
        public ?string $whatUserWantsToUnderstand = null,
        public ?string $backgroundInformation = null,
        public ?string $initialInterpretation = null,
        public ?ConsultationOutcome $outcome = null,
        public ?string $followUpToConsultationId = null,
        public bool $favorite = false,
    ) {
    }

    public static function create(
        string $id,
        string $question,
        CastingMethodName $method,
        Hexagram $primaryHexagram,
        \DateTimeImmutable $createdAt,
        ?string $context = null,
        ?string $whatHappenedBefore = null,
        ?string $whatUserWantsToUnderstand = null,
        ?string $backgroundInformation = null,
        ?string $initialInterpretation = null,
        ?string $followUpToConsultationId = null,
    ): self {
        // Note: a brand-new consultation never has an outcome yet, so create() intentionally
        // has no outcome parameter — one is only ever attached later via withUpdatedOutcome().
        // followUpToConsultationId's existence is validated by the controller (repository
        // access required); self-reference is structurally impossible here since $id is new.
        if (trim($question) === '') {
            throw new \InvalidArgumentException('A consultation question must not be empty.');
        }

        if (mb_strlen($question) > self::MAX_QUESTION_LENGTH) {
            throw new \InvalidArgumentException(
                sprintf('A consultation question must not exceed %d characters.', self::MAX_QUESTION_LENGTH),
            );
        }

        self::validateContextField($context, 'context');
        self::validateContextField($whatHappenedBefore, 'whatHappenedBefore');
        self::validateContextField($whatUserWantsToUnderstand, 'whatUserWantsToUnderstand');
        self::validateContextField($backgroundInformation, 'backgroundInformation');
        self::validateContextField($initialInterpretation, 'initialInterpretation');

        return new self(
            $id,
            $question,
            $method,
            $primaryHexagram,
            $primaryHexagram->getResultingHexagram(),
            $createdAt,
            context: $context,
            whatHappenedBefore: $whatHappenedBefore,
            whatUserWantsToUnderstand: $whatUserWantsToUnderstand,
            backgroundInformation: $backgroundInformation,
            initialInterpretation: $initialInterpretation,
            followUpToConsultationId: $followUpToConsultationId,
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
        ?string $context = null,
        ?string $whatHappenedBefore = null,
        ?string $whatUserWantsToUnderstand = null,
        ?string $backgroundInformation = null,
        ?string $initialInterpretation = null,
        ?ConsultationOutcome $outcome = null,
        ?string $followUpToConsultationId = null,
        bool $favorite = false,
    ): self {
        return new self(
            $id,
            $question,
            $method,
            $primaryHexagram,
            $resultingHexagram,
            $createdAt,
            $notes,
            $tags,
            $context,
            $whatHappenedBefore,
            $whatUserWantsToUnderstand,
            $backgroundInformation,
            $initialInterpretation,
            $outcome,
            $followUpToConsultationId,
            $favorite,
        );
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
            $this->context,
            $this->whatHappenedBefore,
            $this->whatUserWantsToUnderstand,
            $this->backgroundInformation,
            $this->initialInterpretation,
            $this->outcome,
            $this->followUpToConsultationId,
            $this->favorite,
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
            $this->context,
            $this->whatHappenedBefore,
            $this->whatUserWantsToUnderstand,
            $this->backgroundInformation,
            $this->initialInterpretation,
            $this->outcome,
            $this->followUpToConsultationId,
            $this->favorite,
        );
    }

    public function withUpdatedContext(
        ?string $context,
        ?string $whatHappenedBefore,
        ?string $whatUserWantsToUnderstand,
        ?string $backgroundInformation,
        ?string $initialInterpretation,
    ): self {
        self::validateContextField($context, 'context');
        self::validateContextField($whatHappenedBefore, 'whatHappenedBefore');
        self::validateContextField($whatUserWantsToUnderstand, 'whatUserWantsToUnderstand');
        self::validateContextField($backgroundInformation, 'backgroundInformation');
        self::validateContextField($initialInterpretation, 'initialInterpretation');

        return new self(
            $this->id,
            $this->question,
            $this->method,
            $this->primaryHexagram,
            $this->resultingHexagram,
            $this->createdAt,
            $this->notes,
            $this->tags,
            $context,
            $whatHappenedBefore,
            $whatUserWantsToUnderstand,
            $backgroundInformation,
            $initialInterpretation,
            $this->outcome,
            $this->followUpToConsultationId,
            $this->favorite,
        );
    }

    public function withUpdatedOutcome(
        ?string $whatActuallyHappened,
        ?string $outcome,
        ?string $reflection,
        \DateTimeImmutable $recordedAt,
        ?string $interpretationLens = null,
        ?string $interpretationSummary = null,
    ): self {
        return new self(
            $this->id,
            $this->question,
            $this->method,
            $this->primaryHexagram,
            $this->resultingHexagram,
            $this->createdAt,
            $this->notes,
            $this->tags,
            $this->context,
            $this->whatHappenedBefore,
            $this->whatUserWantsToUnderstand,
            $this->backgroundInformation,
            $this->initialInterpretation,
            new ConsultationOutcome(
                $whatActuallyHappened,
                $outcome,
                $reflection,
                $recordedAt,
                $interpretationLens,
                $interpretationSummary,
            ),
            $this->followUpToConsultationId,
            $this->favorite,
        );
    }

    public function withFollowUpTo(?string $followUpToConsultationId): self
    {
        if ($followUpToConsultationId === $this->id) {
            throw new \InvalidArgumentException('A consultation cannot be a follow-up to itself.');
        }

        return new self(
            $this->id,
            $this->question,
            $this->method,
            $this->primaryHexagram,
            $this->resultingHexagram,
            $this->createdAt,
            $this->notes,
            $this->tags,
            $this->context,
            $this->whatHappenedBefore,
            $this->whatUserWantsToUnderstand,
            $this->backgroundInformation,
            $this->initialInterpretation,
            $this->outcome,
            $followUpToConsultationId,
            $this->favorite,
        );
    }

    public function withFavorite(bool $favorite): self
    {
        return new self(
            $this->id,
            $this->question,
            $this->method,
            $this->primaryHexagram,
            $this->resultingHexagram,
            $this->createdAt,
            $this->notes,
            $this->tags,
            $this->context,
            $this->whatHappenedBefore,
            $this->whatUserWantsToUnderstand,
            $this->backgroundInformation,
            $this->initialInterpretation,
            $this->outcome,
            $this->followUpToConsultationId,
            $favorite,
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

    private static function validateContextField(?string $value, string $fieldName): void
    {
        if ($value !== null && mb_strlen($value) > self::MAX_CONTEXT_FIELD_LENGTH) {
            throw new \InvalidArgumentException(
                sprintf('"%s" must not exceed %d characters.', $fieldName, self::MAX_CONTEXT_FIELD_LENGTH),
            );
        }
    }
}
