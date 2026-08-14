<?php

declare(strict_types=1);

namespace App\AI;

use App\Core\Config;
use App\Core\Database;
use App\Readings\ConsultationRepository;
use App\Readings\SqliteConsultationRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class InterpretationController
{
    private readonly ConsultationRepository $repository;
    private readonly InterpretationContextBuilder $contextBuilder;
    private readonly InterpretationProvider $provider;

    public function __construct(Config $config)
    {
        $this->repository = new SqliteConsultationRepository(Database::connect($config));
        $this->contextBuilder = new InterpretationContextBuilder();
        $this->provider = new MockInterpretationProvider();
    }

    /**
     * @param array<string, string> $vars
     */
    public function create(Request $request, array $vars): Response
    {
        $consultation = $this->repository->findById($vars['id']);

        if ($consultation === null) {
            return new JsonResponse(['error' => 'Not Found'], Response::HTTP_NOT_FOUND);
        }

        $context = $this->contextBuilder->build($consultation);
        $interpretation = $this->provider->interpret($context);

        return new JsonResponse($this->toJson($interpretation));
    }

    /**
     * @return array<string, mixed>
     */
    private function toJson(Interpretation $interpretation): array
    {
        return [
            'summary' => $interpretation->summary,
            'coreTheme' => $interpretation->coreTheme,
            'situation' => $interpretation->situation,
            'changingLineMeaning' => $interpretation->changingLineMeaning,
            'transition' => $interpretation->transition,
            'practicalReflection' => $interpretation->practicalReflection,
            'uncertainties' => $interpretation->uncertainties,
            'sourceReferences' => $interpretation->sourceReferences,
        ];
    }
}
