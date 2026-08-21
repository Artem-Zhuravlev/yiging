# Plan — Personal Statistics (SPEC-024)

**Depends on spec status:** `approved`

## Technical approach

- `apps/api/src/Readings/HexagramFrequency.php`, `TagFrequency.php` — tiny readonly value
  objects, same shape/style as `ConsultationSummary`.
- `apps/api/src/Readings/ConsultationStatistics.php` — readonly, holds the four aggregate
  fields.
- `apps/api/src/Readings/StatisticsRepository.php` (interface) + `SqliteStatisticsRepository.php`:
  - `compute()` runs three queries: `SELECT COUNT(*) FROM consultations` for the total;
    `SELECT primary_king_wen_number, COUNT(*) FROM consultations GROUP BY primary_king_wen_number
    ORDER BY COUNT(*) DESC, primary_king_wen_number ASC` for hexagram frequency (each row's
    `chineseName`/`pinyin` resolved via `Hexagram::fromKingWenNumber()`, a pure `yijing-core`
    call — no extra query per row); `SELECT primary_king_wen_number FROM consultations` (one
    column, every row) fed through `Hexagram::fromKingWenNumber()->lines` to tally
    yin/yang — this is the one place genuinely needing PHP-side work, since King Wen line
    polarity is domain logic `yijing-core` owns, not something SQL can compute; and
    `SELECT t.name, COUNT(*) FROM tags t INNER JOIN consultation_tags ct ON ct.tag_id = t.id
    GROUP BY t.name ORDER BY COUNT(*) DESC, t.name ASC` for tag frequency.
- `apps/api/src/Readings/StatisticsController.php` — no persistence beyond
  `Database::connect($config)`, same constructor pattern as `ConsultationController`; `index()`
  calls `compute()` and maps the value objects to the response shape.
- `config/routes.php` gains `GET /api/statistics`.
- `apps/web/src/entities/statistics/model.ts` — `Statistics` interface mirroring the response.
- `apps/web/src/entities/statistics/api.ts` — `fetchStatistics(): Promise<Statistics>`.
- `apps/web/src/pages/statistics/StatisticsPage.vue` — loading/error/loaded states matching this
  app's established page pattern (`ConsultationHistoryPage`'s `State` union), rendering the four
  sections; an explicit `totalConsultations === 0` branch for the empty-history message.
- `router/index.ts` gains `/statistics`; `App.vue`'s nav gains a "Statistics" link.

## Architecture decisions

- **Hexagram/tag frequency computed via SQL `GROUP BY`; yin/yang computed in PHP over one
  single-column query.** `GROUP BY` handles the two purely-relational aggregates efficiently and
  matches REQ-STATS-009. Yin/yang can't be a SQL aggregate because line polarity for a given King
  Wen number is `yijing-core` domain logic (binary-to-lines mapping), not a stored column — so
  that one aggregate necessarily loops in PHP, but only over a single narrow column, not full
  `Consultation` hydration.
- **A new `StatisticsRepository`/`StatisticsController` pair, not methods bolted onto
  `ConsultationRepository`/`ConsultationController`.** This aggregates across the whole table for
  a different purpose (a dashboard, not a single consultation's data) and returns a differently-
  shaped, non-`Consultation` response — keeping it a separate concern avoids overloading either
  existing class the way `ConsultationRepository` already covers several distinct query shapes
  (SPEC-021/023 already extended it three times; this is intentionally not a fourth).
- **No zero-padding `hexagramFrequency` to all 64 hexagrams.** A user who has cast 12
  consultations across 8 distinct hexagrams doesn't need 56 `count: 0` entries — the frontend
  only needs to render what was actually cast.

## Affected areas

- `apps/api/src/Readings/HexagramFrequency.php` (new)
- `apps/api/src/Readings/TagFrequency.php` (new)
- `apps/api/src/Readings/ConsultationStatistics.php` (new)
- `apps/api/src/Readings/StatisticsRepository.php` (new)
- `apps/api/src/Readings/SqliteStatisticsRepository.php` (new)
- `apps/api/src/Readings/StatisticsController.php` (new)
- `apps/api/tests/Readings/SqliteStatisticsRepositoryTest.php` (new)
- `apps/api/tests/Readings/StatisticsControllerTest.php` (new)
- `apps/api/config/routes.php`
- `apps/web/src/entities/statistics/model.ts` (new)
- `apps/web/src/entities/statistics/api.ts` (new)
- `apps/web/src/pages/statistics/StatisticsPage.vue` (new)
- `apps/web/src/pages/statistics/StatisticsPage.spec.ts` (new)
- `apps/web/src/router/index.ts`
- `apps/web/src/App.vue`

## Data / schema changes

None.

## Risks / open questions

- None currently open.
