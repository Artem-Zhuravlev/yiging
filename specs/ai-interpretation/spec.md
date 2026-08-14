# SPEC-008 — AI Interpretation

**Status:** verified
**Owner:** unassigned
**Last updated:** 2026-08-14

## Problem

A `Consultation` (SPEC-005) is a structured record — a question, a cast hexagram, changing
lines, a resulting hexagram, real classical text (SPEC-002) — but nothing turns that into a
readable interpretation for the person who asked the question. That's the last piece of the
plan's core loop: `Consultation → Context Builder → LLM → Interpretation`.

## Purpose

Define the boundary between the application and AI: a structured `InterpretationContext` built
from a `Consultation`, a swappable `InterpretationProvider` that turns it into a structured
`Interpretation`, and a `MockInterpretationProvider` so the whole pipeline is real and testable
before any AI provider or API key exists — per the plan's explicit sequencing ("Реализовать
сначала: MockInterpretationProvider... Чтобы приложение можно было тестировать без API ключа").

## Scope

- `InterpretationContext`: question, primary hexagram (full `Hexagram`, including its real
  judgment/image text per SPEC-002), changing line positions + their specific line statements
  (not all 6 — only the relevant ones, per the plan's "Context Builder" principle of not
  sending the whole dataset), resulting hexagram, and existing consultation notes.
- `InterpretationContextBuilder`: builds an `InterpretationContext` from a `Consultation`. Pure
  assembly — no I/O of its own (`Consultation`'s `primaryHexagram`/`resultingHexagram` are
  already full `Yijing\Core\Hexagram` objects once loaded via `SqliteConsultationRepository`,
  so no extra fetches are needed here, unlike the frontend's `ConsultationPage`, which only had
  a hexagram *summary* to work from over HTTP).
- `Interpretation`: structured output — summary, coreTheme, situation, changingLineMeaning
  (nullable — absent when there are no changing lines), transition (nullable, same reason),
  practicalReflection, uncertainties, sourceReferences. Matches the plan's specified shape
  (section 20) exactly.
- `InterpretationProvider` interface: `interpret(InterpretationContext): Interpretation`.
- `MockInterpretationProvider`: the only implementation in this spec. Deterministic — built
  directly from the context's own canonical text, no external call, no randomness — and
  explicitly documented as not a real interpretation (mirrors SPEC-004's `RandomMethod`
  docblock discipline: dev/test tooling, never to be mistaken for the real thing).
- `POST /api/interpretations/{consultationId}`: builds the context for that consultation and
  returns the provider's `Interpretation` as JSON. `404` for an unknown consultation id.

## Out of scope

- **A real AI provider** (`OpenAIInterpretationProvider` or similar). Needs an API key, secret
  management (`.env` `AI_PROVIDER`/`AI_API_KEY`/`AI_MODEL`, per the plan's own config list),
  and rate limiting (plan section 31) — none of which exist yet and none of which this session
  can provision on its own. The interface this spec defines is exactly what makes adding one
  later a new, isolated implementation, not a redesign.
- **Persisting the interpretation** on the `Consultation`. `POST /api/interpretations/{id}`
  computes and returns one; it doesn't write anything back. `Consultation`'s schema (SPEC-005)
  isn't touched. Revisit if/when a UI need for "see the interpretation again later without
  recomputing" becomes concrete — same deferral pattern SPEC-006 used for notes/tags editing.
- **Frontend UI** for triggering interpretation — a follow-up spec, once this endpoint exists
  to build against (mirrors how SPEC-006's API preceded SPEC-009's UI).
- **Rate limiting** on this endpoint — explicitly a real provider's concern (a mock provider
  costs nothing to call repeatedly); adding it now would be speculative.
- **Provider selection via config** (`AI_PROVIDER` env var choosing between multiple
  providers). Meaningless with exactly one implementation; add it when a second one exists.

## User behavior

```
POST /api/interpretations/{consultationId}
  -> 200, body: {summary, coreTheme, situation, changingLineMeaning, transition,
     practicalReflection, uncertainties, sourceReferences}
  -> built from the consultation's own primary/resulting hexagram and changing-line text,
     never fabricated or pulled from the provider's own "knowledge" of the I Ching

POST /api/interpretations/does-not-exist
  -> 404, {"error": "Not Found"}
```

## Functional requirements

- **REQ-AI-001** — `InterpretationContextBuilder::build(Consultation)` MUST produce an
  `InterpretationContext` containing: the question, the full primary `Hexagram`, the changing
  line positions, a position-keyed map of *only* the changing lines' statements (not all 6),
  the full resulting `Hexagram`, and the consultation's existing notes' text.
- **REQ-AI-002** — `Interpretation` MUST expose exactly the 8 fields the plan specifies:
  summary, coreTheme, situation, changingLineMeaning, transition, practicalReflection,
  uncertainties, sourceReferences. `changingLineMeaning` and `transition` MUST be `null` when
  the consultation has no changing lines (primary equals resulting) — there is nothing
  meaningful to report there, and reporting something invented would violate REQ-AI-004.
- **REQ-AI-003** — `MockInterpretationProvider::interpret()` MUST derive every field from data
  already present in the given `InterpretationContext` (the hexagrams' real judgment/image/line
  text, the question) — no hardcoded "sample" interpretation text unrelated to the actual
  context, so tests exercise the real assembly logic, not a canned string.
- **REQ-AI-004** — `sourceReferences` MUST cite exactly the canonical text actually used
  (which hexagram's judgment, image, and which specific changing lines) — never a source that
  wasn't part of the context, and never omitted when that text was used.
- **REQ-AI-005** — `POST /api/interpretations/{id}` MUST respond `404` with
  `{"error": "Not Found"}` for an id `ConsultationRepository::findById()` can't find, and `200`
  with the `Interpretation` JSON otherwise — never a `500`.

## Non-functional requirements

- **REQ-AI-006** — `InterpretationProvider` MUST be the only way `InterpretationController`
  (or anything else) obtains an interpretation — no direct construction of a specific
  provider's logic outside of dependency wiring, so a future real provider is a drop-in
  replacement (plan section 18: never `Vue → LLM` or scattered direct provider calls).
  `AI must never be a source of truth for domain mechanics or classical text` (`docs/coding-
  rules.md`) — enforced structurally here since `MockInterpretationProvider` (and any future
  provider) only ever *receives* hexagram/text data through `InterpretationContext`; it has no
  path to invent or override it.
- **REQ-AI-007** — This module lives in `apps/api/src/AI` (the SPEC-001 bootstrap placeholder,
  finally used) and depends on `App\Readings` (to load a `Consultation`) and
  `packages/yijing-core` (for `Hexagram`) — never the reverse.

## Data requirements

None — no new persistence (see "Out of scope").

## API requirements

See "User behavior" / "Functional requirements" above.

## Edge cases

- Zero changing lines → `changingLineMeaning`/`transition` are `null`; `summary`/`coreTheme`/
  `situation`/`practicalReflection` are still populated from the primary hexagram alone.
- All 6 lines changing → `changingLineStatements` in the context has all 6 positions; nothing
  in this spec special-cases that count, it's just "however many changing positions the
  consultation has."
- A consultation with existing notes → they're included in the context's `userNotes` (plan
  section 16 lists "user notes" as part of the context) but the mock provider doesn't need to
  use them meaningfully — a real provider might; the context makes them available either way.

## Acceptance criteria

- [x] `InterpretationContextBuilder` produces a context with the question, full primary/
      resulting hexagrams, only-the-changing-lines' statements, and existing notes.
- [x] `MockInterpretationProvider` returns all 8 `Interpretation` fields, with
      `changingLineMeaning`/`transition` `null` exactly when there are no changing lines.
- [x] `sourceReferences` names exactly the hexagram judgment/image/changing-line text actually
      used — verified by a test asserting the reference list matches the context's own content.
- [x] `POST /api/interpretations/{id}` returns `200` with the interpretation for a real
      consultation and `404` for an unknown id.
- [x] `InterpretationController` has no direct PDO/SQL usage (only via `ConsultationRepository`,
      matching `ConsultationController`'s existing pattern).
- [x] Feature tests run against the real `Kernel`/routing stack, matching the established
      pattern from `ConsultationControllerTest`/`HexagramControllerTest`.
- [x] `npm run verify` passes end to end.

`apps/api/src/AI` implements `InterpretationContext`, `InterpretationContextBuilder`,
`Interpretation`, `InterpretationProvider`, `MockInterpretationProvider`, and
`InterpretationController`, wired to `POST /api/interpretations/{id}`. 8 new tests (60 total in
`apps/api`, 413 assertions). Manually verified against the real running API: created a
consultation (Hexagram 1 with line 1 changing), requested its interpretation, and confirmed
every field — including `sourceReferences` — traced back to real Legge text (SPEC-002) with no
invented content; confirmed `404` for an unknown consultation id.

**Found and fixed along the way:** the feature test held its own `SqliteConsultationRepository`
(and its `PDO` connection) as an instance property across the test, which on Windows kept the
temp SQLite file locked past `tearDown()`'s `unlink()` call. Fixed by explicitly `unset()`-ing
it before deleting the file — the same pattern other feature tests in this repo avoid simply by
never holding a `PDO` handle as a property in the first place.
