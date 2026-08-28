# Tasks — Trigram (Bagua) Explorer (SPEC-049)

- [x] **TASK-TRIG-001** — `entities/trigram/model.ts` (`Trigram` interface) +
      `entities/trigram/api.ts` (`fetchTrigrams()` → `GET /api/trigrams`). → REQ-TRIG-001
- [x] **TASK-TRIG-002** — `pages/trigrams/TrigramExplorerPage.vue`: standard
      loading(skeleton)/error(alert)/loaded state machine + `useStatusAnnouncer` +
      `<main id="main">`; responsive card grid (symbol, names, Image/Element/Family/Direction
      `<dl>`). → REQ-TRIG-001, 003, 021
- [x] **TASK-TRIG-003** — Same page: a captioned 3×3 `<figure>` grid placing each trigram by
      its API `direction` (NW/N/NE / W/·/E / SW/S/SE), centre empty. → REQ-TRIG-002
- [x] **TASK-TRIG-004** — `router/index.ts`: `/trigrams` route.
      `HexagramListPage.vue`: header link to `/trigrams`. → REQ-TRIG-004
- [x] **TASK-TRIG-005** — i18n `trigramExplorer.*` (title, link, arrangement, loadError, field
      labels) (en + uk). → REQ-TRIG-020
- [x] **TASK-TRIG-006** — `TrigramExplorerPage.spec.ts`: 8 cards render with their data; the
      arrangement grid places Qian@NW / Li@S in the right cells; loading skeleton; fetch-error
      shows the message. `HexagramListPage.spec.ts`: assert the `/trigrams` header link.
      → REQ-TRIG-022
- [x] **TASK-TRIG-007** — `npm run verify` green; browser pass (`/trigrams` cards + arrangement,
      dark + light + narrow; Explorer link; forced error); fill `plan.md` note; flip `spec.md`
      → `implemented`; add SPEC-049 to both README tables. → REQ-TRIG-022