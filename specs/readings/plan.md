# Plan — Readings (SPEC-005)

**Depends on spec status:** `approved`

## Technical approach

`final`/`final readonly` classes in `App\Readings` (`apps/api/src/Readings`),
`declare(strict_types=1)`, following the same style as `App\Casting` (SPEC-004):

- `CastingMethodName` — enum, string-backed (`three_coins`, `manual`, `random`), one case per
  SPEC-004 `DivinationMethod` implementation. Deliberately just a label, not a reference to the
  `DivinationMethod` class itself (REQ-READ-012).
- `NoteLabel` — enum, string-backed (`before`, `after`, `later`).
- `ConsultationNote` — readonly value object: `label`, `text`, `createdAt`.
- `Consultation` — readonly aggregate: `id`, `question`, `method`, `primaryHexagram`,
  `resultingHexagram` (computed in `create()`, never passed separately), `createdAt`,
  `notes` (list, append-only via `withAddedNote()`), `tags` (list, append-only + deduped via
  `withAddedTag()`). `changingLinePositions()` reads `primaryHexagram->lines` on demand.
- `Clock` interface (`now(): DateTimeImmutable`) + `SystemClock` default — mirrors `CoinTosser`
  from SPEC-004: the only reason this exists is so tests don't depend on wall-clock time.
- `ConsultationIdGenerator` interface (`generate(): string`) + `UuidV4ConsultationIdGenerator`
  default, hand-rolled from `random_bytes(16)` per RFC 4122 §4.4 (set version/variant bits) —
  no new Composer dependency.
- `ConsultationRepository` interface: `save(Consultation): void`, `findById(string): ?Consultation`,
  `findAll(): list<Consultation>`.
- `SqliteConsultationRepository` — takes a `PDO` (reuses `App\Core\Database::connect()`).
  `save()` is a transaction: upsert the `consultations` row (`INSERT ... ON CONFLICT(id) DO
  UPDATE`), delete+reinsert `consultation_notes` and `consultation_tags` rows (simplest correct
  way to make notes/tags fully replace prior state per REQ-READ-007), inserting any new `tags`
  rows first (`INSERT OR IGNORE` on the unique `name`).

## Architecture decisions

- **King Wen number + changing positions, not raw lines, in storage** — `yijing-core`'s
  `HexagramCatalog::entryFor($number)['pattern']` (already public) plus
  `Hexagram::fromLines()` (already public) round-trip a `Hexagram` exactly, so storing all 6
  lines' polarity would just be redundant with data the King Wen number already implies.
- **Notes/tags are delete-and-reinsert on save, not diffed** — `Consultation` is small (a
  handful of notes/tags at most), so there is no performance reason to compute a diff; deleting
  and reinserting is simpler and can't drift from the aggregate's actual current state.
- **`Consultation` has zero dependency on `App\Casting`** — it accepts a `Hexagram` and a
  `CastingMethodName` label, not a `DivinationMethod`. Keeps "what happened" (Readings)
  decoupled from "what mechanism produced it" (Casting); a future casting method never requires
  a change here.
- **Hand-rolled UUIDv4** instead of a package (`ramsey/uuid`, `symfony/uid`) — one ~10-line
  function, keeps `composer.json` dependency-free per the project's portability stance
  (SPEC-001 REQ-ARCH-001/003).

## Affected areas

- `apps/api/src/Readings/CastingMethodName.php`
- `apps/api/src/Readings/NoteLabel.php`
- `apps/api/src/Readings/ConsultationNote.php`
- `apps/api/src/Readings/Consultation.php`
- `apps/api/src/Readings/Clock.php`
- `apps/api/src/Readings/SystemClock.php`
- `apps/api/src/Readings/ConsultationIdGenerator.php`
- `apps/api/src/Readings/UuidV4ConsultationIdGenerator.php`
- `apps/api/src/Readings/ConsultationRepository.php`
- `apps/api/src/Readings/SqliteConsultationRepository.php`
- `apps/api/database/migrations/2026_08_14_000001_create_consultations.php`
- `apps/api/tests/Readings/**`

## Data / schema changes

New tables `consultations`, `consultation_notes`, `tags`, `consultation_tags` — see spec.md
"Data requirements" for full DDL. Applied via `php scripts/migrate.php` (existing runner, no
changes needed to it).

## Risks / open questions

- None currently open. If a future spec needs a `delete()` on the repository, `ON DELETE
  CASCADE` on the child tables already makes that a one-line addition.
