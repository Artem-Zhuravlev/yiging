# Tasks — Sequence of the Hexagrams (SPEC-056)

## yijing-core

- [x] **TASK-HS-001** — `Yijing\Core\Data\HexagramSequenceCatalog` — `ENTRIES` for King Wen
      3..64 (Legge Xù Guà, ctext.org / baharna cross-check; §II preamble on 31, §I close
      appended to 30) + `precedentFor(int): ?string`. Provenance docblock. → REQ-HS-001, 002
- [x] **TASK-HS-002** — `tests/Data/HexagramSequenceCatalogTest.php` — 1/2 → null; 3, 4, 30,
      31, 64 content checks; every 3..64 non-empty; 65 → null. `composer test`/`stan`/`lint`
      green. → REQ-HS-020, 021

## API

- [x] **TASK-HS-003** — `HexagramController::toJson()` adds `sequencePrecedent` inside the
      `includeDynamics` block; `use` the catalog. → REQ-HS-003
- [x] **TASK-HS-004** — `HexagramControllerTest` — `/hexagrams/3` has it (contains "Zhun"),
      `/hexagrams/1` → null, list has no key, `/from-lines` has it. → REQ-HS-003, 021

## Frontend

- [x] **TASK-HS-005** — `entities/hexagram/model.ts`: `Hexagram.sequencePrecedent?`.
      → REQ-HS-004
- [x] **TASK-HS-006** — `HexagramDetailPage.vue`: "Place in the sequence" section — heading,
      predecessor link, the sentence, source note; `v-if` on `sequencePrecedent`.
      → REQ-HS-004
- [x] **TASK-HS-007** — i18n `hexagramSequence.*` (title, heading, predecessorLink, source)
      (en + uk). → REQ-HS-022
- [x] **TASK-HS-008** — `HexagramDetailPage.spec.ts` — section renders with predecessor link
      for a mid-sequence hexagram; absent for hexagram 1. → REQ-HS-022

## Close-out

- [x] **TASK-HS-009** — `yijing-core` + `apps/api` `composer test`/`stan`/`lint` green;
      `npm run verify` green; browser pass (`/hexagrams/4`, `/1`, `/31`); curl pass; fill
      `plan.md` note; flip `spec.md` → `implemented`; add SPEC-056 to both README tables.
      → REQ-HS-021, 022
