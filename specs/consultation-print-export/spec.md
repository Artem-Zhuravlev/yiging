# SPEC-027 — Consultation Print / PDF Export

**Status:** verified
**Owner:** unassigned
**Last updated:** 2026-08-21

## Problem

Feature 6 of the plan's next batch asks to export a consultation to PDF/a printable version —
question, hexagrams, text, interpretation, notes together. `ConsultationPage` already renders all
of that; there's no way today to get a clean copy of it out of the browser.

## Purpose

Add a "Print / Export" button that opens the browser's native print dialog (from which "Save as
PDF" is a standard destination on every major browser/OS — no server involved) over a print
stylesheet that hides interactive controls (nav, forms, buttons, the "← History" link) and shows
only the consultation's own content: question, method/date, both hexagram diagrams, changing
lines, follow-up/repeats links, notes, tags, context, outcome, and any already-loaded AI
interpretation.

## Scope

- `ConsultationPage.vue`: a "Print / Export" button (`window.print()`, no new dependency —
  browsers' native print-to-PDF is the export path, matching
  [SPEC-001](../project-architecture/spec.md)'s no-new-runtime-dependency posture; a PHP PDF-
  generation library would be a real new server dependency for something the browser already does
  for free).
- Tailwind's built-in `print:` variant hides, when printing: the main nav (`App.vue`), the
  "← History" back-link, the favorite toggle button, the "Compare hexagrams" link, the
  "Create Follow-up" link, the note-adding and tag-adding forms (inputs + submit buttons — their
  *existing* notes/tags lists stay visible), the "Save Context"/"Save Outcome" buttons, and the
  "Get Interpretation" button.
- The "AI Interpretation" section is hidden entirely when printing unless an interpretation has
  already been fetched (`interpretationState.status === 'loaded'`) — printing an empty dashed box
  with just a hidden button is worse than not showing the section at all.
- Everything else already on the page (hexagram diagrams, changing lines, follow-up/repeats read-
  only links, notes list, tags list, the context/outcome fields' current values, loaded
  interpretation content) is left visible as-is.

## Out of scope

- **Server-side PDF generation** (a PHP library producing an actual `.pdf` file/endpoint). Adds a
  real new dependency for a result the browser's own print dialog already produces; explicitly
  rejected per the architecture reasoning above.
- **Guaranteeing the output fits exactly one printed page.** A consultation with many notes, a
  full five-field context, an outcome, and a loaded interpretation is genuinely long; forcing it
  onto one page would mean truncating real content. The plan's "on one page" phrasing is read as
  "one cohesive export of everything," not a hard page-count constraint.
- **A dedicated print stylesheet for any other page** (hexagram detail, history list, statistics).
  The plan's feature names *consultation* export specifically.
- **Restyling notes/tags/context into non-editable-looking print elements.** Context/outcome
  values are already rendered as `<textarea>`s on-screen; printing them as-is (still legible, just
  boxed) is an acceptable, low-effort tradeoff over a parallel read-only rendering path this
  feature doesn't need to build.

## User behavior

```
/consultations/{id}
  -> click "Print / Export" -> browser's native print dialog opens
  -> nav, back-link, favorite toggle, follow-up link, add-note/add-tag forms, save buttons, and
     "Get Interpretation" (if not yet clicked) are all absent from the print preview
  -> question, hexagrams, changing lines, follow-up/repeats links, notes, tags, context, outcome,
     and interpretation (if already loaded) all appear
  -> user picks "Save as PDF" (or a physical printer) from the browser's own dialog
```

## Functional requirements

- **REQ-PRINT-001** — `ConsultationPage` MUST render a "Print / Export" button that calls
  `window.print()`.
- **REQ-PRINT-002** — When printing, the main nav, "← History" link, favorite toggle button,
  "Compare hexagrams" link, "Create Follow-up" link, the note/tag-adding forms, and the
  "Save Context"/"Save Outcome"/"Get Interpretation" buttons MUST NOT be visible.
- **REQ-PRINT-003** — When printing, the question, method/date, both hexagram diagrams, changing
  lines text, follow-up-to/follow-ups links, repeats links, existing notes, existing tags, current
  context field values, current outcome values, and (if already fetched) the AI interpretation
  content MUST remain visible.
- **REQ-PRINT-004** — The "AI Interpretation" section MUST NOT be visible when printing if no
  interpretation has been fetched yet for that page view.

## Non-functional requirements

- **REQ-PRINT-005** — No new dependency (frontend or backend) is introduced; the export path is
  the browser's own print/Save-as-PDF, via CSS only (Tailwind's `print:` variant).
- **REQ-PRINT-006** — The screen (non-print) rendering of `ConsultationPage` is visually
  unchanged except for the addition of the button itself.

## Data requirements

None.

## API requirements

None.

## Edge cases

- Printing before clicking "Get Interpretation" → the AI Interpretation section is fully absent,
  not shown with an empty/dashed placeholder.
- Printing a consultation with no notes, no tags, no context, no outcome, no follow-up links →
  each of those sections' existing empty-state rendering (e.g. no notes list rendered at all,
  matching current screen behavior) simply carries over unchanged to print.

## Acceptance criteria

- [x] "Print / Export" button calls `window.print()`.
- [x] Nav, back-link, favorite toggle, follow-up-creation link, add-note/add-tag forms, and save/
      get-interpretation buttons are hidden under `@media print`.
- [x] Question, hexagrams, changing lines, follow-up/repeats links, notes, tags, context, outcome
      values remain visible under `@media print`.
- [x] The AI Interpretation section is hidden under print when nothing has been fetched yet, and
      visible (with its content) once it has.
- [x] On-screen (non-print) rendering is otherwise unchanged.
- [x] `npm run verify` passes end to end.
- [x] Manually verified against the real running API/UI (print preview inspected).
