<?php

declare(strict_types=1);

namespace App\Casting;

use Yijing\Core\Hexagram;

interface DivinationMethod
{
    public function cast(): Hexagram;
}
