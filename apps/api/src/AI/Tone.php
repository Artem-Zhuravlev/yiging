<?php

declare(strict_types=1);

namespace App\AI;

enum Tone: string
{
    case Neutral = 'neutral';
    case Formal = 'formal';
    case Casual = 'casual';
    case Poetic = 'poetic';
}
