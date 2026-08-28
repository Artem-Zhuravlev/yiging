# Tasks — Loading Skeletons (SPEC-048)

- [x] **TASK-SKEL-001** — `shared/ui/LoadingSkeleton.vue`: `aria-hidden` stack of a title
      `<Skeleton>` + `lines` (default 4) bar `<Skeleton>`s, then an `.sr-only`
      `{{ t('common.loading') }}` span; shimmer disabled under `prefers-reduced-motion: reduce`.
      → REQ-SKEL-001, 002, 004, 020, 021
- [x] **TASK-SKEL-002** — Swap the page-level `loading` `<p>` for `<LoadingSkeleton :lines>` on
      `HexagramListPage` (8), `HexagramDetailPage` (5), `HexagramComparePage` (5),
      `ConsultationPage` (6, page-level only), `ConsultationHistoryPage` (6),
      `SharedConsultationPage` (5), `JournalPage` (4), `StatisticsPage` (4),
      `InterpretationSettingsPage` (4). Leave `HexagramEditorPage` / `HomePage`. → REQ-SKEL-001,
      003
- [x] **TASK-SKEL-003** — `LoadingSkeleton.spec.ts`: `1 + lines` `<Skeleton>`s, sr-only
      "Loading…" text, `aria-hidden` container. → REQ-SKEL-002
- [x] **TASK-SKEL-004** — `npm run verify` green (existing `HexagramListPage` / settings
      loading specs pass via the sr-only text); browser pass on 3–4 routes (skeleton → content,
      dark, reduced-motion); fill `plan.md` note; flip `spec.md` → `implemented`; add SPEC-048
      to both README tables. → REQ-SKEL-022