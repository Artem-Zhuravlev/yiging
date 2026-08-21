<?php

declare(strict_types=1);

namespace App\AI;

use App\Core\Config;
use App\Core\Database;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class InterpretationProfileController
{
    private readonly InterpretationProfileRepository $repository;

    public function __construct(Config $config)
    {
        $this->repository = new SqliteInterpretationProfileRepository(Database::connect($config));
    }

    public function show(): Response
    {
        return new JsonResponse($this->toJson($this->repository->get()));
    }

    public function update(Request $request): Response
    {
        try {
            $body = $this->decodeJsonBody($request);
        } catch (\JsonException) {
            return new JsonResponse(['error' => 'Malformed JSON body.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $current = $this->repository->get();

        $tone = $current->tone;
        if (array_key_exists('tone', $body)) {
            $tone = is_string($body['tone']) ? Tone::tryFrom($body['tone']) : null;
            if ($tone === null) {
                return new JsonResponse(
                    ['error' => "Invalid 'tone'. Expected one of: neutral, formal, casual, poetic."],
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                );
            }
        }

        $length = $current->length;
        if (array_key_exists('length', $body)) {
            $length = is_string($body['length']) ? ResponseLength::tryFrom($body['length']) : null;
            if ($length === null) {
                return new JsonResponse(
                    ['error' => "Invalid 'length'. Expected one of: standard, brief, detailed."],
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                );
            }
        }

        $notes = $current->notes;
        if (array_key_exists('notes', $body)) {
            if ($body['notes'] !== null && !is_string($body['notes'])) {
                return new JsonResponse(
                    ['error' => '"notes" must be a string or null.'],
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                );
            }
            $notes = $body['notes'];
        }

        try {
            $profile = new InterpretationProfile($tone, $length, $notes);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->repository->save($profile);

        return new JsonResponse($this->toJson($profile));
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJsonBody(Request $request): array
    {
        $content = $request->getContent();

        if ($content === '') {
            return [];
        }

        $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($decoded)) {
            throw new \JsonException('Request body must be a JSON object.');
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    /**
     * @return array<string, mixed>
     */
    private function toJson(InterpretationProfile $profile): array
    {
        return [
            'tone' => $profile->tone->value,
            'length' => $profile->length->value,
            'notes' => $profile->notes,
        ];
    }
}
