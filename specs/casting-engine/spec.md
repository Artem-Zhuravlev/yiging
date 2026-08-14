# SPEC-004 — Casting Engine

**Status:** verified
**Owner:** unassigned
**Last updated:** 2026-08-14

## Problem

SPEC-002 (I Ching Domain Model) only covers what happens once six line values already exist —
it deliberately left out how those six line values get produced in the first place ("Casting
methods ... that's SPEC-004"). Without a casting engine, nothing can turn a user's action (a
coin toss, a manually entered line, a dev/test shortcut) into the `Hexagram` that SPEC-002
already knows how to interpret structurally.

## Purpose

Define a small set of interchangeable methods that each produce a primary `Hexagram` (with
changing lines correctly marked), so the rest of the platform (Readings, HTTP API, frontend)
can ask for "a cast" without caring which method produced it.

## Scope

- `DivinationMethod` interface — one contract, multiple implementations.
- `ThreeCoinsMethod` — the traditional three-coin casting algorithm (6 tosses, 3 coins each).
- `ManualMethod` — user supplies all six lines directly (for people casting with physical
  yarrow stalks/coins offline and entering the result, or deliberately setting a hexagram).
- `RandomMethod` — fast, non-traditional random cast for development/testing/demo use.
- A `CoinTosser` (random-source) abstraction so casting is deterministic and testable without
  relying on true randomness in tests.
- Reuse of the existing `Yijing\Core\Hexagram` / `Line` / `LinePolarity` types as the output —
  no new "pattern" type. A cast produces exactly what `Hexagram::fromLines()` already models:
  six lines, some possibly changing.

## Out of scope

- The `Consultation` aggregate (question, timestamp, notes, tags) that wraps a cast result —
  that's SPEC-005 (Readings), which also owns persistence.
- Any HTTP endpoint (`POST /api/consultations`) — defined in SPEC-005, which is the first
  spec with an actual reason to expose casting over HTTP.
- Yarrow-stalk casting method (slower, more traditional than three coins) — not needed for
  MVP; the architecture (one more `DivinationMethod` implementation) already accommodates it
  later without changes to this spec.
- Any frontend UI for casting — SPEC-00x (Consultation Flow), later.

## User behavior

`Casting` has no UI or HTTP surface of its own — it is consumed by `apps/api/src/Readings`
(SPEC-005). "User behavior" here means the API contract other code relies on:

```
$method = new ThreeCoinsMethod($coinTosser);
$hexagram = $method->cast();
  → Hexagram with 6 lines, each possibly marked changing, per the 6/7/8/9 coin-sum rule

$method = new ManualMethod($sixLineSpecs);
$hexagram = $method->cast();
  → Hexagram built directly from the given lines; throws on malformed input

$hexagram->getResultingHexagram();
  → already defined in SPEC-002; consumers use it directly once they have a cast
```

## Functional requirements

### DivinationMethod

- **REQ-CAST-001** — `DivinationMethod` MUST define a single `cast(): Hexagram` method that
  returns a `Yijing\Core\Hexagram` with all 6 lines populated (positions 1–6, bottom to top),
  each carrying the correct polarity and `changing` flag for that method.
- **REQ-CAST-002** — Every implementation MUST return a hexagram that satisfies all invariants
  already enforced by `Hexagram::fromLines()` (exactly 6 lines, valid King Wen identification) —
  no implementation may bypass or duplicate that construction logic.

### ThreeCoinsMethod

- **REQ-CAST-003** — `ThreeCoinsMethod::cast()` MUST perform 6 independent line-tosses (bottom
  to top), each consisting of 3 independent coin tosses via the injected `CoinTosser`.
- **REQ-CAST-004** — Each coin toss MUST resolve to heads (value 3) or tails (value 2). The sum
  of the 3 coins for a line MUST determine the line per the traditional mapping:
  - 6 (tails+tails+tails) → old yin (yin, changing)
  - 7 (two tails + one heads) → young yang (yang, stable)
  - 8 (two heads + one tails) → young yin (yin, stable)
  - 9 (heads+heads+heads) → old yang (yang, changing)
- **REQ-CAST-005** — `CoinTosser` MUST be an interface (`tossCoin(): Coin` or equivalent)
  injected into `ThreeCoinsMethod`, so tests can supply a fixed/deterministic sequence instead
  of relying on true randomness. A default implementation using PHP's CSPRNG
  (`random_int`) MUST be provided for real use.

### ManualMethod

- **REQ-CAST-006** — `ManualMethod` MUST accept exactly 6 line specifications (polarity +
  changing flag, or one of the 4 named states: young yang/young yin/old yang/old yin) at
  construction and MUST throw on any count other than 6.
- **REQ-CAST-007** — `ManualMethod::cast()` MUST build the `Hexagram` from exactly the given
  lines with no randomness involved.

### RandomMethod

- **REQ-CAST-008** — `RandomMethod::cast()` MUST produce a hexagram using the same injected
  `CoinTosser` source (for consistency and testability) but MAY use a simpler, non-traditional
  distribution (e.g. uniform yin/yang, uniform changing/stable) — it exists for
  development/testing speed, not doctrinal accuracy, and MUST be documented as such in its
  docblock so it is never mistaken for a traditional method.

## Non-functional requirements

- **REQ-CAST-009** — This module MUST live in `apps/api/src/Casting`, not
  `packages/yijing-core` — casting requires a randomness boundary (impure by nature), which
  violates `packages/yijing-core`'s zero-impurity rule (SPEC-001 REQ-ARCH-005, SPEC-002
  REQ-DM-002). It depends on `packages/yijing-core` for `Hexagram`/`Line`/`LinePolarity`, never
  the reverse.
- **REQ-CAST-010** — `ThreeCoinsMethod`'s traditional distribution (1/8 old yin, 3/8 young
  yang, 3/8 young yin, 1/8 old yang per line) MUST be verified by exhaustively enumerating all
  8 equally-likely 3-coin outcomes against a fake `CoinTosser`, not by statistical sampling.
- **REQ-CAST-011** — No implementation may use PHP's non-cryptographic `rand()`/`mt_rand()`
  directly; the default `CoinTosser` MUST use `random_int()`.

## Data requirements

None — this module is stateless; it has no persistence of its own (SPEC-005 owns storing the
result inside a `Consultation`).

## API requirements

None — see "Out of scope."

## Edge cases

- All 6 lines resolve to old yang (9,9,9,9,9,9) → primary hexagram is 1 (乾, all yang), every
  line changing, resulting hexagram is 2 (坤, all yin) — must fall out of `getResultingHexagram()`
  with no special-casing in this module.
- Zero changing lines (e.g. all coin-sums are 7 or 8) → resulting hexagram equals primary
  hexagram, per SPEC-002 REQ-HX-007 — this module does not need to special-case "no change."
- `ManualMethod` given fewer/more than 6 lines, duplicate positions, or positions outside 1–6 →
  throws `\InvalidArgumentException`, same failure style as `Hexagram::fromLines()`.

## Acceptance criteria

- [x] `DivinationMethod` interface exists with a single `cast(): Hexagram` method.
- [x] `ThreeCoinsMethod` implements the traditional 6/7/8/9 mapping, verified by exhaustively
      testing all 8 possible 3-coin outcomes per line against a fake `CoinTosser`.
- [x] `ManualMethod` builds a hexagram from exactly 6 supplied lines and rejects malformed
      input.
- [x] `RandomMethod` produces a valid hexagram via the same `CoinTosser` abstraction, clearly
      documented as non-traditional.
- [x] `CoinTosser` interface + a `random_int()`-backed default implementation exist; no direct
      `rand()`/`mt_rand()` usage anywhere in this module.
- [x] All three methods are covered by unit tests using a fake, deterministic `CoinTosser`
      (no test relies on true randomness / flaky assertions).
- [x] This module has zero dependency on HTTP, PDO/SQLite, or AI — only on
      `packages/yijing-core` and PHP itself.

`apps/api/src/Casting` implements `Coin`, `CoinTosser` + `RandomIntCoinTosser`,
`DivinationMethod`, `ThreeCoinsMethod`, `ManualMethod`, `RandomMethod` — 8 tests (5 direct +
the 8-case `ThreeCoinsMethod` data provider), 134 assertions across `apps/api`'s full suite (18
tests). `npm run verify` passes end to end (web + api + yijing-core).
