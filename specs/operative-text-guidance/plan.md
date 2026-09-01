# Plan — Operative Text Guidance (SPEC-052)

## 1. `packages/yijing-core` (do first — pure, fully testable)

### New
- `src/ReadingRule.php` — string-backed enum, kebab-case values:
  `no-changing-lines`, `one-changing-line`, `two-changing-lines`, `three-changing-lines`,
  `four-changing-lines`, `five-changing-lines`, `six-changing-lines`. A `static fromCount(int)`
  helper.
- `src/CastReadingRef.php` — `final readonly class` `{ string $hexagram ('primary'|'resulting'),
  string $kind ('judgment'|'line'), ?int $position, bool $governing }` + a `toArray()`.
- `src/CastReading.php` — `final readonly class`:
  - props `int $changingLineCount`, `ReadingRule $rule`, `list<CastReadingRef> $refs`,
    `?string $specialText`.
  - `public static function forCast(Hexagram $primary, array $changingPositions): self`:
    - normalise: `$positions = array_values(array_unique($changingPositions)); sort($positions);`
      throw `InvalidArgumentException` if any `< 1 || > 6` or `count !== count(unique)`.
    - `$n = count($positions)`.
    - `$resulting = array_reduce($positions, fn (Hexagram $h, int $p) => $h->changeLine($p), $primary);`
    - `match ($n)`:
      - `0` → refs `[ref(primary, judgment, null, true)]`
      - `1` → `[ref(primary, line, $positions[0], true)]`
      - `2` → `[ref(primary, line, $positions[0], false), ref(primary, line, $positions[1], true)]`
      - `3` → `[ref(primary, judgment, null, true), ref(resulting, judgment, null, false)]`
      - `4` → `$unchanged = array_values(array_diff([1..6], $positions))` (2 entries, ascending)
        → `[ref(resulting, line, $unchanged[0], true), ref(resulting, line, $unchanged[1], false)]`
      - `5` → `$unchanged` (1 entry) → `[ref(resulting, line, $unchanged[0], true)]`
      - `6` → `match ($primary->kingWenNumber) { 1 => specialText 'use-nine', 2 => 'use-six',
        default => refs [ref(resulting, judgment, null, true)] }`
  - `toArray()` for the API (`changingLineCount`, `rule` (`->value`), `refs` (map toArray),
    `specialText`).
- `src/Data/HexagramTextCatalog.php`:
  - add an optional `useSpecial` field to entries 1 and 2 (or a separate
    `private const SPECIAL = [1 => '…', 2 => '…'];`). Prefer the separate const — keeps the
    `HexagramText` shape untouched and phpstan-simple.
  - `public static function specialTextFor(int $kingWenNumber): ?string` → `self::SPECIAL[$kingWenNumber] ?? null`.
  - The two Legge strings — **verify against baharna.com Legge + ctext.org before committing**,
    same as every other string in this file.

### Tests
- `tests/CastReadingTest.php` — a case per `n` (0..6) asserting `rule`, `refs` (hexagram/kind/
  position/governing in order), `specialText`; plus:
  - n=6 on pattern `111111` → `specialText: 'use-nine'`, `refs: []`.
  - n=6 on pattern `000000` → `'use-six'`.
  - n=6 on a non-Qián/Kūn primary (e.g. `101010`) → `refs: [{resulting, judgment, governing}]`,
    `specialText: null`.
  - n=4 picks the correct two unchanged positions ascending, lower governing.
  - `forCast` throws on a duplicate or out-of-range position.
- `tests/HexagramTextCatalogTest.php` — `specialTextFor(1)` / `(2)` non-empty & distinct;
  `specialTextFor(3)`…`(64)` sample → `null`.

## 2. API (`apps/api`)

- `ConsultationController::toJsonWithRepeats()` — after the `repeats` block, add:
  ```php
  $reading = CastReading::forCast($consultation->primaryHexagram, $changingLinePositions);
  ... 'readingGuidance' => $this->readingGuidanceToJson($reading, $consultation),
  ```
- `private function readingGuidanceToJson(CastReading $reading, Consultation $c): array`:
  - base = `$reading->toArray()`.
  - map each ref: resolve `text` —
    `$hex = $ref->hexagram === 'primary' ? $c->primaryHexagram : $c->resultingHexagram;`
    `$text = $ref->kind === 'judgment' ? $hex->judgment : $hex->lineStatements[$ref->position - 1];`
    → ref array + `'text' => $text`.
  - if `$reading->specialText !== null`:
    `$base['specialTextContent'] = HexagramTextCatalog::specialTextFor($c->primaryHexagram->kingWenNumber);`
    (always non-null here by construction).
- `use Yijing\Core\CastReading; use Yijing\Core\Data\HexagramTextCatalog;` in the controller.
- Tests (`ConsultationControllerTest`):
  - a manual all-yang cast, no changing lines → `readingGuidance.rule === 'no-changing-lines'`,
    one `judgment` ref on `primary` with `text` === the primary's judgment.
  - a manual cast with exactly one changing line → `one-changing-line`, ref `text` === the
    right `lineStatements` entry.
  - a manual all-yang cast with all 6 changing → `six-changing-lines`, `specialText: 'use-nine'`,
    `specialTextContent` present, `refs: []`.
  - assert `POST /api/consultations` and `GET /api/consultations` (list) responses do **not**
    contain `readingGuidance` (detail-only).

## 3. Frontend (`apps/web`)

- `entities/consultation/model.ts`:
  ```ts
  export interface ReadingGuidanceRef {
    hexagram: 'primary' | 'resulting'
    kind: 'judgment' | 'line'
    position: number | null
    governing: boolean
    text: string
  }
  export interface ReadingGuidance {
    changingLineCount: number
    rule: string
    refs: ReadingGuidanceRef[]
    specialText: 'use-nine' | 'use-six' | null
    specialTextContent?: string
  }
  ```
  `ConsultationDetail` gains `readingGuidance: ReadingGuidance`.
- `ConsultationPage.vue`:
  - `state.consultation` is a `Consultation` (list shape). The detail fetch returns
    `ConsultationDetail`; `repeats` is already pulled into `repeats.value`. Do the same:
    `const readingGuidance = ref<ReadingGuidance | null>(null)` set in `onMounted` from the
    fetched `consultation.readingGuidance`.
  - Panel, after the `changingLinePositions` `<p>` and before the follow-ups/`Notes` block:
    ```
    <section v-if="readingGuidance">
      <h2>{{ t('readingGuidance.title') }}</h2>
      <p>{{ t(`readingGuidance.rule.${readingGuidance.rule}`) }}</p>
      <div v-for="(ref, i) in readingGuidance.refs" :key="i" :class="{ governing: ref.governing }">
        <h3>{{ refLabel(ref) }}</h3>
        <p>{{ ref.text }}</p>
      </div>
      <div v-if="readingGuidance.specialText">
        <h3>{{ specialLabel }}</h3>
        <p>{{ readingGuidance.specialTextContent }}</p>
      </div>
    </section>
    ```
  - `refLabel(ref)` → `${t('readingGuidance.' + ref.hexagram)} · ${ref.kind === 'judgment'
    ? t('readingGuidance.judgment') : t('hexagramDetail.line', { position: ref.position })}` +
    ` · ${t('readingGuidance.governing')}` when `ref.governing` and `refs.length > 1`.
  - `specialLabel` → `${t('readingGuidance.primary')} · ${t('readingGuidance.' +
    (readingGuidance.specialText === 'use-nine' ? 'useNine' : 'useSix'))}`.
  - scoped `.governing` style: a left accent border / `--p-primary-color`.
- i18n `readingGuidance.*` (en + uk):
  - `title` = "How to read this cast" / "Як читати цей каст"
  - `primary` / `resulting` / `judgment` / `governing` / `useNine` / `useSix`
  - `rule.no-changing-lines` … `rule.six-changing-lines` — one sentence each (static, no
    interpolation; the concrete positions are visible in the ref labels below).

## 4. Verify

`cd packages/yijing-core && composer test && composer stan && composer lint`; same for
`apps/api`; then `npm run verify`. Browser: cast (or open) consultations with 0 / 1 / 2 / 6
changing lines and confirm the panel names the rule and shows the right operative text with the
governing marker; check a Qián all-changing cast shows "Use Nine".


## Verification note (2026-08-28)

- yijing-core: 68 tests (11 new `CastReadingTest` + 1 catalog test), stan + cs-fixer clean.
- apps/api: 327 tests (4 new — no-changing / one-changing / all-six use-nine / detail-only),
  stan + cs-fixer clean. `npm run verify` green (web 210 tests incl. 2 new ConsultationPage).
- Live pass against the running stack: created manual casts with 0 / 2 / 6 changing lines →
  `GET /api/consultations/{id}.readingGuidance` returned the right rule, refs (primary judgment
  / primary lines 2+5 with line 5 governing) and resolved Legge text; n=6 on Qian →
  `specialText: 'use-nine'` + `specialTextContent`. The consultation page's "Як читати цей каст"
  panel renders the rule sentence, each ref with a `Первинна · Лінія N` label, and a
  `--p-primary-color` left-accent on the governing ref; the all-changing case shows
  `Первинна · Використання дев’яток` + the Use Nine text with a special rule sentence.
