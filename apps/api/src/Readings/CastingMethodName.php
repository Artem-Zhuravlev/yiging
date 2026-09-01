<?php

declare(strict_types=1);

namespace App\Readings;

enum CastingMethodName: string
{
    case ThreeCoins = 'three_coins';
    case Yarrow = 'yarrow';
    case Manual = 'manual';
    case Random = 'random';
}
