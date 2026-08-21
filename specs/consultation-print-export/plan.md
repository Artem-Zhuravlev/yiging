# Plan — Consultation Print / PDF Export (SPEC-027)

**Depends on spec status:** `approved`

## Technical approach

- `App.vue`'s `<nav>` gains `print:hidden`.
- `ConsultationPage.vue`:
  - New button next to the existing favorite toggle: `<button type="button" class="print:hidden"
    @click="printPage">Print / Export</button>`, backed by a `printPage()` function calling
    `window.print()` — Vue's template expression sandbox doesn't allow bare `window` access
    inline, only script-setup bindings and a small global allowlist, so this needs an actual
    function rather than `@click="window.print()"`.
  - `print:hidden` added to: the "← History" `router-link`, the favorite toggle button, the
    "Create Follow-up" `router-link`, the `<form>` for adding a note, the `<form>` for adding a
    tag, the "Save Context" submit button, the "Save Outcome" submit button, and the "Get
    Interpretation" button.
  - The "AI Interpretation" `<section>` gains `:class="{ 'print:hidden': interpretationState.status
    !== 'loaded' }"` — hidden under print unless content has actually been fetched, computed from
    state already in the component (no new state needed).

## Architecture decisions

- **CSS-only via Tailwind's built-in `print:` variant, no new print-specific component or
  route.** The entire feature is "hide these elements under `@media print`," which Tailwind's
  variant does declaratively per-element — no JS media-query listener, no separate print
  template.
- **`window.print()` directly in the template, no wrapper composable.** A single call site; a
  composable would be overhead for one line used in one place.

## Affected areas

- `apps/web/src/App.vue`
- `apps/web/src/pages/consultations/ConsultationPage.vue`
- `apps/web/src/pages/consultations/ConsultationPage.spec.ts`

## Data / schema changes

None.

## Risks / open questions

- None currently open.
