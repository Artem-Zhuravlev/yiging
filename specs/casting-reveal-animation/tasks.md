# Tasks — Casting Reveal Animation (SPEC-042)

- [x] **TASK-REVEAL-001** — `features/casting-reveal/castingRevealPreference.ts`:
      `isCastingRevealEnabled()` / `setCastingRevealEnabled()` over
      `localStorage['yijing-casting-reveal']` (absent → true); `prefersReducedMotion()`
      (`matchMedia`, jsdom-safe). → REQ-REVEAL-005, REQ-REVEAL-020
- [x] **TASK-REVEAL-002** — `features/casting-reveal/CastingReveal.vue`: props
      `{ consultation }`; fetch primary hexagram lines, mark changing; phase machine
      coins→lines(350ms each, bottom→top)→named; Skip / Continue / auto-advance (1.6s) →
      `router.push('/consultations/'+id)` once; clear all timers on unmount; navigate on
      `fetchHexagram` failure. → REQ-REVEAL-002, 003, 004, 006
- [x] **TASK-REVEAL-003** — `CastingReveal` template + scoped CSS: 3 flipping coins, six line
      rows (reuse the bar / broken-bar / changing-dot markup + look), name + Continue, always-
      present Skip; animations under `@media (prefers-reduced-motion: no-preference)`.
      → REQ-REVEAL-002, 003, 004
- [x] **TASK-REVEAL-004** — `NewConsultationPage.vue`: on cast success, immediate `push` when
      `prefersReducedMotion() || !isCastingRevealEnabled()`, else mount `<CastingReveal>` in
      place of the form; add the "Show casting animation" `Checkbox` (default from
      `isCastingRevealEnabled()`, persists on change). Keep SPEC-039 `aria-busy` + announce.
      → REQ-REVEAL-001, 005
- [x] **TASK-REVEAL-005** — i18n `newConsultation.showCastingAnimation`, `castingReveal.skip`,
      `castingReveal.continue`, `castingReveal.casting` (en + uk). → REQ-REVEAL-021
- [x] **TASK-REVEAL-006** — `CastingReveal.spec.ts`: lines reveal over fake timers (all 6 +
      name), Skip → immediate `push`, `fetchHexagram` rejection → still `push`, unmount
      mid-animation → no `push` after. → REQ-REVEAL-002, 003, 006
- [x] **TASK-REVEAL-007** — `NewConsultationPage.spec.ts`: existing success test stays green
      (force reduced-motion or opt-out so `push` is synchronous); add one test that with reveal
      on, success mounts `CastingReveal` (Skip button present) rather than navigating
      synchronously. `castingRevealPreference` unit test (default/persist/read-back).
      → REQ-REVEAL-022
- [x] **TASK-REVEAL-008** — `npm run verify` green; manual browser pass (reveal, Skip, Continue,
      opt-out persists, reduced-motion → no reveal); fill `plan.md` note; flip `spec.md` →
      `implemented`; add SPEC-042 to both README tables. → REQ-REVEAL-022