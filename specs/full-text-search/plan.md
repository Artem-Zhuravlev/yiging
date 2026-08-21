# Plan — Full-Text Search (SPEC-026)

**Depends on spec status:** `approved`

## Technical approach

- `ConsultationHistoryPage.vue`: a `searchQuery = ref('')`, plus a `matchesSearch(consultation)`
  helper (`question` or any `notes[].text` contains `searchQuery.value`, case-insensitive). Added
  as a third `.filter()` stage in the existing `filteredConsultations` computed chain (after tags,
  after favorites — order doesn't affect the result since all three are simple AND filters, but
  keeps the chain readable top-to-bottom in the same order the UI controls appear).
- `HexagramListPage.vue`: a `searchQuery = ref('')` and a `filteredHexagrams` computed matching
  `chineseName`/`pinyin`/`judgment`/`image` case-insensitively (`judgment`/`image` guarded with
  `?.` before `.toLowerCase()`, since both can be `null`).
- Both: a single `<input type="search">` (or `type="text"`) bound with `v-model`, no debounce
  (computed properties re-run synchronously on every keystroke, no network call involved).

## Architecture decisions

- **Purely client-side, page-local `ref` state — no new store, no new API call.** Matches
  REQ-SEARCH-006/007 and the precedent SPEC-022 (tags)/SPEC-025 (favorites) both already
  established for this exact page.
- **Case-insensitive substring match via `.toLowerCase().includes()`, not a regex or search
  library.** The simplest thing that satisfies "search across text I already have," matching this
  project's general preference against premature sophistication.

## Affected areas

- `apps/web/src/pages/consultations/ConsultationHistoryPage.vue`
- `apps/web/src/pages/consultations/ConsultationHistoryPage.spec.ts`
- `apps/web/src/pages/hexagrams/HexagramListPage.vue`
- `apps/web/src/pages/hexagrams/HexagramListPage.spec.ts`

## Data / schema changes

None.

## Risks / open questions

- None currently open.
