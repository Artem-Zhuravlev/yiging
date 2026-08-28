# Plan — Tag Management (SPEC-050)

## Backend

### `ConsultationRepository` (interface) — add
- `allTagsWithCounts(): array` → `list<array{name: string, count: int}>`
- `renameOrMergeTag(string $from, string $to): void`
- `deleteTag(string $name): void`
- `tagExists(string $name): bool`

### `SqliteConsultationRepository`
- `tagExists`: `SELECT 1 FROM tags WHERE name = :name LIMIT 1`.
- `allTagsWithCounts`:
  ```sql
  SELECT t.name, COUNT(ct.consultation_id) AS count
  FROM tags t JOIN consultation_tags ct ON ct.tag_id = t.id
  GROUP BY t.id ORDER BY t.name ASC
  ```
  (JOIN, not LEFT JOIN → only used tags, matching `allTagNames()`.)
- `renameOrMergeTag($from, $to)` — `beginTransaction`:
  - `fromId = SELECT id FROM tags WHERE name = :from` (caller guarantees it exists).
  - `toId  = SELECT id FROM tags WHERE name = :to`.
  - if `toId === false` (no such tag): `UPDATE tags SET name = :to WHERE id = :fromId`.
  - else: `INSERT OR IGNORE INTO consultation_tags (consultation_id, tag_id)
     SELECT consultation_id, :toId FROM consultation_tags WHERE tag_id = :fromId`, then
     `DELETE FROM tags WHERE id = :fromId` (cascade drops the leftover `tag_id = fromId` rows).
  - `commit` / `rollBack` on throw — same shape as `save()`.
- `deleteTag($name)`: `DELETE FROM tags WHERE name = :name` (single statement; cascade handles
  `consultation_tags`). Wrap in a txn for consistency with the others.

### `ConsultationController`
- `tags(Request $request)`:
  ```php
  if ($request->query->get('counts') is truthy) return JsonResponse($this->repository->allTagsWithCounts());
  return JsonResponse($this->repository->allTagNames());
  ```
- `renameTag(Request $request, array $vars)`:
  - `$name = $vars['name']` (FastRoute url-decodes).
  - decode body; `$newName = trim(string $body['newName'] ?? '')`.
  - `$newName === ''` → 422.
  - `!$this->repository->tagExists($name)` → 404.
  - `$newName === $name` → `JsonResponse(['renamed' => true, 'merged' => false])` (no-op).
  - `$merged = $this->repository->tagExists($newName)`; `renameOrMergeTag($name, $newName)`;
    `JsonResponse(['renamed' => true, 'merged' => $merged])`.
- `deleteTag(Request $request, array $vars)`:
  - `!tagExists` → 404; else `deleteTag($name)`; `new Response('', 204)`.

### `config/routes.php`
```
GET    /api/consultations/tags        → tags
PATCH  /api/consultations/tags/{name} → renameTag
DELETE /api/consultations/tags/{name} → deleteTag
GET    /api/consultations/export      → export
GET    /api/consultations/{id}        → show
```
(`tags/{name}` — a two-segment static+param path — is matched by FastRoute before
`consultations/{id}` regardless of order, but keep it grouped.)

## Frontend

### `entities/consultation/api.ts`
- `fetchTagsWithCounts(): Promise<{ name: string; count: number }[]>` → `apiGet('/consultations/tags?counts=1')`.
- `renameTag(name: string, newName: string): Promise<{ renamed: boolean; merged: boolean }>` →
  `apiPatch('/consultations/tags/' + encodeURIComponent(name), { newName })`.
- `deleteTag(name: string): Promise<void>` →
  `apiDelete('/consultations/tags/' + encodeURIComponent(name))`.

### `entities/consultation/model.ts`
- `export interface TagWithCount { name: string; count: number }`

### `ConsultationHistoryPage.vue`
- New state: `tagCounts = ref<TagWithCount[]>([])`, `manageOpen = ref(false)`,
  `editingTag = ref<string | null>(null)`, `editName = ref('')`,
  `confirmingDelete = ref<string | null>(null)`.
- `onMounted` (and after import): also `fetchTagsWithCounts().then(v => tagCounts.value = v).catch(()=>{})`.
- `<details>` "Manage tags" panel above the filter chips: for each `tagCounts` row, a flex row:
  `{name} ({count})` + a "Rename" button. When `editingTag === name`: an `InputText`
  (`v-model="editName"`) + Save / Cancel. A "Delete" button; when `confirmingDelete === name`:
  "Delete? Yes / No" inline.
- `saveRename()`:
  ```
  const from = editingTag.value; const to = editName.value.trim()
  if (!from || to === '' || to === from) { editingTag.value = null; return }
  const { merged } = await renameTag(from, to)
  notifySaved(merged ? 'history.tagMerged' : 'history.tagRenamed')  // reuse useToastSuccess
  await refreshTagsAndList(from)
  editingTag.value = null
  ```
- `confirmDelete(name)` → `await deleteTag(name); notifySaved('history.tagDeleted'); await refreshTagsAndList(name)`.
- `refreshTagsAndList(removedOrOldName)`:
  ```
  allTags.value = await fetchConsultationTags()
  tagCounts.value = await fetchTagsWithCounts()
  if (selectedTags.value.has(removedOrOldName)) { const s = new Set(selectedTags.value); s.delete(removedOrOldName); selectedTags.value = s }  // watcher triggers load(true)
  else await load(true)
  ```
  (Removing from `selectedTags` already triggers the `watch([...selectedTags...])` → `load(true)`;
  guard so we don't double-load.)
- `useToastSuccess` is already used elsewhere; import here too. Errors from rename/delete →
  set a small local `manageError` string shown in the panel (don't reuse `importState`).
- i18n: `history.manageTags`, `history.rename`, `history.deleteTag`, `common.save`,
  `common.cancel`, `history.confirmDeleteTag`, `history.tagRenamed`, `history.tagMerged`,
  `history.tagDeleted`. (`common.close` exists; add `common.save`/`common.cancel` if missing —
  check: `common` has `close`, add `save`/`cancel`... `settings.save` exists but is page-scoped;
  add `common.save` = "Save" / "Зберегти", `common.cancel` = "Cancel" / "Скасувати".)

## Testing

### API (`ConsultationControllerTest`)
- `renameTag` to a fresh name → the tag list reflects the new name, old gone; a consultation
  that had it now reports the new name in its `tags`.
- `renameTag` to an existing tag's name → both consultations end up with `newName` once (no
  dup); old name gone; response `merged: true`.
- `renameTag` no-op (newName === name) → `200 {renamed:true, merged:false}`, unchanged.
- blank `newName` → 422; unknown `{name}` → 404 (both PATCH and DELETE).
- `deleteTag` → tag gone from the list and from the consultation's `tags`; consultation still
  exists; 204. Delete unknown → 404.
- `GET /tags?counts=1` → `[{name,count}]` sorted; `GET /tags` (no param) still `string[]`.

### `SqliteConsultationRepositoryTest`
- `renameOrMergeTag` merge path: two consultations, tags `a` and `b`; rename `a`→`b`; both have
  exactly `['b']`; `allTagNames()` === `['b']`.
- `deleteTag`: removes links, keeps consultations.
- `allTagsWithCounts`: counts correct, name-sorted.

### web (`ConsultationHistoryPage.spec.ts`)
- mock the three new api fns. Open "Manage tags"; a row shows `name (count)`.
- Rename flow: click Rename → input → Save → `renameTag` called with `(old, new)`; on `merged`
  true a toast key differs; `fetchConsultationTags` + `fetchTagsWithCounts` refetched.
- Delete flow: click Delete → confirm → `deleteTag` called; list refetched.
- Also update the mock list in existing tests to include the new fns as `vi.fn()` (they're in
  the same `vi.mock('../../entities/consultation/api', …)` factory).

## Verify

`cd apps/api && composer test && composer stan && composer lint`; `npm run verify`. Browser:
History → Manage tags → rename a tag to a new name (chip updates), rename another into an
existing one (merge, count combines), delete one (gone from chips + cards), confirm a
consultation still exists.


## Verification note (2026-08-28)

- apps/api: composer test 323, stan + lint clean. npm run verify green (web 202).
- Live: ?counts=1 shape ok; rename-fresh + merge (career 2->3, no dup); blank->422, unknown->404
  (PATCH+DELETE); delete->204, tag gone, consultation kept. UI: renamed career->робота, manage
  row + chip + toast updated (restored after).
