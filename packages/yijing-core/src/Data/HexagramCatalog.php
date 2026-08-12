<?php

declare(strict_types=1);

namespace Yijing\Core\Data;

/**
 * Static structural reference data for the 64 hexagrams in King Wen sequence order.
 *
 * Patterns are 6-character strings of '1' (yang) / '0' (yin), read bottom-to-top: the first 3
 * characters are the lower trigram, the last 3 the upper trigram. Cross-checked against the
 * traditional King Wen sequence (lower/upper trigram per hexagram) and spot-verified against
 * independently well-documented facts: the 8 "doubled" hexagrams (11=Qian/Qian, 2=Kun/Kun,
 * 29=Kan/Kan, 30=Li/Li, 51=Zhen/Zhen, 52=Gen/Gen, 57=Xun/Xun, 58=Dui/Dui) and the well-known
 * Tai/Pi (11/12) and Jiji/Weiji (63/64) mirror pairs.
 *
 * Judgment, image, and line-statement text are intentionally not included here — see SPEC-002's
 * "Data requirements" (classical-text pass, not yet done).
 *
 * @phpstan-type HexagramEntry array{pattern: string, chineseName: string, pinyin: string}
 */
final class HexagramCatalog
{
    /** @var array<int, HexagramEntry> King Wen number (1-64) => entry */
    private const ENTRIES = [
        1 => ['pattern' => '111111', 'chineseName' => '乾', 'pinyin' => 'Qián'],
        2 => ['pattern' => '000000', 'chineseName' => '坤', 'pinyin' => 'Kūn'],
        3 => ['pattern' => '100010', 'chineseName' => '屯', 'pinyin' => 'Zhūn'],
        4 => ['pattern' => '010001', 'chineseName' => '蒙', 'pinyin' => 'Méng'],
        5 => ['pattern' => '111010', 'chineseName' => '需', 'pinyin' => 'Xū'],
        6 => ['pattern' => '010111', 'chineseName' => '訟', 'pinyin' => 'Sòng'],
        7 => ['pattern' => '010000', 'chineseName' => '師', 'pinyin' => 'Shī'],
        8 => ['pattern' => '000010', 'chineseName' => '比', 'pinyin' => 'Bǐ'],
        9 => ['pattern' => '111011', 'chineseName' => '小畜', 'pinyin' => 'Xiǎo Xù'],
        10 => ['pattern' => '110111', 'chineseName' => '履', 'pinyin' => 'Lǚ'],
        11 => ['pattern' => '111000', 'chineseName' => '泰', 'pinyin' => 'Tài'],
        12 => ['pattern' => '000111', 'chineseName' => '否', 'pinyin' => 'Pǐ'],
        13 => ['pattern' => '101111', 'chineseName' => '同人', 'pinyin' => 'Tóng Rén'],
        14 => ['pattern' => '111101', 'chineseName' => '大有', 'pinyin' => 'Dà Yǒu'],
        15 => ['pattern' => '001000', 'chineseName' => '謙', 'pinyin' => 'Qiān'],
        16 => ['pattern' => '000100', 'chineseName' => '豫', 'pinyin' => 'Yù'],
        17 => ['pattern' => '100110', 'chineseName' => '隨', 'pinyin' => 'Suí'],
        18 => ['pattern' => '011001', 'chineseName' => '蠱', 'pinyin' => 'Gǔ'],
        19 => ['pattern' => '110000', 'chineseName' => '臨', 'pinyin' => 'Lín'],
        20 => ['pattern' => '000011', 'chineseName' => '觀', 'pinyin' => 'Guān'],
        21 => ['pattern' => '100101', 'chineseName' => '噬嗑', 'pinyin' => 'Shì Kè'],
        22 => ['pattern' => '101001', 'chineseName' => '賁', 'pinyin' => 'Bì'],
        23 => ['pattern' => '000001', 'chineseName' => '剝', 'pinyin' => 'Bō'],
        24 => ['pattern' => '100000', 'chineseName' => '復', 'pinyin' => 'Fù'],
        25 => ['pattern' => '100111', 'chineseName' => '无妄', 'pinyin' => 'Wú Wàng'],
        26 => ['pattern' => '111001', 'chineseName' => '大畜', 'pinyin' => 'Dà Xù'],
        27 => ['pattern' => '100001', 'chineseName' => '頤', 'pinyin' => 'Yí'],
        28 => ['pattern' => '011110', 'chineseName' => '大過', 'pinyin' => 'Dà Guò'],
        29 => ['pattern' => '010010', 'chineseName' => '坎', 'pinyin' => 'Kǎn'],
        30 => ['pattern' => '101101', 'chineseName' => '離', 'pinyin' => 'Lí'],
        31 => ['pattern' => '001110', 'chineseName' => '咸', 'pinyin' => 'Xián'],
        32 => ['pattern' => '011100', 'chineseName' => '恆', 'pinyin' => 'Héng'],
        33 => ['pattern' => '001111', 'chineseName' => '遯', 'pinyin' => 'Dùn'],
        34 => ['pattern' => '111100', 'chineseName' => '大壯', 'pinyin' => 'Dà Zhuàng'],
        35 => ['pattern' => '000101', 'chineseName' => '晉', 'pinyin' => 'Jìn'],
        36 => ['pattern' => '101000', 'chineseName' => '明夷', 'pinyin' => 'Míng Yí'],
        37 => ['pattern' => '101011', 'chineseName' => '家人', 'pinyin' => 'Jiā Rén'],
        38 => ['pattern' => '110101', 'chineseName' => '睽', 'pinyin' => 'Kuí'],
        39 => ['pattern' => '001010', 'chineseName' => '蹇', 'pinyin' => 'Jiǎn'],
        40 => ['pattern' => '010100', 'chineseName' => '解', 'pinyin' => 'Jiě'],
        41 => ['pattern' => '110001', 'chineseName' => '損', 'pinyin' => 'Sǔn'],
        42 => ['pattern' => '100011', 'chineseName' => '益', 'pinyin' => 'Yì'],
        43 => ['pattern' => '111110', 'chineseName' => '夬', 'pinyin' => 'Guài'],
        44 => ['pattern' => '011111', 'chineseName' => '姤', 'pinyin' => 'Gòu'],
        45 => ['pattern' => '000110', 'chineseName' => '萃', 'pinyin' => 'Cuì'],
        46 => ['pattern' => '011000', 'chineseName' => '升', 'pinyin' => 'Shēng'],
        47 => ['pattern' => '010110', 'chineseName' => '困', 'pinyin' => 'Kùn'],
        48 => ['pattern' => '011010', 'chineseName' => '井', 'pinyin' => 'Jǐng'],
        49 => ['pattern' => '101110', 'chineseName' => '革', 'pinyin' => 'Gé'],
        50 => ['pattern' => '011101', 'chineseName' => '鼎', 'pinyin' => 'Dǐng'],
        51 => ['pattern' => '100100', 'chineseName' => '震', 'pinyin' => 'Zhèn'],
        52 => ['pattern' => '001001', 'chineseName' => '艮', 'pinyin' => 'Gèn'],
        53 => ['pattern' => '001011', 'chineseName' => '漸', 'pinyin' => 'Jiàn'],
        54 => ['pattern' => '110100', 'chineseName' => '歸妹', 'pinyin' => 'Guī Mèi'],
        55 => ['pattern' => '101100', 'chineseName' => '豐', 'pinyin' => 'Fēng'],
        56 => ['pattern' => '001101', 'chineseName' => '旅', 'pinyin' => 'Lǚ'],
        57 => ['pattern' => '011011', 'chineseName' => '巽', 'pinyin' => 'Xùn'],
        58 => ['pattern' => '110110', 'chineseName' => '兌', 'pinyin' => 'Duì'],
        59 => ['pattern' => '010011', 'chineseName' => '渙', 'pinyin' => 'Huàn'],
        60 => ['pattern' => '110010', 'chineseName' => '節', 'pinyin' => 'Jié'],
        61 => ['pattern' => '110011', 'chineseName' => '中孚', 'pinyin' => 'Zhōng Fú'],
        62 => ['pattern' => '001100', 'chineseName' => '小過', 'pinyin' => 'Xiǎo Guò'],
        63 => ['pattern' => '101010', 'chineseName' => '既濟', 'pinyin' => 'Jì Jì'],
        64 => ['pattern' => '010101', 'chineseName' => '未濟', 'pinyin' => 'Wèi Jì'],
    ];

    /**
     * @return HexagramEntry
     */
    public static function entryFor(int $kingWenNumber): array
    {
        if (!isset(self::ENTRIES[$kingWenNumber])) {
            throw new \InvalidArgumentException("No hexagram with King Wen number {$kingWenNumber}.");
        }

        return self::ENTRIES[$kingWenNumber];
    }

    public static function kingWenNumberForPattern(string $pattern): int
    {
        foreach (self::ENTRIES as $number => $entry) {
            if ($entry['pattern'] === $pattern) {
                return $number;
            }
        }

        throw new \InvalidArgumentException("No hexagram matches pattern '{$pattern}'.");
    }

    /**
     * @return array<int, HexagramEntry>
     */
    public static function all(): array
    {
        return self::ENTRIES;
    }
}
