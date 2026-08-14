<?php

declare(strict_types=1);

namespace App\Readings;

interface ConsultationIdGenerator
{
    public function generate(): string;
}
