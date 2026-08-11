<?php

declare(strict_types=1);

namespace App\Tests;

use App\Core\Config;
use App\Core\Kernel;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class HealthEndpointTest extends TestCase
{
    public function testHealthEndpointReturnsOk(): void
    {
        $apiRoot = dirname(__DIR__);
        $config = Config::fromEnv($apiRoot);
        $routeDefinitions = require $apiRoot . '/config/routes.php';

        $kernel = new Kernel($config, $routeDefinitions);
        $response = $kernel->handle(Request::create('/api/health', 'GET'));

        self::assertSame(200, $response->getStatusCode());
        self::assertJsonStringEqualsJsonString(
            json_encode(['status' => 'ok', 'env' => $config->string('app_env')], JSON_THROW_ON_ERROR),
            (string) $response->getContent(),
        );
    }

    public function testUnknownRouteReturnsNotFound(): void
    {
        $apiRoot = dirname(__DIR__);
        $config = Config::fromEnv($apiRoot);
        $routeDefinitions = require $apiRoot . '/config/routes.php';

        $kernel = new Kernel($config, $routeDefinitions);
        $response = $kernel->handle(Request::create('/api/does-not-exist', 'GET'));

        self::assertSame(404, $response->getStatusCode());
    }
}
