# SPEC-043 — Statistics Charts

**Status:** implemented
**Owner:** unassigned
**Last updated:** 2026-08-28

## Problem

The Statistics page (SPEC-024) shows real aggregates but renders them as bare lists: hexagram
frequency is a `<ul>` of "N. 名 (pinyin) [count]" rows, tag frequency likewise, and the
yin/yang ratio is one sentence plus a thin `ProgressBar`. For "how has my practice actually
gone" data, a list makes comparison and shape hard to see — which hexagrams dominate, how
lopsided the yin/yang balance is, which tags cluster.

## Purpose

Render the three existing aggregates as small, legible charts — a horizontal bar chart for
hexagram frequency and tag frequency, a donut for the yin/yang ratio — with **no new runtime
dependency**: hand-drawn inline SVG, theme-aware via the existing PrimeVue CSS variables. The
API and the `Statistics` shape are unchanged.

## Scope

- New `src/shared/ui/BarChart.vue`: a horizontal bar chart from
  `{ label: string; value: number }[]`. Bars scale to the max value; each row shows the label,
  the bar, and the numeric value. Accessible: the whole chart is a `<table>` visually styled as
  bars (or an SVG with `role="img"` + a text summary) — pick the table approach so screen
  readers get the numbers for free. Props: `items`, optional `maxRows` (default all), optional
  `barLabel` for the aria context.
- New `src/shared/ui/DonutChart.vue`: a two-segment donut from `{ label; value }[]` (used here
  for yin vs yang), rendered as SVG `<circle>` with `stroke-dasharray`. A centered caption slot
  or prop for the headline number/percent. `role="img"` with an `aria-label` naming both
  segments and their percentages.
- `StatisticsPage.vue`:
  - Hexagram frequency → `<BarChart>` (label `"{n}. {chineseName}"`, value `count`), capped at
    the top 12 rows with a "+N more" note if longer (the API already returns them
    count-descending).
  - Yin/Yang ratio → `<DonutChart>` with the existing `{yin}/{yang} (x% / y%)` line as the
    caption. Keep the sentence; drop the `ProgressBar`.
  - Tag frequency → `<BarChart>` (label `name`, value `count`); the whole panel still only
    renders when there is at least one tag.
  - The three `Panel`s, headings, empty state, and the "N consultations" line stay.
- Colours: bars use `--p-primary-color`; the donut uses `--p-primary-color` for one segment and
  a muted track colour (`--p-content-border-color` or `--p-surface-300`) for the other, so it
  reads in both light and dark. No hard-coded hex.
- Localised: any new strings (e.g. `"+{count} more"`) go through `vue-i18n` (en + uk). The
  existing `statistics.*` keys are reused.

## Out of scope

- **Chart.js / `primevue/chart` / any charting library.** SPEC-001's minimal-dependency
  posture; these three charts are simple enough to draw directly.
- **New metrics** (activity-over-time, outcome sentiment, changing-line position frequency,
  most-revisited hexagrams). Worth a later spec; this one only re-presents the three aggregates
  that already exist.
- **Interactivity** — tooltips, click-to-filter, hover highlights. Static charts.
- **Any API or `Statistics`-shape change.**
- **Animating the bars/donut on load** beyond a CSS transition that respects
  `prefers-reduced-motion`.

## Functional requirements

- **REQ-STATC-001** — Hexagram frequency renders as a horizontal bar chart, bars proportional
  to `count`, count-descending, top 12 with a "+N more" note when there are more.
- **REQ-STATC-002** — The yin/yang ratio renders as a two-segment donut whose segment angles
  match the yin and yang proportions, with the existing count/percent sentence as its caption.
- **REQ-STATC-003** — Tag frequency renders as a horizontal bar chart; its panel still only
  appears when at least one tag exists.
- **REQ-STATC-004** — Both chart components expose their underlying numbers to assistive tech
  (bar chart as a real `<table>`; donut as `role="img"` with a descriptive `aria-label`).
- **REQ-STATC-005** — Charts use only PrimeVue theme CSS variables for colour and are legible in
  both light and dark themes.
- **REQ-STATC-006** — Empty history still shows the existing "no consultations yet" message and
  no charts.

## Non-functional requirements

- **REQ-STATC-020** — No new npm dependency; web bundle grows only by the two small components.
- **REQ-STATC-021** — New strings localised (en + uk).
- **REQ-STATC-022** — `prefers-reduced-motion: reduce` disables any bar/donut transition.
- **REQ-STATC-023** — `npm run verify` passes; `StatisticsPage.spec.ts` updated for the new
  structure (values still asserted, now inside chart markup).

## Data requirements

None. Consumes the existing `GET /api/statistics` response.

## API requirements

None.

## Edge cases

- One hexagram with all the casts (others zero) → its bar is full width, others (if shown)
  near-zero; no divide-by-zero (guard `max === 0`).
- All-yang or all-yin history → the donut is a single full segment; `aria-label` still names
  both ("100% yang, 0% yin").
- Exactly 12 hexagrams cast → no "+N more" note; 13+ → "+1 more" etc.
- A very long tag name → bar chart label cell wraps or ellipsizes, doesn't push the bar off the
  panel.
- `totalConsultations > 0` but `tagFrequency` empty → tag panel hidden (unchanged).

## Acceptance criteria

- [x] Hexagram frequency and tag frequency show as horizontal bar charts (`BarChart.vue`) with
      proportional bars and visible counts; hexagram list caps at 12 with a "+N more" note —
      verified live (rows "1. 乾 2", "17. 隨 2", fills 100%/100%/50%…, "+3 ще" note) +
      `StatisticsPage.spec.ts`.
- [x] Yin/Yang shows as a donut (`DonutChart.vue`) matching the proportions, with the
      count/percent caption — live: `aria-label` "46% Інь, 54% Ян", caption
      "47 інь / 55 ян (46% / 54%)".
- [x] Bar charts are real `<table>`s with `<th scope="row">` labels + a `.sr-only` caption; the
      donut is `role="img"` with an `aria-label` naming both segments and percentages —
      `BarChart.spec.ts` / `DonutChart.spec.ts`.
- [x] Charts use only theme CSS vars — verified computed styles: fill `--p-primary-color`
      (`rgb(59,130,246)` both themes), track `--p-content-border-color`
      (`rgb(226,232,240)` light / dark grey), no hard-coded hex in the components.
- [x] Empty history: unchanged "no consultations yet" message, no charts (`StatisticsPage.spec.ts`).
- [x] `npm run verify` passes (web 181 tests, api 312, yijing-core 55). No new npm dependency.

## Implementation note (2026-08-28)

- `shared/ui/BarChart.vue` (table + proportional `.bar-chart-fill`, `max===0` guard, width
  transition behind `prefers-reduced-motion: no-preference`) and `shared/ui/DonutChart.vue`
  (SVG `stroke-dasharray` arcs over a track circle, `role="img"` + percentage `aria-label`,
  caption prop). Both pure inline SVG/CSS — zero new deps.
- `StatisticsPage.vue`: hexagram + tag `<ul>`s → `<BarChart>`; yin/yang `<ProgressBar>` →
  `<DonutChart>` with the existing count/percent line as its caption. Panels, headings, empty
  state, `useStatusAnnouncer` unchanged. Hexagram chart capped at `HEXAGRAM_ROWS = 12` with a
  `statistics.andMore` note.
- The bar-chart label dropped the pinyin ("1. 乾" not "1. 乾 (Qián)") to keep labels short;
  pinyin is still on the hexagram detail pages.
