# SPEC-050 — Tag Management (Rename / Merge / Delete)

**Status:** implemented
**Owner:** unassigned
**Last updated:** 2026-08-28

## Problem

Tags accrete typos and near-duplicates ("career", "Career", "carrer", "work") with no way to
tidy them. The only tag operation today is "add a tag to this consultation" (SPEC-013); there's
no rename, no merge, no delete. Over time the tag filter on the History page becomes a list of
almost-the-same words.

## Purpose

Let the user rename a tag (which merges into an existing tag when the new name already exists)
and delete a tag, from a "Manage tags" panel on the History page. Both operations act across
the whole history.

## Scope

### Backend

- `SqliteConsultationRepository`:
  - `allTagsWithCounts(): list<array{name: string, count: int}>` — every used tag with how many
    consultations carry it, sorted by name.
  - `renameOrMergeTag(string $from, string $to): void` — in one transaction: if a tag named
    `$to` already exists, repoint every `consultation_tags` row from the `$from` tag_id to the
    `$to` tag_id (`INSERT OR IGNORE` to dedup on the composite PK) then delete the `$from`
    `tags` row (the `ON DELETE CASCADE` clears its now-orphan links); otherwise just
    `UPDATE tags SET name = $to WHERE name = $from`.
  - `deleteTag(string $name): void` — `DELETE FROM tags WHERE name = ?` (cascade removes the
    `consultation_tags` links).
  - `tagExists(string $name): bool`.
- `ConsultationController`:
  - `tags(Request)` — `GET /api/consultations/tags` unchanged by default (`string[]`); with
    `?counts=1` returns `allTagsWithCounts()` (`[{name, count}]`). Backward compatible.
  - `renameTag(Request, vars)` — `PATCH /api/consultations/tags/{name}`, body `{ "newName": "…" }`.
    `422` if `newName` missing / blank after trim; `404` if `{name}` isn't an existing tag;
    `409` if `newName` trims to the same as `{name}` (no-op rename) — actually treat that as
    `200` no-op for simplicity; on success `200` with `{ "renamed": true, "merged": bool }`.
  - `deleteTag(Request, vars)` — `DELETE /api/consultations/tags/{name}`; `404` if not an
    existing tag; `204` on success.
- `config/routes.php`: `PATCH` and `DELETE /api/consultations/tags/{name}` (registered after the
  static `GET /api/consultations/tags`, before `/{id}`).
- `ConsultationRepository` interface gains the three new methods.

### Frontend

- `entities/consultation/api.ts`:
  - `fetchTagsWithCounts(): Promise<{ name: string; count: number }[]>` → `/tags?counts=1`.
  - `renameTag(name, newName): Promise<{ renamed: boolean; merged: boolean }>` → `PATCH`.
  - `deleteTag(name): Promise<void>` → `DELETE`.
- `ConsultationHistoryPage.vue`: a `<details>`-style "Manage tags" panel (collapsed by
  default) above the filter chips. It lists each tag with its count; each row has an inline
  "Rename" (reveals a text input pre-filled with the name + Save / Cancel) and a "Delete"
  (with a confirm — PrimeVue `ConfirmPopup`/`confirm()` or a simple inline "Delete? Yes / No").
  On a successful rename/delete: refetch the tag list (`fetchConsultationTags`), refetch the
  count list, drop the old name from `selectedTags` if present, and `load(true)` the
  consultation page-1 (results may have shifted). A rename that merged shows a toast
  ("Merged into {newName}"); a plain rename shows "Renamed"; delete shows "Tag deleted".
- Localised (en + uk): panel heading, Rename / Delete / Save / Cancel / confirm text, the
  toasts.

## Out of scope

- **Bulk select / multi-tag operations, tag colours, tag descriptions, a tag detail page.**
- **Creating a tag from the management panel** (tags are still created by tagging a
  consultation).
- **Case-insensitive auto-merge suggestions** ("did you mean to merge Career into career?").
  A later nicety; this spec does explicit rename/merge only.
- **Undo.** Rename/merge/delete are immediate. (The backup export/import from SPEC-028 is the
  safety net.)
- **Touching `journal` tags** — the journal has none.
- **A new top-level route or nav entry** — the panel lives on the History page.

## Functional requirements

- **REQ-TAG-001** — `GET /api/consultations/tags?counts=1` returns every used tag with a
  `count` of consultations carrying it, name-sorted; without the param the response is
  unchanged (`string[]`).
- **REQ-TAG-002** — `PATCH /api/consultations/tags/{name}` with `{ newName }` renames the tag;
  if `newName` already names a tag, the two are merged (every consultation on the old tag ends
  up on `newName`, with no duplicate links) and the old tag is removed.
- **REQ-TAG-003** — `PATCH` returns `422` for a blank/missing `newName` and `404` for a
  `{name}` that isn't a current tag.
- **REQ-TAG-004** — `DELETE /api/consultations/tags/{name}` removes the tag and every
  consultation's link to it; `204` on success, `404` if the tag doesn't exist. No consultation
  row is deleted.
- **REQ-TAG-005** — The History page's "Manage tags" panel lists tags with counts and performs
  rename (incl. merge) and delete against these endpoints, then refreshes the tag filter and
  the consultation list.
- **REQ-TAG-006** — After a merge or delete, a tag that no longer exists is removed from the
  active `selectedTags` filter.

## Non-functional requirements

- **REQ-TAG-020** — New strings localised (en + uk).
- **REQ-TAG-021** — `phpstan` level 8 + `php-cs-fixer` clean; rename/merge/delete run in a
  single transaction each.
- **REQ-TAG-022** — `npm run verify` passes, with API tests for rename, merge, delete, and the
  422/404 cases, and web tests for the panel's rename/delete flow.

## Data requirements

No schema change. Uses the existing `tags` / `consultation_tags` tables (the `ON DELETE
CASCADE` on `consultation_tags.tag_id` does the link cleanup).

## API requirements

- `GET /api/consultations/tags?counts=1` → `[{ name, count }]`
- `PATCH /api/consultations/tags/{name}` `{ newName }` → `{ renamed, merged }` | 422 | 404
- `DELETE /api/consultations/tags/{name}` → 204 | 404

## Edge cases

- `newName` equals `{name}` (after trim) → `200`, no-op, `merged: false`.
- `newName` differs only in case from an existing different tag → treated as a normal
  merge into that existing tag (exact-name match; SQLite `name` is case-sensitive by default).
- Renaming to a name that exists → merge; the resulting tag keeps `newName`'s id, so any code
  holding the old id must refetch (the frontend refetches the whole list).
- Deleting a tag currently in `selectedTags` → the filter drops it and re-runs.
- A tag with zero consultations can't appear (the list is "used tags") — so there's nothing to
  manage that isn't attached to something.
- Concurrent rename of the same tag from two tabs → second one 404s (the tag name changed);
  acceptable.

## Acceptance criteria

- [x] Rename to a fresh name → renamed everywhere, filter chip updates — live: `carrer`→`career`
      `{renamed, merged:false}`; UI rename `career`→`робота` updated the manage row + chip +
      toast. Controller + repo tests.
- [x] Rename into an existing tag → merge, no duplicate link, old name gone — live: `tidy`→
      `career` `{merged:true}`, `career` count 2→3. `testRenameTagIntoAnExistingTagMergesWithoutDuplicates`
      + `testRenameOrMergeTagMergesEveryLinkOntoTheTargetWithoutDuplicates`.
- [x] Delete a tag → removed from every consultation and the filter, consultation survives —
      live: `DELETE …/relationships` → 204, gone. `testDeleteTagRemovesItFromEveryConsultationButKeepsTheConsultations`.
- [x] Blank `newName` → 422; unknown `{name}` on PATCH/DELETE → 404 — live + tests.
- [x] `GET …/tags` unchanged (`string[]`); `?counts=1` → `[{name,count}]` name-sorted — live + test.
- [x] `npm run verify` passes (web 202, api 323, yijing-core 55).

## Implementation note (2026-08-28)

- Repo: `tagExists`, `allTagsWithCounts`, `renameOrMergeTag` (txn; `INSERT OR IGNORE` repoint +
  drop old `tags` row on merge, else `UPDATE tags.name`), `deleteTag` (txn; single `DELETE FROM
  tags`, `ON DELETE CASCADE` clears `consultation_tags`). Controller: `tags(Request)` honours
  `?counts=1`; `renameTag` / `deleteTag` actions (422/404/no-op/200-`{renamed,merged}` / 204).
  Routes `PATCH`/`DELETE /api/consultations/tags/{name}`.
- Frontend: `fetchTagsWithCounts` / `renameTag` / `deleteTag`; `TagWithCount`. A collapsed
  `<details>` "Manage tags" panel on the History page — per-tag row with count, inline Rename
  (InputText + Save/Cancel) and Delete (inline "Delete «name»?" confirm). Success →
  `useToastSuccess` (`tagRenamed` / `tagMerged` / `tagDeleted`), refetch tag vocabulary +
  counts, drop a vanished tag from `selectedTags` (which re-runs `load(true)`). Errors → a local
  `manageError` line in the panel.
