# SPEC-055 — Yarrow-Stalk Casting Method

**Status:** implemented
**Owner:** unassigned
**Last updated:** 2026-09-01

## Problem

The app offers three casting methods — `three_coins`, `manual`, and the non-traditional
`random` — but not the **yarrow-stalk** method (蓍法), the oldest and, for many practitioners,
the "real" one. It is not just a slower ritual with the same result: it produces a *different
probability distribution* for the four line values, which materially changes how a reading
behaves.

| line value        | three coins | yarrow stalk |
|-------------------|-------------|--------------|
| 6 old yin (moving)   | 2/16       | **1/16**     |
| 7 young yang         | 6/16       | **5/16**     |
| 8 young yin          | 6/16       | **7/16**     |
| 9 old yang (moving)  | 2/16       | **3/16**     |

With coins, a moving line is equally likely to be yin or yang. With stalks, moving lines are
rarer overall and a *moving yang* (9) is three times as likely as a *moving yin* (6) — the
classical asymmetry that commentaries assume. A practitioner who casts with stalks is getting a
meaningfully different oracle, and the app currently cannot represent that.

## Purpose

Add `yarrow` as a first-class casting method: a `YarrowStalkMethod` in the casting layer that
draws each line on the Zhu Xi (朱熹) yarrow-stalk distribution above, wired through the
consultation-create endpoint and offered on the New Consultation form alongside Three Coins and
Manual.

## Scope

### `apps/api` — casting layer

- `App\Casting\RandomSource` — a tiny interface `intBetween(int $min, int $max): int` (inclusive
  both ends), with `SystemRandomSource` (`random_int`) for production and a fake for tests. The
  existing `CoinTosser` boundary only yields a 2-way coin; the yarrow draw needs a bounded
  uniform integer, so it gets its own seam — same pattern, different shape.
- `App\Casting\YarrowStalkMethod implements DivinationMethod` — builds 6 `Line`s bottom-to-top;
  each line's value is drawn once from `RandomSource->intBetween(1, 16)` and mapped:
  `1 → 6` old yin (changing), `2..6 → 7` young yang, `7..13 → 8` young yin, `14..16 → 9` old
  yang (changing). A class docblock states these are the idealised Zhu Xi yarrow-stalk
  probabilities (the physical three-changes procedure idealised to 3/4 and 1/2 per change, as in
  standard treatments) and contrasts them with `ThreeCoinsMethod`.
- `App\Readings\CastingMethodName` gains `case Yarrow = 'yarrow'`.
- `ConsultationController::resolveDivinationMethod()` maps `CastingMethodName::Yarrow` to
  `new YarrowStalkMethod(new SystemRandomSource())`.

### `apps/web`

- `entities/consultation/model.ts`: `SelectableCastingMethod` gains `'yarrow'`;
  `NewConsultationRequest` gains a `{ question; method: 'yarrow' }` variant.
- `NewConsultationPage.vue`: a third method radio ("Yarrow stalk") between Three Coins and
  Manual; the submit payload sends `method: 'yarrow'` when it is selected; a one-line hint under
  the method fieldset when `yarrow` is chosen, explaining the rarer-moving-lines difference.
- Localised (en + uk): `newConsultation.yarrow`, `newConsultation.yarrowHint`.

## Out of scope

- **Simulating the physical 49-stalk, three-changes ritual** stalk-by-stalk. The idealised
  16-way draw yields exactly the classical distribution; a naïve pile-split simulation does
  *not* (it lands near 22/43, not 1/2, per change) and would be less faithful, not more.
- **A casting animation** specific to yarrow stalks (SPEC-042 is coin-flavoured); the existing
  reveal is reused as-is.
- **Retro-labelling or migrating** existing `three_coins` / `random` consultations.
- **The 序卦傳 (Xugua / Sequence of Hexagrams) commentary** and **the original Chinese
  hexagram / line text with pinyin** — the other two "deeper source material" candidates. Each
  is a sourced-transcription effort of its own and gets its own spec.
- Changing how `method` is displayed on the consultation / history pages (still the raw string,
  as today for every method).

## Functional requirements

- **REQ-YS-001** — `YarrowStalkMethod::cast()` returns a `Hexagram` of 6 lines in position
  order 1..6, each derived from one `RandomSource` draw.
- **REQ-YS-002** — The draw maps `1 → (yin, changing)`, `2..6 → (yang, stable)`,
  `7..13 → (yin, stable)`, `14..16 → (yang, changing)` — i.e. 1/16 old yin, 5/16 young yang,
  7/16 young yin, 3/16 old yang.
- **REQ-YS-003** — `POST /api/consultations` with `{"method":"yarrow"}` creates and persists a
  consultation whose stored `method` is `yarrow`; `GET` echoes it back.
- **REQ-YS-004** — The New Consultation form offers Yarrow stalk as a method and submits
  `method: 'yarrow'` (no `lines`) when it is chosen.

## Non-functional requirements

- **REQ-YS-020** — `YarrowStalkMethod` is deterministic under a fake `RandomSource` and fully
  unit-tested: every one of the four buckets and its boundary values, 6-line position order,
  and an exhausted source raising.
- **REQ-YS-021** — `phpstan` level 8 + `php-cs-fixer` clean in `apps/api`; `npm run verify`
  passes; existing casting/consultation tests stay green.
- **REQ-YS-022** — New UI strings localised (en + uk).

## Data requirements

None — `method` is already a free `TEXT` column persisting whatever `CastingMethodName->value`
is; `yarrow` needs no migration.

## API requirements

- `POST /api/consultations` accepts `method: "yarrow"` (no extra fields) and behaves exactly as
  for `three_coins` otherwise.
- No response-shape change: `method` in every consultation payload can now also be `"yarrow"`.

## Edge cases

- `RandomSource->intBetween(1, 16)` returning each boundary (1, 6, 7, 13, 14, 16) maps to the
  expected line value — covered explicitly by tests.
- A fake source with fewer than 6 values raises (mirrors `FakeCoinTosser`'s `OutOfBoundsException`).
- `method: "yarrow"` with a stray `lines` array in the body — `lines` is simply ignored (same
  as `three_coins` today).

## Acceptance criteria

- [x] `YarrowStalkMethod` under a fake source produces the exact 1/5/7/3-in-16 mapping and
      6 position-ordered lines; an exhausted source raises.
- [x] `POST /api/consultations {"method":"yarrow"}` → 201, and `GET` returns `method: "yarrow"`.
- [x] The New Consultation form has a Yarrow stalk option that submits `method: 'yarrow'`.
- [x] `npm run verify` passes; `phpstan` + `php-cs-fixer` clean.
