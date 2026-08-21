# Plan — Consultation History Backup (SPEC-028)

**Depends on spec status:** `approved`

## Technical approach

- `apps/api/src/Readings/ConsultationController.php` gains `import(Request $request): Response`:
  1. Decode the JSON body; must be a list (array with sequential integer keys) or `422`.
  2. First pass: for each item, validate required fields present/typed correctly and build a
     `Consultation` via `Consultation::reconstitute()`, hexagrams rebuilt with a small private
     `hexagramFromKingWenNumber(int, list<int>): Hexagram` helper — the same ~10-line logic
     `SqliteConsultationRepository::hydrate()` already has privately; duplicated here rather than
     extracted to a shared utility, since it's small, used in exactly two places, and each call
     site already owns its own row/array shape to pull the raw ints from.
  3. Collect every item's `id` into a set; if any already exists via a new repository method
     `existsById(string $id): bool` (`SELECT 1 FROM consultations WHERE id = :id LIMIT 1`),
     `422`.
  4. Collect every item's `followUpToConsultationId` (when non-null); for each, it must be either
     in the batch's own id set or resolve via the existing `findSummaryById()` — otherwise `422`.
  5. If all validation passes, open one transaction: save every `Consultation` with
     `followUpToConsultationId: null` first (reusing the existing `save()`/`upsertConsultation()`
     path, an `INSERT` since none of these IDs exist yet), then a second loop `PATCH`-equivalent
     update (a small new repository method `updateFollowUpLink(string $id, ?string
     $followUpToConsultationId): void`) setting each item's real link now that every row in the
     batch exists.
  6. Commit; respond `201 {"imported": count(items)}`.
- `config/routes.php` gains `POST /api/consultations/import`.
- Frontend: `entities/consultation/api.ts` gains `exportConsultationsBackup(consultations:
  Consultation[]): void` (pure client-side: `JSON.stringify` → `Blob` → temporary `<a
  download>`.click()`, no `apiPost`) and `importConsultationsBackup(items: unknown[]): Promise<{
  imported: number }>` (the one `apiPost` call, per REQ-BACKUP-009).
- `ConsultationHistoryPage.vue`: an "Export Backup (JSON)" button calling
  `exportConsultationsBackup(state.consultations)` (loaded state only); an "Import Backup (JSON)"
  `<input type="file" accept="application/json">` (visually a styled button via a hidden input +
  label, the established pattern for file pickers) whose `change` handler reads the file via
  `FileReader`/`File.text()`, `JSON.parse`s it (catching and showing a parse error inline without
  calling the API), calls `importConsultationsBackup()`, and on success re-fetches the list.

## Architecture decisions

- **`Consultation::reconstitute()` is the entry point for imported data, not `create()`.**
  `create()` enforces "this is brand-new, no outcome yet, no pre-existing state" invariants that
  don't apply to restoring already-valid historical data — `reconstitute()` already exists
  specifically for "rebuild from trusted, previously-validated state," which describes an import
  batch (validated at the HTTP boundary, same as any repository read) exactly.
- **Two-pass insert (rows first, follow-up links second) rather than trying to order the batch
  topologically.** A batch can contain a follow-up cycle or forward-reference another item later
  in the array; inserting every row with no link first, then resolving links in a second pass once
  every ID definitely exists, sidesteps ordering entirely rather than requiring the client to sort
  the array.
- **All-or-nothing per batch, one transaction.** Matches REQ-BACKUP-008 and this API's existing
  validate-then-apply pattern everywhere else (`ConsultationController::update()` validates every
  touched field before calling any wither).
- **Export is zero network calls beyond the page's existing `GET /api/consultations`.** The
  button serializes state already in memory; no reason to re-fetch just to download it.

## Affected areas

- `apps/api/src/Readings/ConsultationController.php`
- `apps/api/src/Readings/ConsultationRepository.php`
- `apps/api/src/Readings/SqliteConsultationRepository.php`
- `apps/api/config/routes.php`
- `apps/api/tests/Readings/ConsultationControllerTest.php`
- `apps/api/tests/Readings/SqliteConsultationRepositoryTest.php`
- `apps/web/src/entities/consultation/api.ts`
- `apps/web/src/entities/consultation/api.spec.ts`
- `apps/web/src/pages/consultations/ConsultationHistoryPage.vue`
- `apps/web/src/pages/consultations/ConsultationHistoryPage.spec.ts`

## Data / schema changes

None.

## Risks / open questions

- None currently open.
