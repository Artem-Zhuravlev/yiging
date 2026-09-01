# Plan — Line Dynamics (SPEC-053)

## 1. `packages/yijing-core`

### New
- `src/LineDynamic.php` — `final readonly class`:
  ```php
  public function __construct(
      public int $position,
      public bool $correctPosition,
      public bool $central,
      public bool $centralAndCorrect,
      public int $correspondsWith,
      public bool $corresponds,
      public bool $ridesFirmBelow,
      public bool $supportsFirmAbove,
  ) {}
  public function toArray(): array { /* all 8 props */ }
  ```
- `src/LineDynamics.php` — `final readonly class`:
  - `public array $lines` (`list<LineDynamic>`, 6, position order).
  - `public static function of(Hexagram $hexagram): self`:
    - index the 6 lines by position (`$hexagram->lines` is position order 1..6).
    - per position `p` (1..6):
      - `yang = $line->isYang()`
      - `oddPosition = $p % 2 === 1`
      - `correctPosition = ($oddPosition && $yang) || (!$oddPosition && !$yang)`
      - `central = $p === 2 || $p === 5`
      - `partner = match ($p) { 1=>4, 2=>5, 3=>6, 4=>1, 5=>2, 6=>3 }`
      - `corresponds = $lines[$partner]->isYang() !== $yang`
      - `ridesFirmBelow = !$yang && $p > 1 && $lines[$p - 1]->isYang()`
      - `supportsFirmAbove = !$yang && $p < 6 && $lines[$p + 1]->isYang()`
  - `toArray(): list<array>` → `array_map(fn ($d) => $d->toArray(), $this->lines)`.

### Tests — `tests/LineDynamicsTest.php`
- hexagram 63 (pattern `101010` bottom-to-top = yang,yin,yang,yin,yang,yin): every
  `correctPosition` true; every `corresponds` true; line 2 `centralAndCorrect` true, line 5
  `centralAndCorrect` true.
- hexagram 64 (pattern `010101`): every `correctPosition` false; every `corresponds` true;
  `centralAndCorrect` false at 2 and 5.
- hexagram 1 (`111111`): no line corresponds (all same polarity); line 5 correct + central →
  `centralAndCorrect` true; line 2 central but yang → `correctPosition` false,
  `centralAndCorrect` false; every `ridesFirmBelow`/`supportsFirmAbove` false (no yin lines).
- hexagram 44 姤 (`011111` = yin at 1, yang 2..6): line 1 is yin with a yang above →
  `supportsFirmAbove` true, `ridesFirmBelow` false (no line below); yang lines all false.
- a pattern with a yin above a yang, e.g. `110000`? compute: choose one where position p is
  yin and p-1 is yang → `ridesFirmBelow` true. `100000` (yang at 1, yin 2..6): line 2 is yin,
  line 1 is yang → line 2 `ridesFirmBelow` true; line 2 also has yin above → `supportsFirmAbove`
  false.

`composer test` / `stan` / `lint` green.

## 2. API (`apps/api`)

- `HexagramController::toJson()` is shared by `index()` and `show()`. Add a `$includeDynamics`
  param (default false); `show()` and `fromLines()` pass `true`, `index()` passes nothing.
  ```php
  private function toJson(Hexagram $hexagram, bool $favorite, bool $includeDynamics = false): array
  {
      $json = [ ...existing... ];
      if ($includeDynamics) {
          $json['lineDynamics'] = LineDynamics::of($hexagram)->toArray();
      }
      return $json;
  }
  ```
- `use Yijing\Core\LineDynamics;`
- Tests (`HexagramControllerTest`):
  - `GET /api/hexagrams/63` → `lineDynamics` has 6 entries, every `correctPosition` true, every
    `corresponds` true.
  - `GET /api/hexagrams/1` → line 5 `centralAndCorrect` true; line 2 `centralAndCorrect` false.
  - `GET /api/hexagrams` (list) → items have **no** `lineDynamics` key.
  - `GET /api/hexagrams/from-lines?lines=...` for an all-yang cast → `lineDynamics` present.

## 3. Frontend (`apps/web`)

- `entities/hexagram/model.ts`:
  ```ts
  export interface LineDynamic {
    position: number
    correctPosition: boolean
    central: boolean
    centralAndCorrect: boolean
    correspondsWith: number
    corresponds: boolean
    ridesFirmBelow: boolean
    supportsFirmAbove: boolean
  }
  // Hexagram:
  lineDynamics?: LineDynamic[]
  ```
  Optional → the many `Hexagram` test fixtures (list page, home, casting reveal, etc.) don't
  need touching; only assertions that specifically check the new section change.
- `HexagramDetailPage.vue`: a `<section v-if="state.hexagram.lineDynamics">` after "Line Texts":
  - **Correspondence summary** — derive the 3 pairs from `lineDynamics` (positions 1,2,3 whose
    `correspondsWith` is 4,5,6): for each, a line "Lines {a} & {b} — {corresponds ?
    t('lineDynamics.corresponds') : t('lineDynamics.noCorrespondence')}"; the 2–5 row gets
    `font-medium`.
  - **Per-line table** — `[...lineDynamics].reverse()` (6→1). Columns: `#`, line
    (`t('common.yang')`/`t('common.yin')` — need the polarity; `lineDynamics` doesn't carry it,
    so read it from `state.hexagram.lines[position-1].polarity`), position status
    (`correctPosition ? t('lineDynamics.correct') : t('lineDynamics.improper')`), centrality
    (`centralAndCorrect ? t('lineDynamics.centralCorrect') : central ? t('lineDynamics.central')
    : '—'`), correspondence (`t('hexagramDetail.line',{position: correspondsWith})` +
    `corresponds ? '正應' : '敵應'` via keys), adjacency (`ridesFirmBelow ?
    t('lineDynamics.rides') : supportsFirmAbove ? t('lineDynamics.supports') : '—'`).
  - a `<p class="text-xs text-color-secondary">` help line.
  - Reuse the `.compare-table` styling pattern from `HexagramComparePage` (bordered rows) or a
    small scoped style.
- i18n `lineDynamics.*` (en + uk): `title`, `help`, column headers (`colLine`, `colPosition`,
  `colCentral`, `colCorresponds`, `colAdjacency`), `correct` = "Correct (當位)", `improper` =
  "Improper (失位)", `central` = "Central (中)", `centralCorrect` = "Central & correct (中正)",
  `corresponds` = "correspond (正應)", `noCorrespondence` = "no correspondence (敵應)",
  `rides` = "rides the firm (乘剛)", `supports` = "supports the firm (承)",
  `correspondenceSummary` = "Correspondence".

## 4. Verify

`cd packages/yijing-core && composer test && composer stan && composer lint`; same `apps/api`;
`npm run verify`. Browser: `/hexagrams/63` (all correct, all correspond), `/hexagrams/1` (line
5 中正, no correspondences), `/hexagrams/44` (line 1 支持 the firm), light + dark; editor page
live-updates the section as lines toggle.


## Verification note (2026-08-28)

- yijing-core: 75 tests (7 new `LineDynamicsTest`), stan + cs-fixer clean.
- apps/api: 331 tests (4 new `HexagramControllerTest`), stan + cs-fixer clean. `npm run verify`
  green (web 211 tests incl. one new `HexagramDetailPage` test).
- Live pass: `GET /api/hexagrams/63` → every line `correctPosition` + `corresponds`, positions
  2 & 5 `centralAndCorrect`, positions 2/4/6 (yin) `ridesFirmBelow`, 2/4 also `supportsFirmAbove`;
  `/hexagrams/44` line 1 (yin below yang) → `supportsFirmAbove`; `GET /api/hexagrams` has no
  `lineDynamics`. Detail page `/hexagrams/1` shows the correspondence summary (all 敵應) and a
  per-line table with line 5 中正 / line 2 central-not-correct; `/hexagrams/63` shows all 正應
  and dual 乘剛 · 承 for the sandwiched yin lines. (Screenshots blocked by the pane's
  scroll-reset; verified via DOM reads.)
