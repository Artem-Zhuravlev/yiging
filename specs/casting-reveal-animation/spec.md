# SPEC-042 — Casting Reveal Animation

**Status:** implemented
**Owner:** unassigned
**Last updated:** 2026-08-28

## Problem

Casting a consultation is the emotional centre of an I Ching practice — the moment of asking
and receiving. In the app it is currently instantaneous and invisible: the user submits the
New Consultation form and is dropped straight onto the detail page with the finished hexagram
already sitting there. There's no beat of anticipation, no sense of the lines *forming*. A
coin-and-yarrow tradition that's all about ritual and attention gets none in the UI.

## Purpose

Add a brief, skippable reveal between submitting the form and landing on the detail page: three
coins fall, then the six lines build from the bottom up, then the hexagram names itself — using
the *real* cast result the server already returned, not a fake. Purely presentational; the
domain cast is unchanged.

## Scope

- After `createConsultation()` resolves on `NewConsultationPage`, instead of navigating
  immediately, render a `CastingReveal` overlay/panel for the just-cast consultation.
- `CastingReveal` (`src/features/casting-reveal/CastingReveal.vue`) takes the created
  `Consultation` (it has `primaryHexagram` summary + `changingLinePositions`), fetches the
  primary hexagram's line polarities via the existing `fetchHexagram(kingWenNumber)` (same call
  `ConsultationPage`/`SharedConsultationPage` already make), marks the changing positions, and:
  1. shows three coins with a short CSS flip animation (~1s),
  2. reveals the six lines one at a time, bottom (position 1) → top (position 6), ~350ms apart,
     each with a small fade/slide-in; a changing line gets its changing-dot with a brief pulse,
  3. once all six are shown, reveals `"{kingWenNumber}. {chineseName} ({pinyin})"` and a
     **Continue** button; auto-advances to `/consultations/{id}` after ~1.6s if the user does
     nothing.
- A **Skip** control is present for the whole animation; activating it navigates to the detail
  page at once.
- **Reduced motion / opt-out**: if `matchMedia('(prefers-reduced-motion: reduce)')` matches,
  *or* the user has turned the reveal off (a `localStorage` flag `yijing-casting-reveal` set to
  `"off"`), `NewConsultationPage` navigates immediately as it does today — `CastingReveal` never
  mounts. A small "Show casting animation" checkbox on the New Consultation form (default on)
  toggles that flag, so a user who finds it slow can permanently skip it.
- The line rendering reuses `HexagramLines.vue`'s bar/broken-bar/changing-dot markup and CSS
  (extracted to a shared style or duplicated minimally) so the revealed lines look identical to
  everywhere else they appear.
- Localised (en + uk): "Casting…", "Continue", "Skip", "Show casting animation".

## Out of scope

- **Animating the follow-up / manual-entry path differently.** Manual casting still produces a
  `Consultation` and gets the same reveal (the coins are decorative there, but the line-by-line
  build still reads well; a user who dislikes it uses the opt-out).
- **A real per-line coin simulation.** The server performs the cast atomically and returns the
  result; the animation dramatises that result, it does not re-roll it. (Stated plainly here so
  a reviewer doesn't think the reveal is deciding anything.)
- **Sound.**
- **Any change to the casting API, the domain model, routes, or the detail page.**
- **A reveal when opening an *existing* consultation.** Only the fresh-cast flow.
- **Persisting that a consultation "has been revealed"** — re-casting always reveals; navigating
  back to an old consultation never does.

## User behavior

1. User fills in the question, picks a method, submits.
2. The submit button shows "Casting…"; on success the form is replaced by the reveal.
3. Three coins flip and settle. The six lines appear from the bottom up. Changing lines pulse.
4. The hexagram's number and name fade in with a "Continue" button; after ~1.6s (or on
   Continue, or on Skip at any point) the user is on `/consultations/{id}`.
5. A user with reduced-motion set, or who unticked "Show casting animation", skips straight to
   step 5's destination with no reveal.

## Functional requirements

- **REQ-REVEAL-001** — On a successful cast, `NewConsultationPage` shows `CastingReveal` for the
  created consultation instead of navigating immediately — unless reduced-motion is set or the
  opt-out flag is `"off"`, in which case it navigates immediately (today's behaviour).
- **REQ-REVEAL-002** — `CastingReveal` renders the six lines of the consultation's *actual*
  primary hexagram, bottom-to-top, one at a time, with the correct changing-line marks.
- **REQ-REVEAL-003** — A Skip control navigates to `/consultations/{id}` immediately, available
  throughout the animation.
- **REQ-REVEAL-004** — After the lines finish, the hexagram number/name and a Continue button
  appear; the page auto-navigates to `/consultations/{id}` after a short delay if untouched.
- **REQ-REVEAL-005** — A "Show casting animation" checkbox on the New Consultation form (default
  checked) persists an opt-out in `localStorage` (`yijing-casting-reveal`).
- **REQ-REVEAL-006** — If `fetchHexagram` fails during the reveal, `CastingReveal` navigates to
  `/consultations/{id}` rather than trapping the user (the detail page will surface any real
  error).

## Non-functional requirements

- **REQ-REVEAL-020** — Respects `prefers-reduced-motion: reduce` (no reveal at all).
- **REQ-REVEAL-021** — All new strings localised (en + uk).
- **REQ-REVEAL-022** — No API/route/domain change; `npm run verify` passes; existing
  `NewConsultationPage.spec.ts` still passes (its success-path assertion — `push` called with
  `/consultations/{id}` — must still hold, though possibly after the reveal/skip rather than
  synchronously).

## Data requirements

None persisted server-side. One `localStorage` key, `yijing-casting-reveal` (`"on"`/`"off"`,
absent = on).

## API requirements

None.

## Edge cases

- Reduced-motion set → reveal never mounts; immediate navigation (unchanged behaviour).
- Opt-out flag `"off"` → same.
- `fetchHexagram` fails inside the reveal → navigate to the detail page anyway (REQ-REVEAL-006).
- User clicks Skip during the coin phase, before lines render → immediate navigation, no error.
- Manual method with zero changing lines → lines still build bottom-to-top, no changing dots,
  Continue appears normally.
- Component unmounted (user hits browser Back) mid-animation → all timers cleared in
  `onBeforeUnmount`, no navigation fired afterward.

## Acceptance criteria

- [x] Submitting a valid cast shows the coin + line-by-line reveal, then the hexagram name and a
      Continue button, then lands on the detail page (auto or on click) — verified live: form
      replaced by `.casting-reveal` (3 coins + "Casting…" + Skip), lines built 2→6, then
      auto-navigated to `/consultations/<real-uuid>`.
- [x] Skip navigates immediately, at any point — `CastingReveal.spec.ts` (Skip during coin
      phase → `push('/consultations/con-1')`).
- [x] The revealed lines match the consultation's real primary hexagram and changing lines —
      `CastingReveal` fetches `fetchHexagram(primaryHexagram.kingWenNumber)` and marks
      `changingLinePositions`; spec asserts 6 `.reveal-line` rows.
- [x] With the checkbox unticked (persisted `"off"`), there is no reveal and navigation is
      immediate — verified live (`hasReveal:false`, straight to `/consultations/<uuid>`);
      `prefersReducedMotion()` short-circuits the same way (`NewConsultationPage.submit`).
- [x] The opt-out checkbox state persists across reloads — verified live (checkbox renders
      unchecked from the stored flag); `castingRevealPreference.spec.ts`.
- [x] `npm run verify` passes (web 172 tests, api 312, yijing-core 55);
      `NewConsultationPage.spec.ts` green (defaults to the no-animation path; one added test
      asserts the reveal mounts when enabled).

## Implementation note (2026-08-28)

- `src/features/casting-reveal/`: `castingRevealPreference.ts` (localStorage
  `yijing-casting-reveal`, `prefersReducedMotion()`), `CastingReveal.vue` (phase machine
  `coins → lines → named`, timers cleared on unmount, `router.push` once via a `done` guard,
  navigates on `fetchHexagram` failure), plus specs.
- `NewConsultationPage.vue`: on cast success, `push` immediately when
  `prefersReducedMotion() || !isCastingRevealEnabled()`, else render `<CastingReveal>` in place
  of the form; a "Show casting animation" `Checkbox` (default on) persists the opt-out. SPEC-039
  `aria-busy` + announce retained.
- Timings: coins 1000ms, 350ms/line bottom→top, 1600ms hold before auto-advance. Animations are
  behind `@media (prefers-reduced-motion: no-preference)` as defense-in-depth (the component
  also just doesn't mount under reduced motion).
