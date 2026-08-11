<?php

declare(strict_types=1);

use App\Core\HealthController;
use FastRoute\RouteCollector;

return static function (RouteCollector $r): void {
    $r->addRoute('GET', '/api/health', [HealthController::class, '__invoke']);
};
