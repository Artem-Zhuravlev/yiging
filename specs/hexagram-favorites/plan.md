# Plan — Hexagram Favorites (SPEC-031)

**Depends on spec status:** `approved`

## Technical approach

- Migration `2026_08_21_000003_create_favorite_hexagrams.php`: `CREATE TABLE favorite_hexagrams
  (king_wen_number INTEGER PRIMARY KEY);`.
- `App\Hexagrams\HexagramFavoritesRepository` (interface) / `SqliteHexagramFavoritesRepository`:
  `isFavorite()` (`SELECT 1 ... LIMIT 1`), `add()` (`INSERT OR IGNORE INTO favorite_hexagrams
  (king_wen_number) VALUES (:n)`), `remove()` (`DELETE FROM favorite_hexagrams WHERE
  king_wen_number = :n`), `allFavoriteNumbers()` (`SELECT king_wen_number FROM
  favorite_hexagrams` → `list<int>`).
- `HexagramController`: gains `private readonly HexagramFavoritesRepository $favorites;` built in
  a new constructor; the existing "no constructor" comment is deleted (its premise — this
  controller never touches the database — is no longer true). `toJson()` gains a `bool $favorite`
  parameter threaded from each call site: `index()` computes `allFavoriteNumbers()` once into a
  set and passes `in_array($hexagram->kingWenNumber, $favorites, true)` per item;
  `show()`/`fromLines()`/`compare()` each resolve their own `favorite` — `fromLines()`/`compare()`
  pass `false` unconditionally (matching the spec's "not applicable there" scope decision, made
  explicit in code rather than silently always-false-looking-like-a-bug).
  New `favoriteToggle(Request, array $vars)`-style pair: `markFavorite()` (`PUT`) validates the
  number resolves via `Hexagram::fromKingWenNumber()` (catch `InvalidArgumentException` → `404`,
  the same pattern `show()` already uses), then `$this->favorites->add()`, `204`.
  `unmarkFavorite()` — same existence check, then `remove()`, `204`.
- `config/routes.php` gains `PUT /api/hexagrams/{id}/favorite`, `DELETE
  /api/hexagrams/{id}/favorite`.
- `entities/hexagram/model.ts`'s `Hexagram` gains `favorite: boolean`.
- `entities/hexagram/api.ts` gains `markHexagramFavorite(n)`/`unmarkHexagramFavorite(n)` (`apiPut`/
  `apiDelete` — both new to `shared/api/http.ts`; unlike `apiGet`/`apiPost`/`apiPatch` these
  return `Promise<void>` and never attempt to parse a response body, since every endpoint that
  uses them responds `204 No Content` with nothing to parse).
- `HexagramListPage.vue`: a `favoritesOnly` ref + filter stage (same shape as
  `ConsultationHistoryPage`'s), a star button per card calling a toggle function that PUTs/DELETEs
  and updates that one item's `favorite` in local state (no full re-fetch needed).
- `HexagramDetailPage.vue`: a toggle button mirroring `ConsultationPage`'s favorite button
  exactly (same `FormState`-style submitting/error handling).

## Architecture decisions

- **`toJson()` takes an explicit `bool $favorite` parameter rather than looking it up per-call
  internally.** Keeps the one-bulk-query-for-index() optimization (REQ-HEXFAV-006) honest — if
  `toJson()` queried the repository itself, `index()`'s 64-item loop would silently regress into
  64 queries the next time someone touched this code without noticing.
  `fromLines()`/`compare()` passing `false` literally, rather than omitting the field, keeps the
  response shape identical across every hexagram-returning endpoint (a consumer can always read
  `.favorite` without a shape check).
- **`shared/api/http.ts` gains `apiPut`/`apiDelete`.** The app has had no PUT/DELETE endpoint
  until now; adding the two missing verbs to the shared client (mirroring `apiPost`/`apiPatch`
  exactly) is simpler than a one-off fetch call in `entities/hexagram/api.ts`.

## Affected areas

- `apps/api/database/migrations/2026_08_21_000003_create_favorite_hexagrams.php` (new)
- `apps/api/src/Hexagrams/HexagramFavoritesRepository.php` (new)
- `apps/api/src/Hexagrams/SqliteHexagramFavoritesRepository.php` (new)
- `apps/api/src/Hexagrams/HexagramController.php`
- `apps/api/config/routes.php`
- `apps/api/tests/Hexagrams/SqliteHexagramFavoritesRepositoryTest.php` (new)
- `apps/api/tests/Hexagrams/HexagramControllerTest.php`
- `apps/web/src/shared/api/http.ts`
- `apps/web/src/entities/hexagram/model.ts`
- `apps/web/src/entities/hexagram/api.ts`
- `apps/web/src/pages/hexagrams/HexagramListPage.vue`
- `apps/web/src/pages/hexagrams/HexagramListPage.spec.ts`
- `apps/web/src/pages/hexagrams/HexagramDetailPage.vue`
- `apps/web/src/pages/hexagrams/HexagramDetailPage.spec.ts`

## Data / schema changes

New table `favorite_hexagrams`, no relation to any existing table.

## Risks / open questions

- None currently open.
