<?php

declare(strict_types=1);

namespace App\AI;

enum ResponseLength: string
{
    case Standard = 'standard';
    case Brief = 'brief';
    case Detailed = 'detailed';
}
