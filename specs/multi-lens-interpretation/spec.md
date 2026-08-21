# SPEC-033 — Multi-Lens Interpretation

**Status:** verified
**Owner:** unassigned
**Last updated:** 2026-08-21

## Problem

Feature 6 of the plan's next batch asks for layered interpretation — independent readings of the
same consultation through different lenses (psychological, practical, symbolic), rather than one
fixed framing. `POST /api/interpretations/{id}` today always produces the same kind of reading
regardless of what the person actually wants from it.

Per the user's explicit direction this session: **Gemini only** — this spec does not add
multi-provider comparison (a separate, now out-of-scope plan feature). It extends the existing
single-provider (`mock` or `gemini`, selected via `AI_PROVIDER` exactly as today) request with a
`lens` parameter that changes *what the provider is asked to focus on*, not which provider
answers.

## Purpose

Let a consultation be interpreted through one of four lenses — General (today's existing framing,
unchanged), Psychological, Practical, Symbolic — requested one at a time (not all four per click,
to avoid multiplying real API cost and rate-limit consumption per interaction), with each
already-fetched lens cached client-side so switching between them doesn't re-request.

## Scope

- `App\AI\InterpretationLens` (new enum): `General = 'general'`, `Psychological =
  'psychological'`, `Practical = 'practical'`, `Symbolic = 'symbolic'`.
- `InterpretationProvider::interpret()` gains a second parameter, `InterpretationLens $lens`.
- `GeminiInterpretationProvider::buildPrompt()` appends one extra framing sentence to the prompt
  based on the lens — `General` adds nothing (byte-for-byte the same prompt as before this spec,
  so existing behavior is provably unchanged for the default case); the other three each add one
  sentence steering the model's focus (psychological/internal dimension; concrete actionable
  guidance; symbolic/archetypal imagery) without changing anything else about the prompt
  (context-grounding, schema, `sourceReferences` computation all stay identical).
- `MockInterpretationProvider` does **not** fabricate lens-differentiated content — it has no
  real understanding to vary by lens, and inventing different-sounding placeholder text per lens
  would misrepresent what the mock provider actually does. Instead it honestly states, in
  `uncertainties`, which lens was requested and that the mock provider doesn't vary its output by
  lens (matches this provider's existing "never invents content" contract).
- `POST /api/interpretations/{id}` accepts an optional `lens` field in its JSON body — absent
  defaults to `general` (byte-identical to pre-SPEC-033 behavior); present must be one of the
  four valid values or the request is rejected `422`.
- The response gains one field, `lens`, echoing which lens actually produced this interpretation.
- `apps/web`: `entities/interpretation` gains an `InterpretationLens` type;
  `requestInterpretation()` accepts an optional lens argument. `ConsultationPage.vue`'s AI
  Interpretation section gains a four-way lens selector; each lens's result is cached
  client-side in session state (a `Record<InterpretationLens, InterpretationState>`) — selecting
  an already-fetched lens shows its cached result instantly, no new request; selecting an
  unfetched lens shows the "Get Interpretation" button again for that lens specifically.

## Out of scope

- **Requesting all four lenses in one click / one request.** Explicitly rejected — it would
  quadruple real API cost and rate-limit consumption for a single user action. One lens per
  request, matching how "Get Interpretation" already works today.
- **Multi-provider comparison** (plan feature 8). Per the user's explicit direction this session,
  this app supports one configured provider at a time (`mock` or `gemini`); lens is a dimension
  of *what* is asked, not *who* answers.
- **Persisting which lens(es) have been fetched for a consultation.** Matches SPEC-008's existing
  "interpretations aren't persisted at all" stance, unchanged — the per-lens cache lives only in
  the page's in-memory state for the current visit, gone on reload, same as today's single
  interpretation already behaves.
- **A fifth, user-defined custom lens.** Four fixed lenses, matching exactly what the plan
  named (with "General" added as the explicit default/baseline, since the plan's three named
  lenses imply a baseline framing already exists to layer alongside).
- **Changing the rate limit's accounting.** Each lens request still counts as exactly one request
  against `AI_RATE_LIMIT_MAX` per IP per window — unchanged from SPEC-012, and correct, since each
  lens request is still one real (or mock) provider call.

## User behavior

```
POST /api/interpretations/{id} {}
  -> same as always: "lens": "general" in the response, same framing as before this spec

POST /api/interpretations/{id} {"lens": "psychological"}
  -> 200, an interpretation genuinely focused on the psychological dimension (when AI_PROVIDER=
     gemini) or the honest mock placeholder naming the requested lens (when AI_PROVIDER=mock)

POST /api/interpretations/{id} {"lens": "not-a-real-lens"}
  -> 422

/consultations/{id}
  -> AI Interpretation section shows four lens buttons: General / Psychological / Practical /
     Symbolic
  -> select "Practical", click "Get Interpretation" -> fetches and shows it, caches it
  -> select "Symbolic" -> shows the "Get Interpretation" button again (not yet fetched)
  -> select "Practical" again -> shows the already-fetched result instantly, no new request
```

## Functional requirements

- **REQ-LENS-001** — `POST /api/interpretations/{id}` MUST accept an optional `lens` field;
  absent MUST behave identically to `"lens": "general"`.
- **REQ-LENS-002** — A present `lens` value that isn't one of `general`/`psychological`/
  `practical`/`symbolic` MUST return `422`.
- **REQ-LENS-003** — With `lens: "general"` (or absent), `GeminiInterpretationProvider`'s prompt
  MUST be byte-identical to the prompt SPEC-011 already produces — no observable behavior change
  for the default case.
- **REQ-LENS-004** — With a non-general lens, `GeminiInterpretationProvider`'s prompt MUST
  include exactly one additional lens-specific framing sentence, appended without altering the
  context-grounding content (question, hexagram text, changing lines, notes) already there.
- **REQ-LENS-005** — `MockInterpretationProvider` MUST NOT vary `summary`/`coreTheme`/
  `situation`/`changingLineMeaning`/`transition`/`practicalReflection` by lens (all remain
  computed the same deterministic way from the context, regardless of lens); it MUST state, in
  `uncertainties`, which lens was requested and that mock output doesn't vary by lens.
- **REQ-LENS-006** — The response MUST include `lens`, echoing the resolved lens (defaulting to
  `general`).
- **REQ-LENS-007** — `ConsultationPage` MUST render a four-way lens selector; selecting an
  already-fetched lens MUST show its cached result without a new request; selecting an unfetched
  lens MUST show the "Get Interpretation" control for that lens.

## Non-functional requirements

- **REQ-LENS-008** — Each interpretation request (any lens) MUST still count as exactly one
  request against the existing per-IP rate limit (SPEC-012), unchanged.
- **REQ-LENS-009** — No component outside `entities/interpretation` may call `apiPost` directly
  for this data.

## Data requirements

None — no persistence change.

## API requirements

`POST /api/interpretations/{id}` request body gains optional `lens`. Response gains `lens`. No
other endpoint, status code, or shape change.

## Edge cases

- `lens` present but not a string (e.g. a number) → `422`, same treatment as an unrecognized
  string value.
- A consultation with no changing lines, requested with a non-general lens → the lens framing
  sentence is still appended; the existing "no changing lines" prompt branch (SPEC-011) is
  otherwise unaffected.
- Switching lenses rapidly before a request resolves → matches this page's existing
  `FormState`-style single-in-flight-request handling elsewhere (SPEC-013 etc.) — a lens change
  while a request for a different lens is in flight doesn't cancel it, but the in-flight
  request's result is stored under *its own* lens key when it resolves, never overwriting the
  currently-selected lens's slot if the user has since switched away.

## Acceptance criteria

- [x] `lens` absent behaves exactly as `general` (byte-identical Gemini prompt, verified).
- [x] An invalid `lens` value returns `422`.
- [x] Each of the three non-general lenses adds exactly one distinct framing sentence to the
      Gemini prompt, verified with a fake `GeminiClient`.
- [x] `MockInterpretationProvider`'s canonical fields don't vary by lens; `uncertainties` names
      the requested lens.
- [x] Response includes `lens`.
- [x] `ConsultationPage`'s lens selector fetches, caches, and correctly redisplays per-lens
      results without redundant requests.
- [x] `npm run verify` passes end to end.
- [x] Manually verified against the real running API/UI — including a real Gemini call for at
      least one non-general lens, confirming the response is genuinely focused differently.
