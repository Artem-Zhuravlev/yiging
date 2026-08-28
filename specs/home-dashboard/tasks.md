# Tasks — Home Dashboard (SPEC-045)

- [x] **TASK-HOME-001** — `HomePage.vue`: add `recent` + `totalCast` refs; in `onMounted` fire
      `fetchConsultations({ limit: 4 })` and `fetchStatistics()` alongside the existing HOTD
      fetch, each `.catch(() => {})` so a failure is silent and independent. → REQ-HOME-003
- [x] **TASK-HOME-002** — Template: a "Recent" section (`v-if="recent.length"`) of up to 4
      `router-link` rows (question / hexagram pair / date) + a "View all" link; a
      "{n} consultations cast" line (`v-if="totalCast !== null"`) linking to `/statistics`. Keep
      the existing header, buttons, and HOTD card unchanged; narrow left-aligned column, calm.
      → REQ-HOME-001, 002, 004, 021
- [x] **TASK-HOME-003** — i18n `home.recent`, `home.viewAll`, `home.consultationsCast` (en + uk).
      → REQ-HOME-020
- [x] **TASK-HOME-004** — `HomePage.spec.ts`: mock `fetchConsultations` + `fetchStatistics`
      (empty defaults); tests for the populated dashboard, the independent-failure case, and
      the empty-history splash. Keep existing HOTD tests green. → REQ-HOME-001, 002, 003, 004,
      REQ-HOME-022
- [x] **TASK-HOME-005** — `npm run verify` green; browser pass on `/` with data and empty,
      mobile + dark; fill `plan.md` note; flip `spec.md` → `implemented`; add SPEC-045 to both
      README tables. → REQ-HOME-021, 022