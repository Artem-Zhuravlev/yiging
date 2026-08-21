<?php

declare(strict_types=1);

namespace App\Readings;

final readonly class ConsultationOutcome
{
    private const MAX_FIELD_LENGTH = 5000;

    /**
     * Plain validated strings, not App\AI\InterpretationLens - App\Readings never imports from
     * App\AI (matches this codebase's established no-cross-module-domain-imports convention,
     * e.g. App\Journal's self-contained Clock rather than reusing App\Readings's) — see
     * SPEC-036's "Out of scope".
     */
    private const VALID_LENSES = ['general', 'psychological', 'practical', 'symbolic'];

    public function __construct(
        public ?string $whatActuallyHappened,
        public ?string $outcome,
        public ?string $reflection,
        public \DateTimeImmutable $recordedAt,
        public ?string $interpretationLens = null,
        public ?string $interpretationSummary = null,
    ) {
        self::validate($whatActuallyHappened, 'whatActuallyHappened');
        self::validate($outcome, 'outcome');
        self::validate($reflection, 'reflection');
        self::validate($interpretationSummary, 'interpretationSummary');

        if ($interpretationLens !== null && !in_array($interpretationLens, self::VALID_LENSES, true)) {
            throw new \InvalidArgumentException(
                '"interpretationLens" must be one of: ' . implode(', ', self::VALID_LENSES) . '.',
            );
        }
    }

    private static function validate(?string $value, string $fieldName): void
    {
        if ($value !== null && mb_strlen($value) > self::MAX_FIELD_LENGTH) {
            throw new \InvalidArgumentException(
                sprintf('"%s" must not exceed %d characters.', $fieldName, self::MAX_FIELD_LENGTH),
            );
        }
    }
}
