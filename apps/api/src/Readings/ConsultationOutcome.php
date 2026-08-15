<?php

declare(strict_types=1);

namespace App\Readings;

final readonly class ConsultationOutcome
{
    private const MAX_FIELD_LENGTH = 5000;

    public function __construct(
        public ?string $whatActuallyHappened,
        public ?string $outcome,
        public ?string $reflection,
        public \DateTimeImmutable $recordedAt,
    ) {
        self::validate($whatActuallyHappened, 'whatActuallyHappened');
        self::validate($outcome, 'outcome');
        self::validate($reflection, 'reflection');
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
