<?php

declare(strict_types=1);

namespace App\Hexagrams;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Yijing\Core\Data\HexagramCatalog;
use Yijing\Core\Hexagram;
use Yijing\Core\Line;
use Yijing\Core\Trigram;

// No constructor: this controller has no database access to configure. Kernel::invoke()
// constructs every controller as `new $class($config)`; PHP silently ignores the extra
// argument when a class declares no __construct(), so this stays compatible without a
// dead, unused Config parameter.
final class HexagramController
{
    public function index(): Response
    {
        $hexagrams = array_map(
            fn (int $kingWenNumber): array => $this->toJson(Hexagram::fromKingWenNumber($kingWenNumber)),
            array_keys(HexagramCatalog::all()),
        );

        return new JsonResponse($hexagrams);
    }

    /**
     * @param array<string, string> $vars
     */
    public function show(Request $request, array $vars): Response
    {
        try {
            $hexagram = Hexagram::fromKingWenNumber((int) $vars['id']);
        } catch (\InvalidArgumentException) {
            return new JsonResponse(['error' => 'Not Found'], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse($this->toJson($hexagram));
    }

    /**
     * @return array<string, mixed>
     */
    private function toJson(Hexagram $hexagram): array
    {
        return [
            'kingWenNumber' => $hexagram->kingWenNumber,
            'chineseName' => $hexagram->chineseName,
            'pinyin' => $hexagram->pinyin,
            'lines' => array_map(
                static fn (Line $line): array => [
                    'position' => $line->position,
                    'polarity' => $line->isYang() ? 'yang' : 'yin',
                ],
                $hexagram->lines,
            ),
            'upperTrigram' => $this->trigramToJson($hexagram->getUpperTrigram()),
            'lowerTrigram' => $this->trigramToJson($hexagram->getLowerTrigram()),
            'judgment' => $hexagram->judgment,
            'image' => $hexagram->image,
            'lineStatements' => $hexagram->lineStatements,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function trigramToJson(Trigram $trigram): array
    {
        return [
            'id' => $trigram->id->name,
            'name' => $trigram->name(),
            'chineseName' => $trigram->chineseName(),
            'pinyin' => $trigram->pinyin(),
            'symbol' => $trigram->symbol(),
        ];
    }
}
