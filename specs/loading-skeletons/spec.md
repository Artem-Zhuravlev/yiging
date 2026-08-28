# SPEC-048 — Loading Skeletons

**Status:** implemented
**Owner:** unassigned
**Last updated:** 2026-08-28

## Problem

Every data page shows the same bare loading state: a single grey line of text, "Loading…", in
the top-left where the content will be. It gives no sense of what's coming, and the jump from
one line of text to a full page of content is abrupt. It's the least-finished part of the UI.

## Purpose

Replace the "Loading…" text on the data pages with a small skeleton placeholder that roughly
occupies the shape of the incoming content, while keeping a screen-reader-only "Loading…"
announcement so assistive tech (and the existing tests) still see it.

## Scope

- New `src/shared/ui/LoadingSkeleton.vue`: a stack of PrimeVue `<Skeleton>` blocks — one wider
  "title" bar plus `lines` (default 4) full-width bars — wrapped in an `aria-hidden="true"`
  container, followed by a `.sr-only` `{{ t('common.loading') }}` span so the loading state is
  still exposed to assistive tech and to `wrapper.text()` in tests. Prop: `lines?: number`.
  The `<Skeleton>` shimmer is disabled under `prefers-reduced-motion: reduce`.
- Replace the page-level `v-if="…status === 'loading'"` `<p>{{ t(…loading…) }}</p>` with
  `<LoadingSkeleton :lines="…" />` on:
  - `HexagramListPage` (grid — `lines` ~8)
  - `HexagramDetailPage` (`lines` ~5)
  - `HexagramComparePage` (`lines` ~5)
  - `ConsultationPage` (`lines` ~6)
  - `ConsultationHistoryPage` (`lines` ~6)
  - `SharedConsultationPage` (`lines` ~5)
  - `JournalPage` (`lines` ~4)
  - `StatisticsPage` (`lines` ~4)
  - `InterpretationSettingsPage` (`lines` ~4)
- The SPEC-039 `useStatusAnnouncer` wiring (which announces "Loading…" in the live region on
  the `loading` transition) is unchanged and independent of this.

## Out of scope

- **`HexagramEditorPage`'s "Computing…" state.** That's an inline recompute on every line
  toggle, not a page load; a skeleton would flash on every click. Left as text.
- **`HomePage`.** It renders its static content immediately; only the Hexagram-of-the-Day card
  has a spinner, which is already appropriate for a small inline element — left as is.
- **The consultation page's AI-interpretation section loading state**, the "Load more"
  button's loading label, or any per-action submit spinner. Page-level initial load only.
- **Content-exact skeletons** (matching every heading and column). One generic title+lines
  shape, sized per page via `lines`, is enough.
- **A skeleton for route transitions / `<Suspense>`.**

## Functional requirements

- **REQ-SKEL-001** — While a data page is in its initial `loading` state it shows a
  `LoadingSkeleton` (visible skeleton bars) instead of the "Loading…" text line.
- **REQ-SKEL-002** — `LoadingSkeleton` still exposes "Loading…" to assistive tech via an
  `.sr-only` span (so screen readers and `wrapper.text()` see it); the skeleton bars
  themselves are `aria-hidden`.
- **REQ-SKEL-003** — Once loaded / errored / not-found, the skeleton is gone and the existing
  content / error / empty states render exactly as before.
- **REQ-SKEL-004** — The skeleton shimmer animation is disabled under
  `prefers-reduced-motion: reduce`.

## Non-functional requirements

- **REQ-SKEL-020** — No new npm dependency (PrimeVue `Skeleton` ships with `primevue`).
- **REQ-SKEL-021** — Theme-aware: the skeleton uses PrimeVue's own `Skeleton` theming, legible
  light and dark.
- **REQ-SKEL-022** — `npm run verify` passes; existing page specs that assert a "Loading" /
  "Завантаження" substring during the pending state still pass (via the `.sr-only` text).

## Data requirements

None.

## API requirements

None.

## Edge cases

- A fetch that resolves before the first paint → the skeleton may not visibly appear; harmless.
- `prefers-reduced-motion` → static grey blocks, no shimmer.
- A page whose loaded content is much shorter than the skeleton (e.g. an empty history) → the
  skeleton is replaced by the empty-state message on load; no layout lock-in.

## Acceptance criteria

- [x] Each listed page shows skeleton bars while loading, then its real content — verified live
      (`/hexagrams` 9 bars → grid; `/statistics` title + 4 bars → 3 charts) + `LoadingSkeleton.spec.ts`.
- [x] A screen reader still hears "Loading…": the `.sr-only` span renders `common.loading`
      (verified live — "Завантаження…" present), and the SPEC-039 live-region announcement is
      unchanged.
- [x] Reduced-motion → no shimmer (`@media (prefers-reduced-motion: reduce)` disables the
      `.p-skeleton::after` animation).
- [x] Skeleton uses PrimeVue `Skeleton` theming — legible in dark (verified) and light.
- [x] `npm run verify` passes (web 194, api 312, yijing-core 55); `HexagramListPage` / settings
      loading specs still green (sr-only text satisfies their substring check).

## Implementation note (2026-08-28)

- `shared/ui/LoadingSkeleton.vue`: `aria-hidden` `.loading-skeleton` stack of a 40%-width
  title `<Skeleton>` + `lines` (default 4) full-width bars, then an `.sr-only`
  `{{ t('common.loading') }}` span; a scoped `@media (prefers-reduced-motion: reduce)` rule
  kills the shimmer.
- The nine listed pages' page-level `loading` `<p>` swapped for `<LoadingSkeleton :lines="N" />`
  (list 8, detail/compare/shared 5, consultation/history 6, journal/stats/settings 4).
  `HexagramEditorPage` ("Computing…" inline recompute) and `HomePage` (renders immediately)
  left as-is. The `common.loading` / `hexagramList.loading` i18n keys stay (editor + sr-only).
