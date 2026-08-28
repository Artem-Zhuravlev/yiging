# Plan — Loading Skeletons (SPEC-048)

## Files

### New
- `apps/web/src/shared/ui/LoadingSkeleton.vue`
  ```vue
  <script setup lang="ts">
  import Skeleton from 'primevue/skeleton'
  import { useI18n } from 'vue-i18n'
  withDefaults(defineProps<{ lines?: number }>(), { lines: 4 })
  const { t } = useI18n()
  </script>
  <template>
    <div class="loading-skeleton flex flex-column gap-2" aria-hidden="true">
      <Skeleton width="40%" height="1.5rem" />
      <Skeleton v-for="i in lines" :key="i" height="1rem" />
    </div>
    <span class="sr-only">{{ t('common.loading') }}</span>
  </template>
  <style scoped>
  @media (prefers-reduced-motion: reduce) {
    .loading-skeleton :deep(.p-skeleton)::after { animation: none !important; }
  }
  </style>
  ```
- `apps/web/src/shared/ui/LoadingSkeleton.spec.ts`

### Changed (swap the loading `<p>` → `<LoadingSkeleton :lines="…" />`, import it)
- `HexagramListPage.vue` (`:lines="8"`; replaces `t('hexagramList.loading')` line)
- `HexagramDetailPage.vue` (`:lines="5"`; keep the `not-found` / `error` branches)
- `HexagramComparePage.vue` (`:lines="5"`)
- `ConsultationPage.vue` (`:lines="6"`; line ~420 only — NOT the interpretation section)
- `ConsultationHistoryPage.vue` (`:lines="6"`)
- `SharedConsultationPage.vue` (`:lines="5"`; keep `not-found`)
- `JournalPage.vue` (`:lines="4"`)
- `StatisticsPage.vue` (`:lines="4"`)
- `InterpretationSettingsPage.vue` (`:lines="4"`)

Each: add `import LoadingSkeleton from '../../shared/ui/LoadingSkeleton.vue'`; change
`<p v-if="…status === 'loading'" class="…">{{ t(…) }}</p>` to
`<LoadingSkeleton v-if="…status === 'loading'" :lines="N" class="mt-4" />` (keep whatever
top-margin the old `<p>` had where it had `mt-4`).

Do NOT touch: `HexagramEditorPage.vue`, `HomePage.vue`, the `common.loading` /
`hexagramList.loading` / `hexagramEditor.computing` i18n keys (still used: editor + the
`common.loading` sr-only text).

## Testing
- `LoadingSkeleton.spec.ts`: renders `1 + lines` `<Skeleton>` blocks (title + lines);
  contains the sr-only "Loading…" text; the skeleton container is `aria-hidden`.
- Existing specs:
  - `HexagramListPage.spec.ts` "shows a loading state" → `toContain('Loading')` still passes
    (sr-only span renders "Loading…").
  - `InterpretationSettingsPage.spec.ts` "shows a loading state" → same.
  - Any spec that did `wrapper.find('p')` on the loading state — grep shows none; the
    loading assertions are all `wrapper.text()` substring checks.
- No page-spec edits expected; if one breaks, fix by asserting the skeleton (`.p-skeleton`)
  or keep the substring check (sr-only covers it).

## Verify

`npm run verify`; browser: throttle / observe each listed route's initial load showing skeleton
bars then content; toggle dark; set reduced-motion and confirm no shimmer.

## Verification note (2026-08-28)

- `npm run verify` green (web 194 tests incl. new `LoadingSkeleton.spec.ts`; api 312;
  yijing-core 55). Existing `HexagramListPage` / settings loading-state specs pass unchanged via
  the `.sr-only` "Loading…" span.
- Live pass (fetch artificially delayed): `/hexagrams` shows 9 skeleton bars (1 title + 8),
  container `aria-hidden="true"`, `.sr-only` "Завантаження…" present; `/statistics` shows the
  title + 4 bars in dark theme, then the three charts once data resolves. `HexagramEditorPage`
  "Computing…" and `HomePage` left as-is per scope.
