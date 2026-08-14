# Plan — Hexagram Explorer (SPEC-003)

**Depends on spec status:** `approved`

## Technical approach

Two small, symmetrical controllers. Neither declares a constructor at all: `Kernel::invoke()`
always calls `new $handler[0]($this->config)`, but PHP silently ignores extra constructor
arguments when a class declares no `__construct()` (verified directly), so a class with
nothing to configure just omits it rather than storing an unused `Config` property (PHPStan
level 8 correctly flags `property.onlyWritten` for that — caught while building this spec):

- `App\Hexagrams\HexagramController` (`apps/api/src/Hexagrams`):
  - `index()`: loop `1..64`, `Hexagram::fromKingWenNumber($n)` each, map to JSON, `200`.
  - `show(Request, array $vars)`: parse `$vars['id']` as an int (reject non-numeric ->
    treated as "not found" per REQ-HEXAPI-002), `Hexagram::fromKingWenNumber()`, catch its
    `\InvalidArgumentException` for out-of-range -> `404 {"error": "Not Found"}` (same shape
    `Kernel`'s own not-found already uses); else `200`.
- `App\Trigrams\TrigramController` (`apps/api/src/Trigrams`):
  - `index()`: loop `TrigramId::cases()`, build a `Trigram` per id (via
    `Trigram::fromLines()` against `TrigramCatalog::patternFor($id)`, the only way to get a
    `Trigram` instance today — no `Trigram::fromId()` shortcut exists, and adding one isn't
    needed for a one-call-site loop of 8), map to JSON, `200`.
- Hexagram JSON includes a nested trigram shape (`hexagramController`'s own `trigramToJson()`)
  — deliberately not shared with `TrigramController`'s output builder even though the fields
  overlap, since sharing would mean introducing a cross-module dependency between two
  controllers that otherwise have no reason to know about each other; the ~10 lines of overlap
  aren't worth that coupling.

## Architecture decisions

- **No new yijing-core API for "all 8 trigrams as Trigram instances".** `TrigramId::cases()` +
  `Trigram::fromLines(linesFromPattern(TrigramCatalog::patternFor($id)))` is a one-call-site,
  8-iteration loop — not worth a new `Trigram::fromId()` factory (mirrors the judgment call
  already made for not adding speculative API surface, contrast `Hexagram::fromKingWenNumber()`
  from the domain-model amendment, which *was* worth adding because it had two real, separate
  call sites: `SqliteConsultationRepository` and this spec).
- **Judgment/image/line-statement fields serialize as `null`, not omitted.** Matches SPEC-002's
  own choice to type them nullable rather than defaulting to placeholder text — the API
  contract should reflect the domain model's actual current state, not paper over it.
- **This module never touches PDO.** Confirmed by omission — no `Database::connect()` call
  appears anywhere in `Hexagrams`/`Trigrams`, unlike `Readings`.

## Affected areas

- `apps/api/src/Hexagrams/HexagramController.php`
- `apps/api/src/Trigrams/TrigramController.php`
- `apps/api/config/routes.php` (add 3 routes)
- `apps/api/tests/Hexagrams/HexagramControllerTest.php`
- `apps/api/tests/Trigrams/TrigramControllerTest.php`

## Data / schema changes

None.

## Risks / open questions

- None currently open.
