<?php

declare(strict_types=1);

namespace App\Tests;

use App\Core\Config;
use App\Core\Kernel;
use FastRoute\RouteCollector;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class KernelTest extends TestCase
{
    public function testConvertsAnUncaughtExceptionIntoACleanFiveHundredResponse(): void
    {
        $config = new Config(['app_env' => 'testing']);
        $routeDefinitions = static function (RouteCollector $r): void {
            $r->addRoute('GET', '/throws', static function (): never {
                throw new \RuntimeException('boom - this detail must never reach the client');
            });
        };

        $kernel = new Kernel($config, $routeDefinitions);
        $response = $kernel->handle(Request::create('/throws', 'GET'));

        self::assertSame(500, $response->getStatusCode());
        self::assertSame(
            ['error' => 'Internal Server Error'],
            json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR),
        );
        self::assertStringNotContainsString('boom', (string) $response->getContent());
    }

    public function testStillRoutesNormallyWhenNothingThrows(): void
    {
        $config = new Config(['app_env' => 'testing']);
        $routeDefinitions = static function (RouteCollector $r): void {
            $r->addRoute('GET', '/ok', static fn () => new \Symfony\Component\HttpFoundation\JsonResponse(['ok' => true]));
        };

        $kernel = new Kernel($config, $routeDefinitions);
        $response = $kernel->handle(Request::create('/ok', 'GET'));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(['ok' => true], json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR));
    }
}
