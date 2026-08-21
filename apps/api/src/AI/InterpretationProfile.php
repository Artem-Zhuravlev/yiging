<?php

declare(strict_types=1);

namespace App\AI;

/**
 * A single, global standing preference for how interpretations/follow-up answers should be
 * written (SPEC-035) — this app has no accounts, so there is exactly one profile, not one per
 * user.
 */
final readonly class InterpretationProfile
{
    private const MAX_NOTES_LENGTH = 1000;

    public function __construct(
        public Tone $tone = Tone::Neutral,
        public ResponseLength $length = ResponseLength::Standard,
        public ?string $notes = null,
    ) {
        if ($notes !== null && mb_strlen($notes) > self::MAX_NOTES_LENGTH) {
            throw new \InvalidArgumentException(
                sprintf('"notes" must not exceed %d characters.', self::MAX_NOTES_LENGTH),
            );
        }
    }

    public static function default(): self
    {
        return new self();
    }

    public function isDefault(): bool
    {
        return $this->tone === Tone::Neutral && $this->length === ResponseLength::Standard && $this->notes === null;
    }
}
