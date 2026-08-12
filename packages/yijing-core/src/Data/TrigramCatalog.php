<?php

declare(strict_types=1);

namespace Yijing\Core\Data;

use Yijing\Core\TrigramId;

/**
 * Static reference data for the 8 trigrams. Patterns are 3-character strings of '1' (yang) /
 * '0' (yin), read bottom-to-top, derived from the classical "family" mnemonic: Zhen/Kan/Gen are
 * Kun with one yang line placed at the bottom/middle/top; Xun/Li/Dui are Qian with one yin line
 * placed at the bottom/middle/top. Family/element/direction follow the Later Heaven (King Wen)
 * Bagua arrangement.
 *
 * @phpstan-type TrigramAttributes array{
 *     pattern: string,
 *     name: string,
 *     chineseName: string,
 *     pinyin: string,
 *     symbol: string,
 *     element: string,
 *     familyMember: string,
 *     direction: string,
 *     image: string,
 * }
 */
final class TrigramCatalog
{
    /** @var array<string, TrigramAttributes> */
    private const ENTRIES = [
        'Qian' => [
            'pattern' => '111',
            'name' => 'Qian',
            'chineseName' => '乾',
            'pinyin' => 'Qián',
            'symbol' => '☰',
            'element' => 'Metal',
            'familyMember' => 'Father',
            'direction' => 'Northwest',
            'image' => 'Heaven',
        ],
        'Kun' => [
            'pattern' => '000',
            'name' => 'Kun',
            'chineseName' => '坤',
            'pinyin' => 'Kūn',
            'symbol' => '☷',
            'element' => 'Earth',
            'familyMember' => 'Mother',
            'direction' => 'Southwest',
            'image' => 'Earth',
        ],
        'Zhen' => [
            'pattern' => '100',
            'name' => 'Zhen',
            'chineseName' => '震',
            'pinyin' => 'Zhèn',
            'symbol' => '☳',
            'element' => 'Wood',
            'familyMember' => 'Eldest Son',
            'direction' => 'East',
            'image' => 'Thunder',
        ],
        'Kan' => [
            'pattern' => '010',
            'name' => 'Kan',
            'chineseName' => '坎',
            'pinyin' => 'Kǎn',
            'symbol' => '☵',
            'element' => 'Water',
            'familyMember' => 'Middle Son',
            'direction' => 'North',
            'image' => 'Water',
        ],
        'Gen' => [
            'pattern' => '001',
            'name' => 'Gen',
            'chineseName' => '艮',
            'pinyin' => 'Gèn',
            'symbol' => '☶',
            'element' => 'Earth',
            'familyMember' => 'Youngest Son',
            'direction' => 'Northeast',
            'image' => 'Mountain',
        ],
        'Xun' => [
            'pattern' => '011',
            'name' => 'Xun',
            'chineseName' => '巽',
            'pinyin' => 'Xùn',
            'symbol' => '☴',
            'element' => 'Wood',
            'familyMember' => 'Eldest Daughter',
            'direction' => 'Southeast',
            'image' => 'Wind',
        ],
        'Li' => [
            'pattern' => '101',
            'name' => 'Li',
            'chineseName' => '離',
            'pinyin' => 'Lí',
            'symbol' => '☲',
            'element' => 'Fire',
            'familyMember' => 'Middle Daughter',
            'direction' => 'South',
            'image' => 'Fire',
        ],
        'Dui' => [
            'pattern' => '110',
            'name' => 'Dui',
            'chineseName' => '兌',
            'pinyin' => 'Duì',
            'symbol' => '☱',
            'element' => 'Metal',
            'familyMember' => 'Youngest Daughter',
            'direction' => 'West',
            'image' => 'Lake',
        ],
    ];

    /**
     * @return TrigramAttributes
     */
    public static function attributesFor(TrigramId $id): array
    {
        return self::ENTRIES[$id->name];
    }

    public static function patternFor(TrigramId $id): string
    {
        return self::ENTRIES[$id->name]['pattern'];
    }

    public static function idForPattern(string $pattern): TrigramId
    {
        foreach (TrigramId::cases() as $id) {
            if (self::ENTRIES[$id->name]['pattern'] === $pattern) {
                return $id;
            }
        }

        throw new \InvalidArgumentException("No trigram matches pattern '{$pattern}'.");
    }
}
