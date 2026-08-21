<?php

declare(strict_types=1);

namespace App\Readings;

use App\Core\Config;
use App\Core\Database;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class StatisticsController
{
    private readonly StatisticsRepository $repository;

    public function __construct(Config $config)
    {
        $this->repository = new SqliteStatisticsRepository(Database::connect($config));
    }

    public function index(): Response
    {
        $statistics = $this->repository->compute();

        return new JsonResponse([
            'totalConsultations' => $statistics->totalConsultations,
            'hexagramFrequency' => array_map(
                static fn (HexagramFrequency $f): array => [
                    'kingWenNumber' => $f->kingWenNumber,
                    'chineseName' => $f->chineseName,
                    'pinyin' => $f->pinyin,
                    'count' => $f->count,
                ],
                $statistics->hexagramFrequency,
            ),
            'yinYangRatio' => ['yin' => $statistics->yinLineCount, 'yang' => $statistics->yangLineCount],
            'tagFrequency' => array_map(
                static fn (TagFrequency $f): array => ['name' => $f->name, 'count' => $f->count],
                $statistics->tagFrequency,
            ),
        ]);
    }
}
