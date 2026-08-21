# Plan — Consultation Timeline (SPEC-022)

**Depends on spec status:** `approved`

## Technical approach

- Add a `groupedConsultations` computed value to `ConsultationHistoryPage.vue`: given the loaded
  `Consultation[]` (already newest-first from the API) and the current `selectedTags` set, first
  filter (if `selectedTags.size > 0`, keep only consultations whose `tags` array is a superset of
  `selectedTags`), then walk the filtered array once, starting a new group whenever the local
  calendar date (`new Date(c.createdAt).toDateString()` as the group key, cheap and
  timezone-consistent within a single render) differs from the previous item's.
- Add an `allTags` computed value: `[...new Set(consultations.flatMap(c => c.tags))].sort()`,
  computed over the *unfiltered* list so chips don't disappear as filters narrow the results.
- `selectedTags` is a local `ref<Set<string>>`, toggled by clicking a chip.
- Group heading text uses `toLocaleDateString()` (undefined locale → browser default, matching
  the existing per-item `toLocaleString()` call already in this file).
- Three empty states, mutually exclusive: no consultations loaded at all (existing message,
  unchanged); consultations loaded but `groupedConsultations` is empty because a tag filter
  matched nothing (new message); normal grouped rendering.

## Architecture decisions

- **Everything computed client-side in the page component, no new store, no new API call.** The
  full list is already fetched once; grouping/filtering is pure array transformation over data
  already in memory, matching REQ-TIMELINE-011/012. Introducing a Pinia store or a second network
  round-trip for this would be pure overhead at this app's scale.
- **Group key is `toDateString()` (local calendar day), not a UTC day or a rolling window.** A
  user browsing their own history expects "today" to mean their own local today, and the app has
  no server-side timezone concept to reconcile against — matches how the existing per-item
  timestamp already renders via `toLocaleString()`.
- **Tag filter uses AND (every selected tag must be present), computed by array `.every()` +
  `.includes()`.** Chosen because narrowing filters ("show me things tagged both X and Y") is the
  more common mental model for multi-select filter chips than OR would be; documented explicitly
  in the spec since it's a real design choice, not an obvious default.

## Affected areas

- `apps/web/src/pages/consultations/ConsultationHistoryPage.vue`
- `apps/web/src/pages/consultations/ConsultationHistoryPage.spec.ts`

## Data / schema changes

None.

## Risks / open questions

- None currently open.
