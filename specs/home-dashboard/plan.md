# Plan — Home Dashboard (SPEC-045)

## Files

### Changed
- `apps/web/src/pages/home/HomePage.vue`
  - Keep the existing `state` machine for the Hexagram of the Day and its `useStatusAnnouncer`.
  - Add two more independent refs:
    - `recent = ref<ConsultationListItem[]>([])` (empty = nothing to show; failure leaves it empty).
    - `totalCast = ref<number | null>(null)` (null = hidden; failure leaves it null).
  - In `onMounted`, fire all three fetches without awaiting each other:
    - the existing `fetchHexagram(hexagramOfTheDayNumber())`,
    - `fetchConsultations({ limit: 4 }).then(p => recent.value = p.items).catch(() => {})`,
    - `fetchStatistics().then(s => { if (s.totalConsultations > 0) totalCast.value = s.totalConsultations }).catch(() => {})`.
  - Template, below the two buttons and above/below the HOTD card (keep HOTD where it is; put
    the new blocks after it):
    - `<section v-if="recent.length > 0">` — `h2` `t('home.recent')`, a `<ul>` of
      `router-link`s (question / `{n}. {name} → {n}. {name}` / `toLocaleDateString`), then a
      `router-link to="/consultations"` `t('home.viewAll')`.
    - `<p v-if="totalCast !== null">` — `router-link to="/statistics"`
      `t('home.consultationsCast', { count: totalCast })`.
  - Layout: the page is already
    `flex flex-column align-items-center ... text-center`; wrap the new sections so they read as
    a narrow left-aligned column within that (e.g. `class="w-full text-left"` with
    `style="max-width: 22rem"` or reuse `container-sm`-ish). Keep it visually calm.
- `apps/web/src/i18n/locales/{en,uk}.ts`: `home.recent` = "Recent" / "Останні",
  `home.viewAll` = "View all" / "Переглянути всі",
  `home.consultationsCast` = "{count} consultations cast" / "Кинуто консультацій: {count}".

## Testing — `HomePage.spec.ts`
- Add mocks for `../../entities/consultation/api` (`fetchConsultations`) and
  `../../entities/statistics/api` (`fetchStatistics`); default them in `beforeEach` to an empty
  page / `{ totalConsultations: 0, ... }` so the existing HOTD-only tests are unaffected.
- New tests:
  - with a 4-item page + `totalConsultations: 12`: the four questions render as links to
    `/consultations/{id}`, a "View all" link, and a "12 consultations cast" link to
    `/statistics`.
  - `fetchConsultations` rejects, `fetchStatistics` resolves with a count → no Recent section,
    still shows the count line and the HOTD.
  - empty history (`items: []`, `totalConsultations: 0`) → neither new section; HOTD + buttons
    still present (this is essentially the existing test, re-asserted).
- Existing tests: they mock only `fetchHexagram`; add the two new module mocks so the component
  mounts. The "renders project title / core links" and error tests keep asserting the same
  strings.

## Verify

`npm run verify`; browser on `/` with the seeded history: recent list + count line render and
link correctly; then point the API at an empty DB (or mock) and confirm the page is the plain
splash; check mobile width + dark.


## Verification note (2026-08-28)

- `npm run verify` green (web 188 tests incl. 3 new HomePage tests; api 312; yijing-core 55).
- Live pass on `/` with the seeded history: HOTD (22. 賁) plus a "Останні" list of 4 recent
  consultations linking to their detail pages, a "Переглянути всі" link to /consultations, and
  "Кинуто консультацій: 17" linking to /statistics. Empty-history splash covered by unit test.
