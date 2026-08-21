<?php

declare(strict_types=1);

namespace App\Hexagrams;

interface HexagramFavoritesRepository
{
    public function isFavorite(int $kingWenNumber): bool;

    public function add(int $kingWenNumber): void;

    public function remove(int $kingWenNumber): void;

    /**
     * @return list<int>
     */
    public function allFavoriteNumbers(): array;
}
