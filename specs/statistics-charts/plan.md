# Plan — Statistics Charts (SPEC-043)

## Files

### New
- `apps/web/src/shared/ui/BarChart.vue`
  - props: `items: { label: string; value: number }[]`, `caption?: string` (aria context).
  - renders a `<table>`: `<caption class="sr-only">`, header row (`Label` / `Value`), one row
    per item. Each row: `<th scope="row">{label}</th>`, then a `<td>` containing a bar
    (`<div class="bar" :style="{ width: pct + '%' }">`) and the numeric value.
  - `pct = max === 0 ? 0 : Math.round((value / max) * 100)`.
  - bar colour `var(--p-primary-color)`; transition on width behind
    `@media (prefers-reduced-motion: no-preference)`.
- `apps/web/src/shared/ui/DonutChart.vue`
  - props: `segments: { label: string; value: number }[]` (expects 2 here but handle N),
    `caption?: string`.
  - SVG: a background `<circle>` (track, `stroke: var(--p-content-border-color)`) plus one
    `<circle>` per segment with `stroke: var(--p-primary-color)` for the first and a muted tone
    for the rest, positioned via `stroke-dasharray` / `stroke-dashoffset` around a `r=16`
    circle (`circumference = 2πr`). `transform: rotate(-90deg)` so it starts at 12 o'clock.
  - wrapper `role="img"` with `:aria-label` = segments + percentages; `<figcaption>`/slot shows
    `caption`.
  - For the 2-segment yin/yang case only the primary segment needs drawing over the track;
    keep it general but simple.
- Specs: `BarChart.spec.ts`, `DonutChart.spec.ts`.

### Changed
- `apps/web/src/pages/statistics/StatisticsPage.vue`
  - import `BarChart`, `DonutChart`.
  - `hexagramBars = computed(() => statistics.hexagramFrequency.slice(0, 12).map(h => ({
     label: \`\${h.kingWenNumber}. \${h.chineseName}\`, value: h.count })))`;
    `hexagramOverflow = Math.max(0, hexagramFrequency.length - 12)`.
  - `tagBars = tagFrequency.map(tg => ({ label: tg.name, value: tg.count }))`.
  - `yinYangSegments = [{ label: t('common.yin'), value: yin }, { label: t('common.yang'), value: yang }]`.
  - Replace the hexagram `<ul>` with `<BarChart :items="hexagramBars" .../>` + a
    `<p v-if="hexagramOverflow">{{ t('statistics.andMore', { count: hexagramOverflow }) }}</p>`.
  - Replace the yin/yang `<ProgressBar>` with `<DonutChart :segments="yinYangSegments"
     :caption="<existing yinYangLine string>" />`; keep the sentence too (or fold it into the
    caption).
  - Replace the tag `<ul>` with `<BarChart :items="tagBars" />`.
  - Keep `useStatusAnnouncer`, the `Panel`s, headings, `state.status` handling, empty state.
- `apps/web/src/i18n/locales/{en,uk}.ts`: `statistics.andMore` = `"+{count} more"` /
  `"+{count} ще"`.
- `apps/web/src/style.css`: `.sr-only` already exists (SPEC-039) — reuse for the table caption.

## Testing

- `BarChart.spec.ts`: renders a row per item; widest bar is the max value; `max === 0` → all
  bars 0%; each value is in the DOM text; it's a `<table>` with row headers.
- `DonutChart.spec.ts`: `role="img"`; `aria-label` contains both segment labels and their
  rounded percentages; all-one-segment case → "100%" / "0%".
- `StatisticsPage.spec.ts`: update to find counts inside the new markup — assert a
  `BarChart` (table) exists with the hexagram labels/counts, the donut `aria-label` reflects
  the ratio, tag panel still gated, empty state unchanged, `>12` hexagrams → "+N more".

## Verify

`npm run verify`; browser: seed history has data → check bar charts + donut render and match
the numbers in light and dark (`resize_window` colorScheme), and the empty state on a fresh DB.

## Verification note (2026-08-28)

- `npm run verify` green (web 181 tests incl. new `BarChart.spec.ts` / `DonutChart.spec.ts` and
  updated `StatisticsPage.spec.ts`; api 312; yijing-core 55). No new npm dependency.
- Live pass on the seeded history: two horizontal bar charts (hexagram freq with a "+3 ще"
  overflow note, tag freq) with proportional fills and visible counts; the yin/yang donut arc
  matches 46% / 54% with the count/percent caption. Computed styles confirm theme CSS vars only
  (`--p-primary-color` fill, `--p-content-border-color` track) — legible in light and dark.