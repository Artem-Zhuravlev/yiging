<?php

declare(strict_types=1);

namespace Yijing\Core;

enum LinePolarity
{
    case Yin;
    case Yang;

    public function opposite(): self
    {
        return match ($this) {
            self::Yin => self::Yang,
            self::Yang => self::Yin,
        };
    }
}
