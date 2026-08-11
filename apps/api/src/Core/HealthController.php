<?php

declare(strict_types=1);

namespace App\Core;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

final class HealthController
{
    public function __construct(private readonly Config $config)
    {
    }

    public function __invoke(Request $request): JsonResponse
    {
        return new JsonResponse([
            'status' => 'ok',
            'env' => $this->config->string('app_env'),
        ]);
    }
}
