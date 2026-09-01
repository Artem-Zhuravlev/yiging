# Plan — Yarrow-Stalk Casting Method (SPEC-055)

## 1. `apps/api` casting layer

### `src/Casting/RandomSource.php` (new interface)
```php
interface RandomSource
{
    /** A uniform random integer in [$min, $max], both inclusive. */
    public function intBetween(int $min, int $max): int;
}
```

### `src/Casting/SystemRandomSource.php` (new)
```php
final class SystemRandomSource implements RandomSource
{
    public function intBetween(int $min, int $max): int
    {
        return random_int($min, $max);
    }
}
```

### `src/Casting/YarrowStalkMethod.php` (new)
- `final readonly class YarrowStalkMethod implements DivinationMethod`
- ctor `private RandomSource $random`
- `cast()` — loop position 1..6, `$lines[] = $this->drawLine($position)`, `Hexagram::fromLines`.
- `drawLine(int $position): Line`:
  ```php
  // Zhu Xi yarrow-stalk line probabilities (sixteenths):
  //   6 old yin 1 · 7 young yang 5 · 8 young yin 7 · 9 old yang 3
  // — moving lines rarer than the three-coin method's 2 · 6 · 6 · 2, and a moving
  // yang (9) three times as likely as a moving yin (6). Idealised distribution
  // (each of the three stalk "changes" idealised to 3/4 and 1/2, per Zhu Xi).
  $draw = $this->random->intBetween(1, 16);
  return match (true) {
      $draw <= 1  => new Line($position, LinePolarity::Yin, changing: true),
      $draw <= 6  => new Line($position, LinePolarity::Yang, changing: false),
      $draw <= 13 => new Line($position, LinePolarity::Yin, changing: false),
      default     => new Line($position, LinePolarity::Yang, changing: true),
  };
  ```

### `src/Readings/CastingMethodName.php`
- add `case Yarrow = 'yarrow';`

### `src/Readings/ConsultationController.php`
- `use App\Casting\SystemRandomSource; use App\Casting\YarrowStalkMethod;`
- `resolveDivinationMethod()` match arm: `CastingMethodName::Yarrow => new YarrowStalkMethod(new SystemRandomSource()),`

### Tests
- `tests/Casting/Support/FakeRandomSource.php` (new) — ctor `list<int> $values`, `intBetween()`
  pops the next (ignoring bounds, like `FakeCoinTosser`), raises `\OutOfBoundsException` when
  empty.
- `tests/Casting/YarrowStalkMethodTest.php`:
  - data provider over `[1→yin/changing], [2→yang/stable], [6→yang/stable], [7→yin/stable],
    [13→yin/stable], [14→yang/changing], [16→yang/changing]` — feed the value ×6, assert all
    6 lines match.
  - `testCastBuildsSixLinesInPositionOrder` — feed `[1,6,7,13,14,16]`, assert positions 1..6
    and the per-line value.
  - `testExhaustedSourceThrows` — 2 values, `cast()` raises `\OutOfBoundsException`.
- `tests/Readings/ConsultationControllerTest.php`:
  - `testCreateWithYarrowMethodReturns201AndEchoesMethod` — POST `{"method":"yarrow"}` → 201,
    body `method === 'yarrow'`; GET `/{id}` → `method === 'yarrow'`.

`composer test` / `stan` / `lint` green.

## 2. `apps/web`

### `entities/consultation/model.ts`
- `SelectableCastingMethod = 'three_coins' | 'yarrow' | 'manual'` (update the doc comment: still
  excludes `random`).
- `NewConsultationRequest`:
  ```ts
  export type NewConsultationRequest = (
    | { question: string; method: 'three_coins' }
    | { question: string; method: 'yarrow' }
    | { question: string; method: 'manual'; lines: ManualLine[] }
  ) & Partial<ConsultationContext> & { followUpToConsultationId?: string }
  ```

### `pages/consultations/NewConsultationPage.vue`
- method fieldset: a third `<label>` with `<RadioButton v-model="method" name="method"
  value="yarrow" />` + `{{ t('newConsultation.yarrow') }}`, placed between Three Coins and
  Manual.
- a hint under the fieldset: `<p v-if="method === 'yarrow'" class="text-xs text-color-secondary
  m-0">{{ t('newConsultation.yarrowHint') }}</p>`.
- submit payload — replace the two-way ternary:
  ```ts
  ...(method.value === 'manual'
    ? { question: question.value, method: 'manual' as const, lines: lines.value }
    : { question: question.value, method: method.value }),
  ```
  (`method.value` here is `'three_coins' | 'yarrow'`, a valid discriminant; cast to the union
  only if tsc complains.)

### i18n (`en.ts` + `uk.ts`) — `newConsultation`
- `yarrow`: "Yarrow stalk" / "Стебла деревію"
- `yarrowHint`: EN — "The traditional 50-stalk method. Moving lines are rarer than with coins,
  and a moving yang is three times as likely as a moving yin." UK — parallel.

### Tests
- `pages/consultations/NewConsultationPage.spec.ts`:
  - `submits a yarrow request` — select `value="yarrow"`, submit, expect
    `createConsultation` called with `{ question, method: 'yarrow' }`.
- `entities/consultation/api.spec.ts` — no change needed (method is a free string in fixtures),
  but add a quick `createConsultation` yarrow-method assertion if it fits the existing style.

## 3. Verify

`cd apps/api && composer test && composer stan && composer lint`; `npm run verify`.
Browser (php dev server + web): New Consultation → pick Yarrow stalk → hint appears → Cast →
lands on the detail page, `method` reads `yarrow`. `curl -XPOST .../api/consultations
-d '{"question":"…","method":"yarrow"}'` → 201 with `"method":"yarrow"`.

## Verification note (2026-09-01)

- apps/api: 354 tests (+10 — `YarrowStalkMethodTest` ×9, `ConsultationControllerTest` ×1),
  phpstan level 8 + php-cs-fixer clean.
- `npm run verify` green: web lint/typecheck/test (212, incl. the new `NewConsultationPage`
  yarrow-submit test) + build; api; yijing-core 75.
- Distribution check: 20 000 casts × 6 lines → 6:0.0618 / 7:0.3140 / 8:0.4381 / 9:0.1862,
  matching the classical 1/16, 5/16, 7/16, 3/16 within sampling noise.
- Live pass: `POST /api/consultations {"method":"yarrow"}` → 201 with `method:"yarrow"`,
  echoed on `GET /{id}`; an unknown method still `422`. Browser: New Consultation offers
  "Yarrow stalk", the probability hint shows only when it is selected, and a cast lands on the
  detail page reading `yarrow`.
