<?php

declare(strict_types=1);

namespace App\Core;

use FastRoute\Dispatcher;
use FastRoute\RouteCollector;

use function FastRoute\simpleDispatcher;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class Kernel
{
    /**
     * @param callable(RouteCollector): void $routeDefinitions
     */
    public function __construct(
        private readonly Config $config,
        private readonly mixed $routeDefinitions,
    ) {
    }

    public function handle(Request $request): Response
    {
        try {
            $dispatcher = simpleDispatcher($this->routeDefinitions);

            $routeInfo = $dispatcher->dispatch(
                $request->getMethod(),
                rawurldecode($request->getPathInfo()),
            );

            return match ($routeInfo[0]) {
                Dispatcher::NOT_FOUND => new JsonResponse(['error' => 'Not Found'], Response::HTTP_NOT_FOUND),
                Dispatcher::METHOD_NOT_ALLOWED => new JsonResponse(
                    ['error' => 'Method Not Allowed'],
                    Response::HTTP_METHOD_NOT_ALLOWED,
                ),
                Dispatcher::FOUND => $this->invoke($routeInfo[1], $routeInfo[2], $request),
                default => new JsonResponse(
                    ['error' => 'Internal Server Error'],
                    Response::HTTP_INTERNAL_SERVER_ERROR,
                ),
            };
        } catch (\Throwable $e) {
            // Never let a raw stack trace (which can include file paths, config values, etc.)
            // reach the client - log the real detail server-side, return a clean generic
            // response. Covers failures anywhere in routing, controller construction, or
            // handling - not just this project's own code.
            error_log((string) $e);

            return new JsonResponse(['error' => 'Internal Server Error'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @param array<string, string> $vars
     */
    private function invoke(mixed $handler, array $vars, Request $request): Response
    {
        if (is_array($handler) && is_string($handler[0])) {
            $handler = [new $handler[0]($this->config), $handler[1]];
        }

        /** @var Response */
        return $handler($request, $vars);
    }
}
