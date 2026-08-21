# SPEC-034 — Interpretation Follow-Up Questions

**Status:** verified
**Owner:** unassigned
**Last updated:** 2026-08-21

## Problem

Feature 7 of the plan's next batch asks for a conversational mode — the ability to ask a
clarifying question about an interpretation already received, rather than the interaction ending
at one fixed response. [SPEC-033](../multi-lens-interpretation/spec.md) let a user pick *what
angle* to interpret from; this spec lets them dig into *one specific answer* further.

Per the user's direction this session, still Gemini-only (or mock) — no multi-provider
comparison.

## Purpose

After an interpretation is shown, let the user ask a free-text follow-up question and get a
grounded answer — building on the same interpretation's context and, for a second-or-later
follow-up, the prior exchange in that thread — without inventing new classical text or citations
beyond what's actually in scope for this reading.

## Scope

- `InterpretationProvider` gains a second capability:
  `answerFollowUp(InterpretationContext $context, list<ConversationExchange> $history, string
  $question): FollowUpAnswer`.
- `App\AI\ConversationExchange` (new, readonly): `question: string`, `answer: string` — one
  prior round of the thread.
- `App\AI\FollowUpAnswer` (new, readonly): `answer: string`, `sourceReferences: list<string>` —
  `sourceReferences` computed the same deterministic way as `Interpretation`'s
  (`$context->defaultSourceReferences()`), never provider-generated, for the same reason SPEC-011
  already established.
- `POST /api/interpretations/{id}/followup` (new): body `{"question": string, "history"?:
  [{"question": string, "answer": string}]}`; validates `question` non-empty and ≤2000
  characters (matching `Consultation::question`'s own limit); rate-limited through the exact same
  `RateLimiter` instance and key as `POST /api/interpretations/{id}` (a follow-up is a real
  provider call with real cost, same as the initial interpretation).
- `GeminiInterpretationProvider::answerFollowUp()`: builds a prompt grounded in the context's own
  canonical text plus the conversation history plus the new question, explicitly instructed not
  to invent classical text beyond what's given; requests a structured `{"answer": string}` JSON
  response (same schema-based reliability pattern SPEC-011 already established for `interpret()`).
- `MockInterpretationProvider::answerFollowUp()`: an honest, deterministic placeholder naming the
  question asked and stating the mock provider has no real understanding of the conversation —
  matches this provider's existing "never invents content" contract.
- `apps/web`: `entities/interpretation` gains `requestFollowUp()`; `ConsultationPage.vue`'s AI
  Interpretation section, once an interpretation is loaded for the currently-selected lens, shows
  a follow-up question input and a growing thread of Q&A exchanges beneath it — scoped per lens
  (asking a follow-up under "Psychological" doesn't appear under "Practical"), matching
  SPEC-033's per-lens state pattern.

## Out of scope

- **Persisting conversation threads.** Matches SPEC-008/033's existing "not persisted, gone on
  reload" stance for everything AI-generated in this app.
- **A general-purpose chat UI unrelated to a specific interpretation.** Every follow-up is
  anchored to one already-fetched interpretation (a specific lens, a specific consultation) — not
  a freestanding chat surface.
- **Multi-provider comparison of follow-up answers.** Same Gemini/mock-only posture as SPEC-033.
- **Editing or deleting a question already asked in the thread.** Append-only, matching how
  consultation notes (SPEC-005) are also append-only.
- **Limiting how many follow-ups a single thread can have.** The per-request rate limit (SPEC-012)
  already bounds total real-cost volume per IP per hour; no separate per-thread cap is added.

## User behavior

```
POST /api/interpretations/{id}/followup
{"question": "What does the hidden dragon specifically suggest I should avoid doing?"}
  -> 200 {"answer": "...", "sourceReferences": [...]}

POST /api/interpretations/{id}/followup
{"question": "And what about the transition to hexagram 44?",
 "history": [{"question": "What does the hidden dragon...", "answer": "..."}]}
  -> 200, an answer that can reference the prior exchange, still grounded only in the context's
     own canonical text

/consultations/{id}, AI Interpretation section, after fetching an interpretation
  -> "Ask a follow-up question…" input + button
  -> submitting shows the new Q&A pair appended below, scoped to the currently-selected lens
  -> switching lenses shows that lens's own separate thread (or none, if never asked)
```

## Functional requirements

- **REQ-FOLLOWUP-001** — `POST /api/interpretations/{id}/followup` MUST accept `question`
  (required, non-empty, ≤2000 characters) and optional `history` (a list of `{question,
  answer}` pairs, in order).
- **REQ-FOLLOWUP-002** — An empty, missing, or over-length `question` MUST return `422` before
  any repository lookup or provider call.
- **REQ-FOLLOWUP-003** — The response MUST include `answer` (string) and `sourceReferences`
  (always `$context->defaultSourceReferences()`, never provider-generated).
- **REQ-FOLLOWUP-004** — `GeminiInterpretationProvider::answerFollowUp()`'s prompt MUST include
  the context's own canonical text, every prior exchange in `history` (in order), and the new
  question — MUST NOT reference any hexagram data not present in the context.
- **REQ-FOLLOWUP-005** — `MockInterpretationProvider::answerFollowUp()` MUST return a
  deterministic answer that names the question asked and discloses it's a mock placeholder,
  never fabricated analysis.
- **REQ-FOLLOWUP-006** — This endpoint MUST be rate-limited through the same limiter/key as
  `POST /api/interpretations/{id}` (shared budget, not a separate allowance).
- **REQ-FOLLOWUP-007** — `ConsultationPage` MUST render a follow-up question input once an
  interpretation is loaded for the selected lens, and MUST show the growing thread scoped to
  that lens.

## Non-functional requirements

- **REQ-FOLLOWUP-008** — A `GeminiClient`/provider failure during a follow-up MUST map to `502`
  with a descriptive, secret-free message, matching `POST /api/interpretations/{id}`'s existing
  `InterpretationProviderException` handling exactly.
- **REQ-FOLLOWUP-009** — No component outside `entities/interpretation` may call `apiPost`
  directly for this data.

## Data requirements

None — no persistence change.

## API requirements

New endpoint: `POST /api/interpretations/{id}/followup`. No change to any existing endpoint.

## Edge cases

- `history` present but malformed (not an array, or an entry missing `question`/`answer`) →
  `422`, same treatment as an invalid `question`.
- A follow-up requested for a nonexistent consultation id → `404`, matching
  `POST /api/interpretations/{id}`'s existing behavior, checked after rate-limit and
  question-validation (mirrors that endpoint's exact ordering).
- A very long `history` (many rounds) → no cap added; the real bound is the model's own context
  window, which would surface as a `502` from the provider if ever exceeded, not something this
  spec adds bespoke handling for.

## Acceptance criteria

- [x] A valid follow-up request returns `200` with `answer` and `sourceReferences`.
- [x] An empty/missing/over-length `question` returns `422` before touching the repository.
- [x] `sourceReferences` is always `$context->defaultSourceReferences()`, verified even when a
      fake client's response includes a different value.
- [x] `GeminiInterpretationProvider`'s follow-up prompt includes the context's canonical text,
      every prior exchange, and the new question, verified with a fake client.
- [x] `MockInterpretationProvider`'s follow-up answer names the question and discloses it's a
      mock placeholder.
- [x] The follow-up endpoint shares the same rate limit as the main interpretation endpoint.
- [x] A provider failure during follow-up maps to `502`.
- [x] `ConsultationPage` renders a working follow-up input and a per-lens-scoped thread.
- [x] `npm run verify` passes end to end.
- [x] Manually verified against the real running API/UI, including a real Gemini follow-up call
      referencing a prior exchange.
