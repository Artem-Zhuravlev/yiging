<?php

declare(strict_types=1);

namespace Yijing\Core;

/**
 * One piece of classical text a cast tells you to read: a hexagram's Judgment, or a specific
 * line statement. `governing` marks the principal text when a rule points at more than one
 * (SPEC-052).
 */
final readonly class CastReadingRef
{
    /**
     * @param 'primary'|'resulting' $hexagram
     * @param 'judgment'|'line'     $kind
     * @param int|null              $position 1-6, set only when $kind === 'line'
     */
    public function __construct(
        public string $hexagram,
        public string $kind,
        public ?int $position,
        public bool $governing,
    ) {
    }

    /**
     * @param 'primary'|'resulting' $hexagram
     */
    public static function judgment(string $hexagram, bool $governing): self
    {
        return new self($hexagram, 'judgment', null, $governing);
    }

    /**
     * @param 'primary'|'resulting' $hexagram
     */
    public static function line(string $hexagram, int $position, bool $governing): self
    {
        return new self($hexagram, 'line', $position, $governing);
    }

    /**
     * @return array{hexagram: string, kind: string, position: int|null, governing: bool}
     */
    public function toArray(): array
    {
        return [
            'hexagram' => $this->hexagram,
            'kind' => $this->kind,
            'position' => $this->position,
            'governing' => $this->governing,
        ];
    }
}
