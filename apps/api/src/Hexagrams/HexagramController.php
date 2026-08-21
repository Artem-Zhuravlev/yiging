<?php

declare(strict_types=1);

namespace App\Hexagrams;

use App\Core\Config;
use App\Core\Database;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Yijing\Core\Data\HexagramCatalog;
use Yijing\Core\Hexagram;
use Yijing\Core\HexagramComparator;
use Yijing\Core\Line;
use Yijing\Core\LineComparison;
use Yijing\Core\LinePolarity;
use Yijing\Core\Trigram;
use Yijing\Core\YijingRelations;

final class HexagramController
{
    private readonly HexagramFavoritesRepository $favorites;

    public function __construct(Config $config)
    {
        $this->favorites = new SqliteHexagramFavoritesRepository(Database::connect($config));
    }

    public function index(): Response
    {
        $favoriteNumbers = $this->favorites->allFavoriteNumbers();

        $hexagrams = array_map(
            fn (int $kingWenNumber): array => $this->toJson(
                Hexagram::fromKingWenNumber($kingWenNumber),
                in_array($kingWenNumber, $favoriteNumbers, true),
            ),
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

        return new JsonResponse($this->toJson($hexagram, $this->favorites->isFavorite($hexagram->kingWenNumber)));
    }

    /**
     * @param array<string, string> $vars
     */
    public function markFavorite(Request $request, array $vars): Response
    {
        try {
            $hexagram = Hexagram::fromKingWenNumber((int) $vars['id']);
        } catch (\InvalidArgumentException) {
            return new JsonResponse(['error' => 'Not Found'], Response::HTTP_NOT_FOUND);
        }

        $this->favorites->add($hexagram->kingWenNumber);

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    /**
     * @param array<string, string> $vars
     */
    public function unmarkFavorite(Request $request, array $vars): Response
    {
        try {
            $hexagram = Hexagram::fromKingWenNumber((int) $vars['id']);
        } catch (\InvalidArgumentException) {
            return new JsonResponse(['error' => 'Not Found'], Response::HTTP_NOT_FOUND);
        }

        $this->favorites->remove($hexagram->kingWenNumber);

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    public function fromLines(Request $request): Response
    {
        try {
            $lines = $this->parseLinesFromQuery($request->query->get('lines'));
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return new JsonResponse($this->toJson(Hexagram::fromLines($lines), false));
    }

    public function compare(Request $request): Response
    {
        try {
            $aNumber = $this->parseKingWenNumberFromQuery($request->query->get('a'), 'a');
            $bNumber = $this->parseKingWenNumberFromQuery($request->query->get('b'), 'b');
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $a = Hexagram::fromKingWenNumber($aNumber);
            $b = Hexagram::fromKingWenNumber($bNumber);
        } catch (\InvalidArgumentException) {
            return new JsonResponse(['error' => 'Not Found'], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse([
            'a' => $this->toJson($a, false),
            'b' => $this->toJson($b, false),
            'lineComparisons' => array_map(
                static fn (LineComparison $c): array => [
                    'position' => $c->position,
                    'aPolarity' => $c->aPolarity === LinePolarity::Yang ? 'yang' : 'yin',
                    'bPolarity' => $c->bPolarity === LinePolarity::Yang ? 'yang' : 'yin',
                    'changed' => $c->changed,
                ],
                HexagramComparator::compareLines($a, $b),
            ),
            'upperTrigramDiffers' => $a->getUpperTrigram()->id !== $b->getUpperTrigram()->id,
            'lowerTrigramDiffers' => $a->getLowerTrigram()->id !== $b->getLowerTrigram()->id,
        ]);
    }

    private function parseKingWenNumberFromQuery(mixed $value, string $paramName): int
    {
        if (!is_string($value) || !ctype_digit($value)) {
            throw new \InvalidArgumentException(
                sprintf('"%s" must be a numeric King Wen number.', $paramName),
            );
        }

        return (int) $value;
    }

    /**
     * @return list<Line>
     */
    private function parseLinesFromQuery(mixed $lines): array
    {
        if (!is_string($lines) || $lines === '') {
            throw new \InvalidArgumentException(
                '"lines" must be exactly 6 comma-separated "yin"/"yang" values.',
            );
        }

        $values = explode(',', $lines);

        if (count($values) !== 6) {
            throw new \InvalidArgumentException(
                sprintf('"lines" must contain exactly 6 values, got %d.', count($values)),
            );
        }

        return array_map(
            static fn (int $index, string $value): Line => new Line(
                $index + 1,
                match ($value) {
                    'yin' => LinePolarity::Yin,
                    'yang' => LinePolarity::Yang,
                    default => throw new \InvalidArgumentException(
                        sprintf('Invalid polarity at index %d: expected "yin" or "yang".', $index),
                    ),
                },
                false,
            ),
            array_keys($values),
            $values,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function toJson(Hexagram $hexagram, bool $favorite): array
    {
        return [
            'kingWenNumber' => $hexagram->kingWenNumber,
            'chineseName' => $hexagram->chineseName,
            'pinyin' => $hexagram->pinyin,
            'symbol' => $hexagram->symbol(),
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
            'relationships' => $this->relationshipsToJson($hexagram),
            'favorite' => $favorite,
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function relationshipsToJson(Hexagram $hexagram): array
    {
        return [
            'nuclear' => $this->hexagramSummaryToJson(YijingRelations::getNuclearHexagram($hexagram)),
            'reversed' => $this->hexagramSummaryToJson(YijingRelations::getOpposite($hexagram)),
            'complement' => $this->hexagramSummaryToJson(YijingRelations::getComplement($hexagram)),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function hexagramSummaryToJson(Hexagram $hexagram): array
    {
        return [
            'kingWenNumber' => $hexagram->kingWenNumber,
            'chineseName' => $hexagram->chineseName,
            'pinyin' => $hexagram->pinyin,
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
