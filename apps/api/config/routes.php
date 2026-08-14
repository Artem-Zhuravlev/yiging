<?php

declare(strict_types=1);

use App\Core\HealthController;
use App\Readings\ConsultationController;
use FastRoute\RouteCollector;

return static function (RouteCollector $r): void {
    $r->addRoute('GET', '/api/health', [HealthController::class, '__invoke']);

    $r->addRoute('POST', '/api/consultations', [ConsultationController::class, 'create']);
    $r->addRoute('GET', '/api/consultations', [ConsultationController::class, 'index']);
    $r->addRoute('GET', '/api/consultations/{id}', [ConsultationController::class, 'show']);
};
