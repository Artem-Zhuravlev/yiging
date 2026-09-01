<?php

declare(strict_types=1);

namespace Yijing\Core\Data;

/**
 * The 序卦傳 (Xu Gua, "Treatise on the Orderly Sequence of the Hexagrams"), one of the Ten
 * Wings: for each hexagram from the 3rd onward in the King Wen order, the classical one-sentence
 * rationale for why it follows the hexagram before it.
 *
 * Source: James Legge's translation, Appendix VI of 'The Yi King' (Sacred Books of the East,
 * vol. 16), 1899 - public domain (see SPEC-002). Transcribed from the Chinese Text Project's
 * presentation of Legge's text (ctext.org/book-of-changes/xu-gua), which modernises Legge's
 * hexagram-name romanisation to pinyin, and cross-checked against the baharna.com Legge
 * digitization (Appendix 6 - Sequence of the Hexagrams) - wording identical apart from the
 * romanisation system (baharna keeps Legge's 'Khien / Kun / Mang ...'). Two obvious digitization
 * artefacts in the ctext copy are normalised here: 'Fu. is followed' -> 'Fu is followed'
 * (hexagram 25), and a stray comma in 'Those who, have what is great' (hexagram 15).
 *
 * Paragraph-to-hexagram mapping: Xu Gua paragraphs 1-28 correspond to hexagrams 3-30; the
 * closing clause of Section I is appended to hexagram 30; the Section II preamble ("Heaven and
 * earth existing ...") introduces hexagram 31; paragraphs 31-63 correspond to hexagrams 32-64.
 * Hexagrams 1 and 2 (Qian and Kun, the heaven/earth pair with which the sequence opens) have no
 * entry - the Xu Gua offers no "why it follows" for them.
 */
final class HexagramSequenceCatalog
{
    /** @var array<int, string> King Wen number (3-64) => Xu Gua rationale */
    private const ENTRIES = [
        3 => 'When there were heaven and earth, then afterwards all things were produced. What fills up (the space) between heaven and earth are (those) all things. Hence (Qian and Kun) are followed by Zhun.',
        4 => 'Zhun denotes filling up. Zhun is descriptive of things on their first production. When so produced, they are sure to be in an undeveloped condition. Hence Zhun is followed by Meng.',
        5 => 'Meng is descriptive of what is undeveloped,--the young of creatures and things. These in that state require to be nourished. Hence Meng is followed by Xu.',
        6 => 'Xu is descriptive of the way in which meat and drink (come to be supplied). Over meat and drink there are sure to be contentions. Hence Xu is followed by Song.',
        7 => 'Song is sure to cause the rising up of the multitudes; and hence it is followed by Shi.',
        8 => 'Shi has the signification of multitudes, and between multitudes there must be some bond of union. Hence it is followed by Bi.',
        9 => 'Bi denotes being attached to. (Multitudes in) union must be subjected to some restraint. Hence Bi is followed by Xiao Xu.',
        10 => 'When things are subjected to restraint, there come to be rites of ceremony, and hence Xiao Xu is followed by Li.',
        11 => 'The treading (on what is proper) leads to Tai, which issues in a state of freedom and repose, and hence Li is followed by Tai.',
        12 => 'Tai denotes things having free course. They cannot have that for ever, and hence it is followed by Pi (denoting being shut up and restricted).',
        13 => 'Things cannot for ever be shut up, and hence Pi is followed by Tong Ren.',
        14 => 'To him who cultivates union with men, things must come to belong, and hence Tong Ren is followed by Da You.',
        15 => 'Those who have what is great should not allow in themselves the feeling of being full, and hence Da You is followed by Qian.',
        16 => 'When great possessions are associated with humility, there is sure to be pleasure and satisfaction; and hence Qian is followed by Yu.',
        17 => 'Where such complacency is awakened, (he who causes it) is sure to have followers (Sui).',
        18 => 'They who follow another are sure to have services (to perform), and hence Sui is followed by Gu.',
        19 => 'Gu means (the performance of) services. He who performs such services may afterwards become great, and hence Gu is followed by Lin.',
        20 => 'Lin means great. What is great draws forth contemplation, and hence Lin is followed by Guan.',
        21 => 'He who attracts contemplation will then bring about the union of others with himself, and hence Guan is followed by Shi He.',
        22 => 'Shi He means union. But things should not be united in a reckless or irregular way, and hence Shi He is followed by Bi.',
        23 => 'Bi denotes adorning. When ornamentation has been carried to the utmost, its progress comes to an end; and hence Bi is followed by Po.',
        24 => 'Po denotes decay and overthrow. Things cannot be done away for ever. When decadence and overthrow have completed their work at one end, reintegration commences at the other; and hence Po is followed by Fu.',
        25 => 'When the return (thus indicated) has taken place, we have not any rash disorder, and Fu is followed by Wu Wang.',
        26 => 'Given the freedom from disorder and insincerity (which this name denotes), there may be the accumulation (of virtue), and Wu Wang is followed by Da Xu.',
        27 => 'Such accumulation having taken place, there will follow the nourishment of it; and hence Da Xu is followed by Yi.',
        28 => 'Yi denotes nourishing. Without nourishment there could be no movement, and hence Yi is followed by Da Guo.',
        29 => 'Things cannot for ever be in a state of extraordinary (progress); and hence Da Guo is followed by Kan.',
        30 => 'Kan denotes falling into peril. When one falls into peril, he is sure to attach himself to some person or thing; and hence Kan is followed by Li. Li denotes being attached, or adhering, to.',
        31 => 'Heaven and earth existing, all (material) things then got their existence. All (material) things having existence, afterwards there came male and female. From the existence of male and female there came afterwards husband and wife. From husband and wife there came father and son. From father and son there came ruler and minister. From ruler and minister there came high and low. When (the distinction of) high and low had existence, afterwards came the arrangements of propriety and righteousness.',
        32 => 'The rule for the relation of husband and wife is that it should be long-enduring. Hence Xian is followed by Heng.',
        33 => 'Heng denotes long enduring. Things cannot long abide in the same place; and hence Heng is followed by Dun.',
        34 => 'Dun denotes withdrawing. Things cannot be for ever withdrawn; and hence Dun is succeeded by Da Zhuang.',
        35 => 'Things cannot remain forever (simply) in the state of vigour; and hence Da Zhuang is succeeded by Jin.',
        36 => 'Jin denotes advancing. (But) advancing is sure to lead to being wounded; and hence Jin is succeeded by Ming Yi.',
        37 => 'Yi denotes being wounded. He who is wounded abroad will return to his home; and hence Ming Yi is followed by Jia Ren.',
        38 => 'When the right administration of the family is at an end, misunderstanding and division will ensue; and hence Jia Ren is followed by Kui.',
        39 => 'Kui denotes misunderstanding and division; and such a state is sure to give rise to difficulties and complications. Kui therefore is followed by Jian.',
        40 => 'Jian denotes difficulties; but things cannot remain for ever in such a state. Jian therefore is followed by Jie.',
        41 => 'Jie denotes relaxation and ease. In a state of relaxation and ease there are sure to be losses; and hence Jie is followed by Sun.',
        42 => 'But when Sun (or diminution) is going on without end, increase is sure to come. Sun therefore is followed by Yi.',
        43 => 'When increase goes on without end, there is sure to come a dispersing of it, and hence Yi is followed by Guai.',
        44 => 'Guai denotes dispersion. But dispersion must be succeeded by a meeting (again). Hence Guai is followed by Gou.',
        45 => 'Gou denotes such meeting. When things meet together, a collection is then formed. Hence Gou is followed by Cui.',
        46 => 'Cui denotes being collected. When (good men) are collected and mount to the highest places, there results what we call an upward advance; and hence Cui is followed by Sheng.',
        47 => 'When such advance continues without stopping, there is sure to come distress; and hence Sheng is followed by Kun.',
        48 => 'When distress is felt in the height (that has been gained), there is sure to be a return to the ground beneath; and hence Kun is followed by Jing.',
        49 => 'What happens under Jing requires to be changed, and hence it is followed by Ge (denoting change).',
        50 => 'For changing the substance of things there is nothing equal to the caldron; and hence Ge is followed by Ding.',
        51 => 'For presiding over (that and all other) vessels, no one is equal to the eldest son, and hence Ding is followed by Zhen.',
        52 => 'Zhen conveys the idea of putting in motion. But things cannot be kept in motion forever. The motion is stopped; and hence Zhen is followed by Gen.',
        53 => 'Gen gives the idea of arresting or stopping. Things cannot be kept for ever in a state of repression, and hence Gen is followed by Jian.',
        54 => 'Jian gives the idea of (gradually) advancing. With advance there must be a certain point that is arrived at, and hence Jian is succeeded by Gui Mei.',
        55 => 'When things thus find the proper point to which to come, they are sure to become great. Hence Gui Mei is succeeded by Feng.',
        56 => 'Feng conveys the idea of being great. He whose greatness reaches the utmost possibility, is sure to lose his dwelling; and hence Feng is succeeded by Lu (denoting travellers or strangers).',
        57 => 'We have in it the idea of strangers who have no place to receive them, and hence Lu is followed by Xun.',
        58 => 'Xun gives the idea of (penetrating and) entering. One enters (on the pursuit of his object), and afterwards has pleasure in it; hence Xun is followed by Dui.',
        59 => 'Dui denotes pleasure and satisfaction. This pleasure and satisfaction (begins) afterwards to be dissipated, and hence Dui is followed by Huan.',
        60 => 'Huan denotes separation and division. A state of division cannot continue for ever, and therefore Huan is followed by Jie.',
        61 => 'Jie (or the system of regulations) having been established, men believe in it, and hence it is followed by Zhong Fu.',
        62 => 'When men have the belief which Zhong Fu implies, they are sure to carry it into practice; and hence it is succeeded by Xiao Guo.',
        63 => 'He that surpasses others is sure to remedy (evils that exist), and therefore Xiao Guo is succeeded by Ji Ji.',
        64 => 'But the succession of events cannot come to an end, and therefore Ji Ji is succeeded by Wei Ji, with which (the hexagrams) come to a close.',
    ];

    /**
     * @param string $locale 'uk' for the Ukrainian rendering (SPEC-057), anything else for the
     *                        canonical English
     */
    public static function precedentFor(int $kingWenNumber, string $locale = 'en'): ?string
    {
        if ($locale === 'uk') {
            return HexagramSequenceCatalogUk::ENTRIES[$kingWenNumber]
                ?? self::ENTRIES[$kingWenNumber]
                ?? null;
        }

        return self::ENTRIES[$kingWenNumber] ?? null;
    }
}
