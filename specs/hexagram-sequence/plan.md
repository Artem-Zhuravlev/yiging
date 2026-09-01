# Plan — Sequence of the Hexagrams (SPEC-056)

## 1. `packages/yijing-core`

### `src/Data/HexagramSequenceCatalog.php` (new)
```php
final class HexagramSequenceCatalog
{
    /** @var array<int, string> King Wen number 3..64 => Xù Guà rationale */
    private const ENTRIES = [
        3 => 'When there were heaven and earth, ... Hence (Qian and Kun) are followed by Zhun.',
        // ... 4..64 ...
        64 => 'But the succession of events cannot come to an end, and therefore Ji Ji is succeeded by Wei Ji, with which (the hexagrams) come to a close.',
    ];

    public static function precedentFor(int $kingWenNumber): ?string
    {
        return self::ENTRIES[$kingWenNumber] ?? null;
    }
}
```
- Text compiled from ctext.org's Xù Guà (Legge translation, pinyin names), cross-checked
  against baharna.com. Mapping: paragraphs 1..28 of the Xù Guà → hexagrams 3..30 (offset −2);
  paragraph 29 (Section I close) appended to hexagram 30's entry; paragraph 30 (Section II
  preamble) → hexagram 31; paragraphs 31..63 → hexagrams 32..64 (offset −1).
- Provenance docblock mirroring `HexagramTextCatalog`'s.

### `tests/Data/HexagramSequenceCatalogTest.php` (new)
- `precedentFor(1)` and `(2)` are `null`.
- `precedentFor(3)` contains `Zhun`; `precedentFor(4)` contains `Meng` and `Zhun`.
- `precedentFor(31)` contains `husband and wife` and does NOT contain `is followed by`.
- `precedentFor(30)` ends with `adhering, to.`.
- `precedentFor(64)` contains `Wei Ji` and `come to a close`.
- every `$n` in `3..64` returns a non-empty string; `65` → `null`.

## 2. API (`apps/api`)

### `src/Hexagrams/HexagramController.php`
- `use Yijing\Core\Data\HexagramSequenceCatalog;`
- in `toJson()`, inside the existing `if ($includeDynamics)` block:
  ```php
  $json['sequencePrecedent'] = HexagramSequenceCatalog::precedentFor($hexagram->kingWenNumber);
  ```
  (Same detail-only gate as `lineDynamics`; `index()` passes `includeDynamics: false`.)

### `tests/Hexagrams/HexagramControllerTest.php`
- `GET /api/hexagrams/3` → `sequencePrecedent` is a string containing `Zhun`.
- `GET /api/hexagrams/1` → `sequencePrecedent` is `null` (key present, value null).
- `GET /api/hexagrams` → `assertArrayNotHasKey('sequencePrecedent', $body[0])`.
- `GET /api/hexagrams/from-lines?lines=<hexagram 4 pattern>` → `sequencePrecedent` present.

## 3. Frontend (`apps/web`)

### `entities/hexagram/model.ts`
- `Hexagram` gains `sequencePrecedent?: string | null`.

### `pages/hexagrams/HexagramDetailPage.vue`
- computed `predecessor` = `state.hexagram.kingWenNumber > 1 ? kingWenNumber - 1 : null`
  (only used when `sequencePrecedent` is non-null, which already implies `>= 3`, so the link
  target is always valid).
- a `<section v-if="state.hexagram.sequencePrecedent">` (place near the other classical-text
  sections, before the Legge source attribution):
  - `<h2>{{ t('hexagramSequence.title') }}</h2>`
  - a sub-heading: `{{ t('hexagramSequence.heading', { n, name, pinyin, prev, prevName,
    prevPinyin }) }}` wrapping the predecessor identifier in a `<router-link
    :to="`/hexagrams/${prev}`">`. Fetch the predecessor's name/pinyin from
    `HexagramCatalog`? The page only has the current hexagram. Simplest: the heading names
    only the current hexagram and links "← hexagram {{ prev }}" generically, OR fetch the
    predecessor summary. Decision: keep it to `t('hexagramSequence.heading', { n, name })` +
    a separate `<router-link>` reading `t('hexagramSequence.predecessorLink', { prev })`
    ("← Hexagram {prev} in the sequence") — no extra fetch.
  - `<p>{{ state.hexagram.sequencePrecedent }}</p>`
  - `<p class="text-xs text-color-secondary">{{ t('hexagramSequence.source') }}</p>`

### i18n (`en.ts` + `uk.ts`) — `hexagramSequence` block
- `title`: "Place in the sequence" / "Місце в послідовності"
- `heading`: "Why {n}. {name} follows the hexagram before it" / parallel
- `predecessorLink`: "← Hexagram {prev} in the King Wen order" / parallel
- `source`: "From the 序卦傳 (Xù Guà, 'Orderly Sequence of the Hexagrams'), one of the Ten
  Wings — James Legge's translation, 1899." / parallel

### `pages/hexagrams/HexagramDetailPage.spec.ts`
- fixture with `sequencePrecedent: 'Meng is descriptive of what is undeveloped... Hence Meng is
  followed by Xu.'` and `kingWenNumber: 5` → the "Place in the sequence" section renders the
  text and a link to `/hexagrams/4`.
- fixture `kingWenNumber: 1`, `sequencePrecedent: null` (or absent) → no such section.

## 4. Verify

`cd packages/yijing-core && composer test && composer stan && composer lint`; same `apps/api`;
`npm run verify`. Browser (php dev server + web): `/hexagrams/4` shows "Place in the sequence"
with the Meng→Xu... wait, hexagram 4's precedent is Zhun→Meng; check the sentence names Meng
and Zhun and links to `/hexagrams/3`; `/hexagrams/1` shows no such section; `/hexagrams/31`
shows the "Heaven and earth existing…" preamble and links to `/hexagrams/30`.
curl: `/api/hexagrams/3` has `sequencePrecedent`, `/api/hexagrams` does not.

## Verification note (2026-09-01)

- yijing-core: 82 tests (+7 `HexagramSequenceCatalogTest`), phpstan level 8 + php-cs-fixer clean.
- apps/api: 358 tests (+4 `HexagramControllerTest`), stan + cs-fixer clean.
- `npm run verify` green: web lint/typecheck/test (214, incl. 2 new `HexagramDetailPage`
  tests) + build.
- Live pass: `GET /api/hexagrams/3` → precedent naming Zhun; `/1` → `sequencePrecedent: null`
  (key present); `/31` → the "Heaven and earth existing…" Section II preamble; `/64` → the
  Ji Ji → Wei Ji close; `GET /api/hexagrams` (list) has no `sequencePrecedent` key.
  Browser: `/hexagrams/4` shows a "Place in the sequence" section (Zhun→Meng text + "←
  Hexagram 3" link → `/hexagrams/3`), `/hexagrams/31` shows the preamble linking to
  `/hexagrams/30`, `/hexagrams/1` shows no such section.
