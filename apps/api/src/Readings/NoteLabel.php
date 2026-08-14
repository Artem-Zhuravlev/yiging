<?php

declare(strict_types=1);

namespace App\Readings;

enum NoteLabel: string
{
    case Before = 'before';
    case After = 'after';
    case Later = 'later';
}
