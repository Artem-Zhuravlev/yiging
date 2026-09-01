# Plan — Ukrainian Classical Text (SPEC-057)

## 1. `packages/yijing-core`

### `src/Data/HexagramTextCatalogUk.php` (new)
```php
final class HexagramTextCatalogUk
{
    /**
     * Machine-assisted Ukrainian translation of Legge's English (see HexagramTextCatalog).
     * NOT from the Chinese, NOT a published Ukrainian edition. The English ENTRIES stay the
     * sourced canonical text (SPEC-002); this exists for uk-locale readability (SPEC-057).
     *
     * @phpstan-import-type HexagramText from HexagramTextCatalog
     * @var array<int, HexagramText>
     */
    public const ENTRIES = [ 1 => ['judgment' => '…', 'image' => '…', 'lineStatements' => [ … 6 … ]], … 64 ];

    /** @var array<int, string> */
    public const SPECIAL = [ 1 => '…', 2 => '…' ];
}
```

### `src/Data/HexagramSequenceCatalogUk.php` (new)
```php
final class HexagramSequenceCatalogUk
{
    /** Ukrainian; see HexagramSequenceCatalog + the note above. @var array<int, string> */
    public const ENTRIES = [ 3 => '…', … 64 => '…' ];
}
```

### `src/Data/HexagramTextCatalog.php`
```php
public static function textFor(int $kingWenNumber, string $locale = 'en'): array
{
    if (!isset(self::ENTRIES[$kingWenNumber])) {
        throw new \InvalidArgumentException("No hexagram text for King Wen number {$kingWenNumber}.");
    }
    if ($locale === 'uk') {
        return HexagramTextCatalogUk::ENTRIES[$kingWenNumber] ?? self::ENTRIES[$kingWenNumber];
    }
    return self::ENTRIES[$kingWenNumber];
}

public static function specialTextFor(int $kingWenNumber, string $locale = 'en'): ?string
{
    if ($locale === 'uk') {
        return HexagramTextCatalogUk::SPECIAL[$kingWenNumber] ?? self::SPECIAL[$kingWenNumber] ?? null;
    }
    return self::SPECIAL[$kingWenNumber] ?? null;
}
```

### `src/Data/HexagramSequenceCatalog.php`
```php
public static function precedentFor(int $kingWenNumber, string $locale = 'en'): ?string
{
    if ($locale === 'uk') {
        return HexagramSequenceCatalogUk::ENTRIES[$kingWenNumber] ?? self::ENTRIES[$kingWenNumber] ?? null;
    }
    return self::ENTRIES[$kingWenNumber] ?? null;
}
```

### Tests
- `tests/HexagramTextCatalogUkTest.php` — for 1..64: `textFor($n, 'uk')` has keys
  `judgment/image/lineStatements`, all non-empty, `lineStatements` count 6, and each string
  contains a Cyrillic character; `specialTextFor(1|2, 'uk')` non-empty Cyrillic,
  `specialTextFor(3, 'uk')` null; `textFor($n)` unchanged vs `textFor($n, 'en')`.
- `tests/HexagramSequenceCatalogUkTest.php` — `precedentFor($n, 'uk')` for 3..64 non-empty
  Cyrillic; `precedentFor(1|2, 'uk')` null.
- extend the existing catalog tests only where the signature changed (still green with no 2nd arg).

## 2. API (`apps/api`)

### `src/Core/RequestLocale.php` (new)
```php
final class RequestLocale
{
    public static function from(Request $request): string
    {
        $lang = $request->query->get('lang');
        return $lang === 'uk' ? 'uk' : 'en';
    }
}
```

### `src/Hexagrams/HexagramController.php`
- `use App\Core\RequestLocale; use Yijing\Core\Data\HexagramTextCatalog; use …\HexagramSequenceCatalog;`
- `toJson(Hexagram $hexagram, bool $favorite, bool $includeDynamics = false, string $locale = 'en')`:
  - when `$locale !== 'en'`, overlay:
    ```php
    $text = HexagramTextCatalog::textFor($hexagram->kingWenNumber, $locale);
    $json['judgment'] = $text['judgment'];
    $json['image'] = $text['image'];
    $json['lineStatements'] = $text['lineStatements'];
    ```
  - `sequencePrecedent` line already inside the `includeDynamics` block →
    `HexagramSequenceCatalog::precedentFor($hexagram->kingWenNumber, $locale)`.
- `index()`, `show()`, `fromLines()`, `compare()` each read `$locale = RequestLocale::from($request)`
  and pass it. (`index()` signature currently `index()` with no `Request` — add `Request $request`;
  the kernel always passes it.)
- `compare()` passes `$locale` into both `toJson($a, …)` / `toJson($b, …)`.

### `src/Readings/ConsultationController.php`
- `show()` → `$locale = RequestLocale::from($request)`, thread into `toJsonWithRepeats($consultation, $locale)`
  → `readingGuidanceToJson($reading, $consultation, $locale)`.
- in `readingGuidanceToJson`, replace the `$hexagram->judgment` / `$hexagram->lineStatements[$position-1]`
  reads with:
  ```php
  $text = HexagramTextCatalog::textFor($hexagram->kingWenNumber, $locale);
  $value = $ref->kind === 'judgment' ? $text['judgment'] : $text['lineStatements'][$position - 1];
  ```
  and `specialTextContent` via `HexagramTextCatalog::specialTextFor($primary->kingWenNumber, $locale)`.
- No other consultation field is localised (question, notes, etc. are user content).

### Tests
- `HexagramControllerTest` — `GET /api/hexagrams/2?lang=uk` → judgment/image/lineStatements/
  sequencePrecedent contain Cyrillic; `?lang=en` and no-param unchanged (assert an English
  substring still present); `?lang=fr` behaves as en; `GET /api/hexagrams?lang=uk` first item
  Cyrillic judgment; `compare?a=1&b=2&lang=uk` → `a.judgment` Cyrillic.
- `ConsultationControllerTest` — create a manual all-yang cast with a couple changing lines,
  `GET /api/consultations/{id}?lang=uk` → `readingGuidance.refs[0].text` contains Cyrillic;
  `?lang=en` still English.

## 3. Frontend (`apps/web`)

### `shared/api/http.ts`
- no change (query string built by callers).

### `entities/hexagram/api.ts`
```ts
export function fetchHexagram(kingWenNumber: number, lang = 'en'): Promise<Hexagram> {
  const q = lang !== 'en' ? `?lang=${lang}` : ''
  return apiGet<Hexagram>(`/hexagrams/${kingWenNumber}${q}`)
}
export function fetchHexagrams(lang = 'en'): Promise<Hexagram[]> { … `/hexagrams${q}` }
export function compareHexagrams(a, b, lang = 'en'): Promise<HexagramComparison> { … `&lang=` }
```

### `entities/consultation/api.ts`
```ts
export function fetchConsultation(id: string, lang = 'en'): Promise<ConsultationDetail> {
  const q = lang !== 'en' ? `?lang=${lang}` : ''
  return apiGet<ConsultationDetail>(`/consultations/${id}${q}`)
}
```

### Pages — pass `locale` + `watch` to re-fetch
- `HexagramDetailPage.vue` — `const { t, locale } = useI18n()`; `fetchHexagram(number, locale.value)`;
  `watch(locale, () => load(currentNumber))`.
- `HexagramListPage.vue` — `fetchHexagrams(locale.value)`; `watch(locale, reload)`.
- `HexagramComparePage.vue` — `compareHexagrams(a, b, locale.value)`; `watch(locale, reload)`.
- `ConsultationPage.vue` — `fetchConsultation(id.value, locale.value)`; `watch(locale, reload)`
  (already imports `locale` from `useI18n`).
- `SharedConsultationPage.vue` — same.
- `CastingReveal.vue`, `HomePage.vue` hexagram-of-the-day, consultation-page diagram
  `fetchHexagram` calls — leave as-is (structure only); optionally pass `locale.value` for
  consistency, no `watch`.

### `i18n` — `hexagramDetail.sourceSuffix`
- EN unchanged. UK: note that the classical text is a Ukrainian translation of Legge's English
  ("…переклад з англійського видання Джеймса Леґґе, 1899").

### Tests
- `entities/hexagram/api.spec.ts` / `entities/consultation/api.spec.ts` — a `lang: 'uk'` call
  hits `?lang=uk`; default omits it.
- `HexagramDetailPage.spec.ts` — mock `fetchHexagram`; changing `locale` triggers a re-fetch
  (assert `fetchHexagram` called with `'uk'`). Keep it light.

## 4. Verify

`cd packages/yijing-core && composer test && composer stan && composer lint`; same `apps/api`;
`npm run verify`. Browser (php + web): `/hexagrams/2`, toggle EN/UK — Judgment, Image, all six
line texts, and the sequence sentence flip to Ukrainian and back; a consultation with changing
lines — the reading-guidance quotes flip too. `curl '…/api/hexagrams/2?lang=uk'` shows Cyrillic
`judgment`.

## Verification note (2026-09-01)

- yijing-core: 90 tests (+8 — `HexagramTextCatalogUkTest`, `HexagramSequenceCatalogUkTest`;
  full 1..64 / 3..64 coverage, Cyrillic, structural parity, en path unchanged), phpstan L8 +
  php-cs-fixer clean. `HexagramTextCatalogUk` (64 + Use Nine/Six) and
  `HexagramSequenceCatalogUk` (3..64) added; base catalogs gained `string $locale = 'en'`.
- apps/api: 365 tests (+7 — `HexagramControllerTest` ×6, `ConsultationControllerTest` ×2),
  stan + cs-fixer clean. `App\Core\RequestLocale` reads `?lang`; `HexagramController` (all of
  index/show/from-lines/compare) and `ConsultationController::show` thread it through.
- `npm run verify` green: web lint/typecheck/test (217, incl. api-spec `?lang=` cases + a
  `HexagramDetailPage` locale-switch re-fetch test) + build. `SharedConsultationPage` renders
  no classical text, so it was left untouched.
- Live pass: `GET /api/hexagrams/2?lang=uk` → Ukrainian judgment/image/line texts;
  `/api/hexagrams/4?lang=uk` → Ukrainian Xù Guà; `/api/hexagrams?lang=uk` list → Ukrainian;
  `?lang=en` / none / `?lang=fr` → unchanged English; `GET /api/consultations/{id}?lang=uk`
  for a one-changing-line cast → Ukrainian `readingGuidance.refs[0].text`. Browser: on
  `/hexagrams/2`, EN→UK toggle re-fetches and flips the Judgment, Image, all six line texts
  and (on `/hexagrams/4`) the sequence sentence; the source note gains the "translation of
  Legge's English" line in `uk`.
