# Plan — Interpretation UI (SPEC-010)

**Depends on spec status:** `approved`

## Technical approach

```
apps/web/src/
├── entities/interpretation/
│   ├── model.ts    Interpretation type matching SPEC-008's JSON shape
│   └── api.ts       requestInterpretation(consultationId)
└── pages/consultations/ConsultationPage.vue    + interpretation section
```

- `requestInterpretation(id: string): Promise<Interpretation>` wraps
  `apiPost<Interpretation>(\`/interpretations/${id}\`, {})` (SPEC-008's endpoint takes no body;
  an empty object keeps `apiPost`'s existing signature, which always sends a JSON body, rather
  than adding a bodyless variant for a single call site).
- `ConsultationPage.vue` gains a second, independent piece of local state alongside the
  existing consultation-load state (`interpretationState: { status: 'idle' | 'loading' |
  'error' | 'loaded', ... }`) — deliberately not folded into the page's existing `State` union,
  since the two loads are independent (interpretation can fail/retry without touching the
  already-loaded consultation, per REQ-INTUI-004).
- Button handler: guard on `interpretationState.status !== 'loading'` before starting a new
  request (REQ-INTUI-002 — no concurrent requests from rapid clicks).
- Rendering: a bordered/labeled `<section>` ("AI Interpretation") below the existing detail,
  each field rendered `v-if` its value is present — `changingLineMeaning`/`transition` simply
  don't produce a row when `null` (REQ-INTUI-003), no placeholder text needed since their
  absence is itself meaningful (no changing lines) rather than an error state.

## Architecture decisions

- **No Pinia store, no caching.** Matches SPEC-007/009's established call — nothing yet shares
  interpretation data across routes/components, and the backend recomputes fresh every time
  anyway (SPEC-008 doesn't persist), so a client-side cache would just paper over that with a
  second source of truth that could go stale relative to it.
- **Interpretation load state is separate from the consultation load state.** A failed/retried
  interpretation request must never re-trigger the consultation fetch or disturb what's already
  rendered (REQ-INTUI-004) — two independent state machines is simpler than one combined state
  with cross-cutting transitions to reason about.
- **No frontend "this is a mock" messaging.** SPEC-008's `uncertainties` field already states
  plainly that the interpretation came from a mock provider; duplicating that in the frontend
  would drift out of sync the moment a real provider lands and stops saying it.

## Affected areas

- `apps/web/src/entities/interpretation/model.ts`
- `apps/web/src/entities/interpretation/api.ts`
- `apps/web/src/pages/consultations/ConsultationPage.vue` (+ test additions)

## Data / schema changes

None.

## Risks / open questions

- None currently open.
