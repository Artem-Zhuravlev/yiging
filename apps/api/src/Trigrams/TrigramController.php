<?php

declare(strict_types=1);

namespace App\Trigrams;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Yijing\Core\Data\TrigramCatalog;
use Yijing\Core\Line;
use Yijing\Core\LinePolarity;
use Yijing\Core\Trigram;
use Yijing\Core\TrigramId;

// No constructor: this controller has no database access to configure. Kernel::invoke()
// constructs every controller as `new $class($config)`; PHP silently ignores the extra
// argument when a class declares no __construct(), so this stays compatible without a
// dead, unused Config parameter.
final class TrigramController
{
    public function index(): Response
    {
        $trigrams = array_map(
            fn (TrigramId $id): array => $this->toJson($this->trigramFor($id)),
            TrigramId::cases(),
        );

        return new JsonResponse($trigrams);
    }

    private function trigramFor(TrigramId $id): Trigram
    {
        $pattern = TrigramCatalog::patternFor($id);

        $lines = array_map(
            static fn (int $index, string $char): Line => new Line(
                $index + 1,
                $char === '1' ? LinePolarity::Yang : LinePolarity::Yin,
                false,
            ),
            array_keys(str_split($pattern)),
            str_split($pattern),
        );

        return Trigram::fromLines($lines);
    }

    /**
     * @return array<string, mixed>
     */
    private function toJson(Trigram $trigram): array
    {
        return [
            'id' => $trigram->id->name,
            'name' => $trigram->name(),
            'chineseName' => $trigram->chineseName(),
            'pinyin' => $trigram->pinyin(),
            'symbol' => $trigram->symbol(),
            'element' => $trigram->element(),
            'familyMember' => $trigram->familyMember(),
            'direction' => $trigram->direction(),
            'image' => $trigram->image(),
        ];
    }
}
