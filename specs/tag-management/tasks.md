# Tasks — Tag Management (SPEC-050)

## Backend

- [x] **TASK-TAG-001** — `ConsultationRepository` + `SqliteConsultationRepository`:
      `tagExists()`, `allTagsWithCounts()`, `renameOrMergeTag()` (txn: repoint
      `consultation_tags` via `INSERT OR IGNORE` + drop old `tags` row when target exists, else
      `UPDATE tags.name`), `deleteTag()` (txn: `DELETE FROM tags` → cascade). → REQ-TAG-001, 002,
      004, 021
- [x] **TASK-TAG-002** — `ConsultationController`: `tags(Request)` honours `?counts=1`
      (`allTagsWithCounts()` vs `allTagNames()`); `renameTag(Request, vars)` (422 blank
      `newName`, 404 unknown tag, 200 `{renamed, merged}`, no-op when unchanged);
      `deleteTag(Request, vars)` (404 unknown, 204). → REQ-TAG-001, 002, 003, 004
- [x] **TASK-TAG-003** — `config/routes.php`: `PATCH` + `DELETE /api/consultations/tags/{name}`.
      → REQ-TAG-002, 004

## Frontend

- [x] **TASK-TAG-004** — `entities/consultation/api.ts`: `fetchTagsWithCounts()`,
      `renameTag(name, newName)`, `deleteTag(name)`; `model.ts`: `TagWithCount`. → REQ-TAG-005
- [x] **TASK-TAG-005** — `ConsultationHistoryPage.vue`: collapsed "Manage tags" panel (tag +
      count, inline Rename with Save/Cancel, Delete with inline confirm); on success refetch
      `fetchConsultationTags` + `fetchTagsWithCounts`, drop a vanished tag from `selectedTags`,
      reload page 1; success toasts (renamed / merged / deleted); local error line. → REQ-TAG-005,
      006
- [x] **TASK-TAG-006** — i18n `history.manageTags` / `rename` / `deleteTag` /
      `confirmDeleteTag` / `tagRenamed` / `tagMerged` / `tagDeleted`, `common.save` /
      `common.cancel` (en + uk). → REQ-TAG-020

## Tests

- [x] **TASK-TAG-007** — `ConsultationControllerTest`: rename-fresh, rename-merge (no dup),
      rename no-op, blank→422, unknown→404 (PATCH+DELETE), delete removes links & keeps the
      consultation (204), `?counts=1` shape + plain `/tags` unchanged. → REQ-TAG-022
- [x] **TASK-TAG-008** — `SqliteConsultationRepositoryTest`: `renameOrMergeTag` merge path,
      `deleteTag`, `allTagsWithCounts`. → REQ-TAG-022
- [x] **TASK-TAG-009** — `ConsultationHistoryPage.spec.ts`: mock the 3 new fns; Manage-tags
      row shows `name (count)`; rename flow calls `renameTag(old,new)` + refetches; delete flow
      calls `deleteTag` + refetches. → REQ-TAG-022

## Close-out

- [x] **TASK-TAG-010** — `apps/api` `composer test`/`stan`/`lint` green; `npm run verify`
      green; browser pass (rename → chip updates; merge → counts combine; delete → gone,
      consultation survives); fill `plan.md` note; flip `spec.md` → `implemented`; add SPEC-050
      to both README tables. → REQ-TAG-021, 022