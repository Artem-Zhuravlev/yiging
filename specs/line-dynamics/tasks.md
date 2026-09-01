# Tasks — Line Dynamics (SPEC-053)

## yijing-core

- [x] **TASK-LD-001** — `Yijing\Core\LineDynamic` (8 props + `toArray()`). → REQ-LD-001..004
- [x] **TASK-LD-002** — `Yijing\Core\LineDynamics` + `::of(Hexagram)` computing
      correctPosition / central / centralAndCorrect / correspondsWith+corresponds /
      ridesFirmBelow / supportsFirmAbove per line; `toArray()`. → REQ-LD-001..004, 020
- [x] **TASK-LD-003** — `tests/LineDynamicsTest.php`: hexagram 63 (all correct, all
      correspond), 64 (none correct, all correspond), 1 (no correspondence, line 5 中正), 44
      (line 1 supports the firm), a rides-the-firm case. `composer test`/`stan`/`lint` green.
      → REQ-LD-020, 021

## API

- [x] **TASK-LD-004** — `HexagramController::toJson()` gains `bool $includeDynamics = false`;
      `show()` + `fromLines()` pass `true`, `index()` doesn't; appends
      `lineDynamics => LineDynamics::of($hexagram)->toArray()`. → REQ-LD-005
- [x] **TASK-LD-005** — `HexagramControllerTest`: `/hexagrams/63` all-correct+all-correspond,
      `/hexagrams/1` line 5 中正 / line 2 not, list endpoint has no `lineDynamics`,
      `/from-lines` has it. `composer test`/`stan`/`lint` green. → REQ-LD-005, 021

## Frontend

- [x] **TASK-LD-006** — `entities/hexagram/model.ts`: `LineDynamic` type; `Hexagram`
      `lineDynamics?: LineDynamic[]`. → REQ-LD-006
- [x] **TASK-LD-007** — `HexagramDetailPage.vue`: a "Line dynamics" section (correspondence
      summary of the 3 pairs + per-line table top-to-bottom, Chinese terms shown + a help
      line), `v-if` on `lineDynamics`. → REQ-LD-006
- [x] **TASK-LD-008** — i18n `lineDynamics.*` (title, help, column headers, term labels each
      English + 中文) (en + uk). → REQ-LD-022
- [x] **TASK-LD-009** — `HexagramDetailPage.spec.ts`: with a `lineDynamics` fixture, the
      section renders the 2–5 correspondence row and a per-line row with the correct-position
      / central labels. → REQ-LD-022

## Close-out

- [x] **TASK-LD-010** — `yijing-core` + `apps/api` `composer test`/`stan`/`lint` green;
      `npm run verify` green; browser pass (`/hexagrams/63`, `/1`, `/44`, editor live-update,
      light + dark); fill `plan.md` note; flip `spec.md` → `implemented`; add SPEC-053 to both
      README tables. → REQ-LD-021, 022