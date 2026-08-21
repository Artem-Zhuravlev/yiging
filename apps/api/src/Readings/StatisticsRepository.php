<?php

declare(strict_types=1);

namespace App\Readings;

interface StatisticsRepository
{
    public function compute(): ConsultationStatistics;
}
