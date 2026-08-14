# Plan — Casting Engine (SPEC-004)

**Depends on spec status:** `approved`

## Technical approach

`final` classes/enums in `App\Casting` (`apps/api/src/Casting`), `declare(strict_types=1)`,
consuming `Yijing\Core\Hexagram`/`Line`/`LinePolarity` — no new pattern/DTO type, since `Line`
already models all 4 line states (polarity × changing) and `Hexagram::fromLines()` already
renumbers/validates whatever 6 lines it's given.

- `Coin` — enum, `Heads`/`Tails`, with `value(): int` (3/2) for the traditional coin-sum rule.
- `CoinTosser` — interface, single `toss(): Coin` method. The randomness boundary.
- `RandomIntCoinTosser` — default `CoinTosser`, backed by `random_int(0, 1)`.
- `DivinationMethod` — interface, single `cast(): Hexagram` method.
- `ThreeCoinsMethod` — takes a `CoinTosser`; for each of 6 positions (bottom to top), tosses 3
  coins, sums their values, maps 6/7/8/9 to old-yin/young-yang/young-yin/old-yang via `match`
  (exhaustive — a 3-coin sum can only be 6, 7, 8, or 9, so no `default` arm is needed).
- `ManualMethod` — takes a `list<Line>` at construction; validates `count() === 6`; `cast()`
  just calls `Hexagram::fromLines()`. No new "line spec" DTO — `Line` already covers all 4
  named states directly.
- `RandomMethod` — takes the same `CoinTosser`; 2 tosses per line (one for polarity, one for
  changing) instead of 3, uniform 50/50 each. Explicitly documented as non-traditional.

## Architecture decisions

- **Reuse `Yijing\Core\Hexagram`/`Line` as the output type** — introducing a parallel
  "HexagramPattern" (as the original plan sketch suggested) would duplicate what SPEC-002
  already validated and tested; every `DivinationMethod` implementation ends its work with
  exactly the same `Hexagram::fromLines()` call SPEC-002 already covers.
- **Casting lives in `apps/api`, not `packages/yijing-core`** — per `docs/coding-rules.md` and
  SPEC-002 REQ-DM-002, `yijing-core` derivation logic must be pure/deterministic; a coin toss is
  inherently non-deterministic, so the boundary sits at `apps/api/src/Casting`, which already
  depends on `yijing-core` for the domain types it composes.
- **`CoinTosser` as an injected interface, not a static/global RNG call** — makes
  `ThreeCoinsMethod`'s traditional distribution exhaustively testable (8 fixed coin-triplets)
  instead of requiring statistical/flaky tests, and reused by `RandomMethod` for consistency
  (one randomness boundary in the whole module, not two).
- **No `default` arm in the coin-sum `match`** — 3 coins each worth 2 or 3 can only sum to
  6/7/8/9; PHPStan level 8 can prove this exhaustive from `Coin::value()`'s return type, so an
  unreachable `default` would just be dead code (violates "no half-finished
  implementations"/no dead code from the coding rules).

## Affected areas

- `apps/api/src/Casting/Coin.php`
- `apps/api/src/Casting/CoinTosser.php`
- `apps/api/src/Casting/RandomIntCoinTosser.php`
- `apps/api/src/Casting/DivinationMethod.php`
- `apps/api/src/Casting/ThreeCoinsMethod.php`
- `apps/api/src/Casting/ManualMethod.php`
- `apps/api/src/Casting/RandomMethod.php`
- `apps/api/tests/Casting/Support/FakeCoinTosser.php`
- `apps/api/tests/Casting/ThreeCoinsMethodTest.php`
- `apps/api/tests/Casting/ManualMethodTest.php`
- `apps/api/tests/Casting/RandomMethodTest.php`

## Data / schema changes

None — this module is stateless (REQ-CAST data requirements: none).

## Risks / open questions

- None currently open. If a future spec needs yarrow-stalk casting, it's a new
  `DivinationMethod` implementation — no change to this plan's interfaces.
