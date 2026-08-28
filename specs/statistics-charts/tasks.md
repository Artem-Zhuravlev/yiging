# Tasks — Statistics Charts (SPEC-043)

- [x] **TASK-STATC-001** — `shared/ui/BarChart.vue`: `<table>` (sr-only caption, row headers),
      one row per `{label,value}`, proportional bar (`var(--p-primary-color)`), value shown,
      `max===0` guard, width transition behind `prefers-reduced-motion: no-preference`.
      → REQ-STATC-001, 003, 004, 005, 022
- [x] **TASK-STATC-002** — `shared/ui/DonutChart.vue`: SVG track + segment circles via
      `stroke-dasharray`, `role="img"` + `aria-label` naming segments and percentages, caption
      slot/prop, theme CSS vars only. → REQ-STATC-002, 004, 005
- [x] **TASK-STATC-003** — `StatisticsPage.vue`: hexagram freq → `BarChart` (top 12 + "+N more"),
      yin/yang → `DonutChart` with the count/percent caption (drop `ProgressBar`), tag freq →
      `BarChart` (panel still gated). Keep panels/headings/empty state/`useStatusAnnouncer`.
      → REQ-STATC-001, 002, 003, 006
- [x] **TASK-STATC-004** — i18n `statistics.andMore` (en + uk). → REQ-STATC-021
- [x] **TASK-STATC-005** — `BarChart.spec.ts` + `DonutChart.spec.ts` (proportions, max-0,
      aria-label, table semantics). → REQ-STATC-004
- [x] **TASK-STATC-006** — Update `StatisticsPage.spec.ts` for the new markup (counts asserted
      inside chart tables / donut label; "+N more"; tag gating; empty state). → REQ-STATC-023
- [x] **TASK-STATC-007** — `npm run verify` green; browser pass light + dark on the seeded
      history and the empty state; fill `plan.md` note; flip `spec.md` → `implemented`; add
      SPEC-043 to both README tables. → REQ-STATC-022, 023