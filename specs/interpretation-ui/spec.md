# SPEC-010 — Interpretation UI

**Status:** verified
**Owner:** unassigned
**Last updated:** 2026-08-14

## Problem

SPEC-008 exposed `POST /api/interpretations/{id}`, but nothing in `apps/web` calls it. The
plan's own consultation flow (section 23) ends with "user launches interpretation, gets AI
interpretation" — `ConsultationPage` (SPEC-009) currently stops at the hexagram/notes/tags
detail with no way to request one.

## Purpose

Add an on-demand "Get Interpretation" trigger to `ConsultationPage`, rendering SPEC-008's
structured `Interpretation` — completing the plan's UI flow for a single consultation.

## Scope

- `entities/interpretation`: types matching SPEC-008's JSON shape, plus
  `requestInterpretation(consultationId)` (a `POST`, not a `GET` — interpreting isn't a passive
  read, it's a triggered action, and the mock provider recomputes fresh each call since SPEC-008
  explicitly doesn't persist).
- `ConsultationPage.vue`: a "Get Interpretation" button below the existing hexagram/notes/tags
  detail. On click: `POST /api/interpretations/{id}`, show a loading state, then render all 8
  fields. Errors show inline without disturbing the rest of the already-loaded consultation.
- Visual/structural separation between the consultation's own canonical data (hexagrams,
  classical text — already on the page from SPEC-009) and the interpretation section, so a
  reader can't mistake one for the other — matches the plan's explicit requirement that AI
  output must never be presented as if it were the canonical source (plan section 20: "AI
  должен явно отделять: canonical source от AI interpretation").

## Out of scope

- Caching/reusing a previously-fetched interpretation across page visits (no store, matches
  SPEC-007/009's "no Pinia store until something needs to share data across routes" call) —
  each click is a fresh request, consistent with the backend not persisting results either.
- Any different UI once a real (non-mock) `InterpretationProvider` exists — SPEC-008's
  `uncertainties` field already communicates the mock-vs-real distinction from the API; this
  spec just renders whatever the API returns; no frontend-side "this is fake" messaging
  duplicating what the backend already says.
- Automatically triggering interpretation on page load — the plan's flow has the user actively
  request it (step 7, "user launches interpretation"), not receive it unprompted.

## User behavior

```
On /consultations/{id}, after the existing detail loads:
  -> "Get Interpretation" button visible
  -> click -> loading state -> POST /api/interpretations/{id}
  -> success: summary, coreTheme, situation, changingLineMeaning/transition (only shown when
     non-null — omitted, not blank, when there were no changing lines), practicalReflection,
     uncertainties, sourceReferences all rendered
  -> failure (network error, unexpected 5xx): inline error message, button re-enabled to retry;
     the rest of the page (hexagrams, notes, tags) is unaffected
```

## Functional requirements

- **REQ-INTUI-001** — `ConsultationPage` MUST show a button that, when clicked, calls
  `requestInterpretation(consultation.id)` and renders the result — MUST NOT fetch it
  automatically on page load.
- **REQ-INTUI-002** — While the request is pending, the button MUST show a distinct loading
  state (e.g. disabled + "Interpreting…") — MUST NOT allow a second concurrent request from the
  same click sequence.
- **REQ-INTUI-003** — On success, MUST render all `Interpretation` fields that are present;
  `changingLineMeaning`/`transition` MUST be omitted (not rendered as an empty/null row) when
  the API returns `null` for them.
- **REQ-INTUI-004** — On failure, MUST show an inline error message scoped to the
  interpretation section only — the already-rendered consultation detail (hexagrams, notes,
  tags) MUST remain visible and unaffected, and the button MUST remain clickable to retry.
- **REQ-INTUI-005** — The interpretation section MUST be visually distinct from the
  consultation's own canonical hexagram/text section (e.g. a clearly labeled, separately
  bordered block) — never interleaved with or styled identically to the canonical data.

## Non-functional requirements

- **REQ-INTUI-006** — No component outside `entities/interpretation` may call
  `apiPost`/`apiGet`/`fetch` directly for interpretation data (mirrors REQ-HEXUI-007/
  REQ-CONSUI-008 from SPEC-007/009).

## Data requirements

None.

## API requirements

Consumes SPEC-008's `POST /api/interpretations/{id}` as-is; no backend changes.

## Edge cases

- Consultation with zero changing lines → interpretation still renders (summary/coreTheme/
  situation/practicalReflection from the primary hexagram alone); `changingLineMeaning`/
  `transition` rows simply don't appear.
- Clicking "Get Interpretation" twice in a row (after the first completes) → a second, fresh
  request — allowed, since SPEC-008 doesn't persist and recomputing is cheap with the mock
  provider (no debounce/cache needed for this pass).
- Interpreting an unknown/deleted consultation id → can't happen through this UI (the page
  itself already 404'd before the button would ever render), so no separate handling needed
  beyond the generic error state.

## Acceptance criteria

- [x] Clicking "Get Interpretation" on a real consultation (manually verified against the
      running API) shows a loading state, then all populated `Interpretation` fields.
- [x] A consultation with no changing lines shows the interpretation without
      changingLineMeaning/transition rows.
- [x] A simulated fetch failure shows an inline error without disturbing the rest of the page.
- [x] The interpretation section is visually and structurally separate from the canonical
      hexagram/text section already on the page.
- [x] Component tests (fetch mocked) cover: successful render, no-changing-lines render, and
      error state.
- [x] `npm run verify` passes end to end.
- [x] No component outside `entities/interpretation` imports `shared/api` directly for
      interpretation data.

Implemented: `entities/interpretation` (model/api) and a bordered "AI Interpretation" section
on `ConsultationPage.vue` with its own independent loading/error/loaded state, never disturbing
the already-loaded consultation detail. 6 new frontend tests (35 total in `apps/web`);
`npm run verify` passes end to end. Manually verified against the real running API in the
browser preview on two real consultations: one with a changing line (all 8 fields render,
including `changingLineMeaning`/`transition`) and one with none (those two rows correctly
absent, no blank placeholders).

**Found and fixed along the way:** a template `v-for` loop variable named `ref` shadowed the
`ref()` import from Vue (ESLint's `vue/no-template-shadow` caught it) — renamed to `sourceRef`.
