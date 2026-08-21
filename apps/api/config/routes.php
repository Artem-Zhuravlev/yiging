<?php

declare(strict_types=1);

use App\AI\InterpretationController;
use App\AI\InterpretationProfileController;
use App\Core\HealthController;
use App\Hexagrams\HexagramController;
use App\Journal\JournalController;
use App\Readings\ConsultationController;
use App\Readings\StatisticsController;
use App\Trigrams\TrigramController;
use FastRoute\RouteCollector;

return static function (RouteCollector $r): void {
    $r->addRoute('GET', '/api/health', [HealthController::class, '__invoke']);

    $r->addRoute('POST', '/api/consultations', [ConsultationController::class, 'create']);
    $r->addRoute('POST', '/api/consultations/import', [ConsultationController::class, 'import']);
    $r->addRoute('GET', '/api/consultations', [ConsultationController::class, 'index']);
    $r->addRoute('GET', '/api/consultations/{id}', [ConsultationController::class, 'show']);
    $r->addRoute('PATCH', '/api/consultations/{id}', [ConsultationController::class, 'update']);

    $r->addRoute('GET', '/api/hexagrams', [HexagramController::class, 'index']);
    $r->addRoute('GET', '/api/hexagrams/from-lines', [HexagramController::class, 'fromLines']);
    $r->addRoute('GET', '/api/hexagrams/compare', [HexagramController::class, 'compare']);
    $r->addRoute('PUT', '/api/hexagrams/{id}/favorite', [HexagramController::class, 'markFavorite']);
    $r->addRoute('DELETE', '/api/hexagrams/{id}/favorite', [HexagramController::class, 'unmarkFavorite']);
    $r->addRoute('GET', '/api/hexagrams/{id}', [HexagramController::class, 'show']);

    $r->addRoute('GET', '/api/trigrams', [TrigramController::class, 'index']);

    $r->addRoute('GET', '/api/statistics', [StatisticsController::class, 'index']);

    $r->addRoute('POST', '/api/journal', [JournalController::class, 'create']);
    $r->addRoute('GET', '/api/journal', [JournalController::class, 'index']);

    $r->addRoute('POST', '/api/interpretations/{id}', [InterpretationController::class, 'create']);
    $r->addRoute('POST', '/api/interpretations/{id}/followup', [InterpretationController::class, 'followUp']);

    $r->addRoute('GET', '/api/interpretation-profile', [InterpretationProfileController::class, 'show']);
    $r->addRoute('PATCH', '/api/interpretation-profile', [InterpretationProfileController::class, 'update']);
};
