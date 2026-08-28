# Plan — Casting Reveal Animation (SPEC-042)

## Files

### New
- `apps/web/src/features/casting-reveal/castingRevealPreference.ts` — `isCastingRevealEnabled()`
  / `setCastingRevealEnabled(bool)` over `localStorage['yijing-casting-reveal']`
  (absent → `true`), plus `prefersReducedMotion()` (`matchMedia`, guarded for jsdom).
- `apps/web/src/features/casting-reveal/CastingReveal.vue` — props `{ consultation: Consultation }`,
  emits nothing; drives its own navigation via `useRouter()`.
  - `onMounted`: `fetchHexagram(consultation.primaryHexagram.kingWenNumber)` → build
    `HexagramLine[]` with `changing: changingLinePositions.includes(pos)`. On error → `finish()`.
  - phases via a `phase` ref: `'coins' | 'lines' | 'named'`. Timers: coins→lines after 1000ms;
    then a per-line interval revealing index 0..5 (350ms); then phase `'named'`; then a 1600ms
    timer calling `finish()`.
  - `finish()` / Skip / Continue → `router.push('/consultations/' + consultation.id)` (guard a
    `done` flag so it fires once).
  - `onBeforeUnmount`: clear every timer; set `done = true`.
  - template: a centered panel — coins (3 × `<span class="coin">` with a CSS flip
    `@keyframes`), the six line rows (reuse `HexagramLines`-style markup; show only the first
    `revealedCount`), the name line + Continue, and a persistent Skip button. `prefers-reduced-
    motion` is already handled upstream (component won't mount), but keep the CSS animations
    behind `@media (prefers-reduced-motion: no-preference)` as defense-in-depth.
- `apps/web/src/features/.gitkeep` stays.

### Changed
- `NewConsultationPage.vue`:
  - add `const showReveal = ref(false)` and `const castResult = ref<Consultation | null>(null)`.
  - `submit()`: on success, if `prefersReducedMotion() || !isCastingRevealEnabled()` →
    `router.push('/consultations/' + consultation.id)` (unchanged path); else
    `castResult.value = consultation; showReveal.value = true`.
  - template: `<CastingReveal v-if="showReveal && castResult" :consultation="castResult" />`
    replacing the `<form>` (wrap existing form in `v-else` / `v-if="!showReveal"`).
  - add a checkbox above/below the submit button: `Checkbox` bound to a `revealEnabled` ref
    initialised from `isCastingRevealEnabled()`, `@change` → `setCastingRevealEnabled(...)`.
    Label `t('newConsultation.showCastingAnimation')`.
  - keep the `aria-busy` + announce-on-submit from SPEC-039.
- `i18n/locales/{en,uk}.ts`: `newConsultation.showCastingAnimation`,
  `castingReveal.skip`, `castingReveal.continue`, `castingReveal.casting`.

## Testing

- `apps/web/src/features/casting-reveal/CastingReveal.spec.ts`:
  - mocks `fetchHexagram` + `vue-router`'s `useRouter`.
  - reveals lines over fake timers (`vi.useFakeTimers()` + `vi.advanceTimersByTimeAsync`),
    asserts all 6 eventually shown and the name appears.
  - Skip button → `push('/consultations/<id>')` immediately.
  - `fetchHexagram` rejects → `push` still called (REQ-REVEAL-006).
  - unmount mid-animation → no `push` after unmount.
- `NewConsultationPage.spec.ts`:
  - existing success test: keep `prefers-reduced-motion` effectively on in jsdom (matchMedia
    stub returns `matches:false` — so add a stub making the reduced-motion query `true` in that
    test, or set the opt-out flag) so navigation stays synchronous and the assertion holds
    unchanged. Add one test: with reveal enabled + motion allowed, success mounts
    `CastingReveal` (find the Skip button) instead of navigating synchronously.
- `castingRevealPreference` unit test: default true; set false persists; reads back.

## Verify

`npm run verify`; manual browser: cast with animation on (watch reveal, Skip, Continue), untick
the checkbox and cast again (immediate), reload to confirm the checkbox stays unticked, set OS
reduced-motion and confirm no reveal.

## Verification note (2026-08-28)

- `npm run verify` green: web lint/typecheck/172 tests/build, api 312, yijing-core 55.
- New web specs: `CastingReveal.spec.ts` (4), `castingRevealPreference.spec.ts` (3), plus one
  added `NewConsultationPage` test; existing `NewConsultationPage` tests kept green by defaulting
  them to the opt-out path in `beforeEach`.
- Live browser pass on the running stack: reveal plays and auto-advances to the real detail
  page; unticking the checkbox (persisted) skips the reveal and navigates immediately; the
  checkbox re-renders unchecked from the stored flag after reload.
