# Plan — AI Interpretation (SPEC-008)

**Depends on spec status:** `approved`

## Technical approach

```
apps/api/src/AI/
├── InterpretationContext.php        readonly value object
├── InterpretationContextBuilder.php  Consultation -> InterpretationContext
├── Interpretation.php                readonly value object (8 fields)
├── InterpretationProvider.php        interface: interpret(InterpretationContext): Interpretation
├── MockInterpretationProvider.php    the only implementation this spec adds
└── InterpretationController.php      POST /api/interpretations/{id}
```

- `InterpretationContext`: `question: string`, `primaryHexagram: Hexagram`,
  `changingLinePositions: list<int>`, `changingLineStatements: array<int, string>`
  (position => statement, only the changing ones), `resultingHexagram: Hexagram`,
  `userNotes: list<string>`.
- `InterpretationContextBuilder::build(Consultation $consultation): InterpretationContext`:
  reads `$consultation->primaryHexagram`/`resultingHexagram` directly (already full `Hexagram`
  objects with real judgment/image/lineStatements, per SPEC-002/005 — no extra fetch needed),
  filters `primaryHexagram->lineStatements` down to `changingLinePositions()`, and maps
  `$consultation->notes` to their `text`.
- `Interpretation`: the 8 plan-specified fields; `changingLineMeaning`/`transition` typed
  `?string`.
- `MockInterpretationProvider::interpret()`: `summary` from the question + primary hexagram
  identity; `coreTheme`/`situation` from the primary hexagram's judgment/image verbatim (the
  mock provider's job is to prove the pipeline works end to end, not to write new prose);
  `changingLineMeaning` from joining the changing lines' statements (`null` if none);
  `transition` naming the resulting hexagram (`null` if none changing);
  `practicalReflection`/`uncertainties` are fixed strings that plainly say this is a mock, not
  real AI output; `sourceReferences` built from exactly which hexagram/lines were read.
- `InterpretationController`: same shape as `ConsultationController`/`HexagramController` —
  constructs its own `SqliteConsultationRepository` from `Config` in its constructor;
  `create(Request, array $vars)` loads the consultation (404 if missing), builds the context,
  calls the (hardcoded, for now) `MockInterpretationProvider`, maps `Interpretation` to JSON.

## Architecture decisions

- **No config-driven provider selection.** With exactly one `InterpretationProvider`
  implementation, wiring a provider-selection mechanism (`AI_PROVIDER` env var, a factory) has
  no second case to select between — it would be speculative structure with nothing to prove it
  correct. `InterpretationController` constructs `MockInterpretationProvider` directly; adding
  a real provider later means changing that one line plus adding the new class — the interface
  boundary (REQ-AI-006) is what actually makes swapping safe, not a premature factory.
- **No interpretation persistence.** Computing is cheap and deterministic (mock provider); a
  real provider will have its own cost/latency reasons to want caching, which is exactly why
  that decision belongs with that spec, not guessed at here.
- **`InterpretationContextBuilder` takes a `Consultation`, not a consultation id.** Keeps it a
  pure function (no repository dependency of its own) — `InterpretationController` is
  responsible for loading the `Consultation` and handling the not-found case; the builder only
  ever sees a `Consultation` that already exists.
- **Only changing lines' statements enter the context**, never all 6. Matches the plan's own
  "Context Builder" example (section 19: "Question + Hexagram 23 + Line 4 + Resulting Hexagram
  35 + relevant canonical texts") and keeps a future real provider's prompt bounded and
  relevant rather than dumping the whole hexagram.

## Affected areas

- `apps/api/src/AI/InterpretationContext.php`
- `apps/api/src/AI/InterpretationContextBuilder.php`
- `apps/api/src/AI/Interpretation.php`
- `apps/api/src/AI/InterpretationProvider.php`
- `apps/api/src/AI/MockInterpretationProvider.php`
- `apps/api/src/AI/InterpretationController.php`
- `apps/api/config/routes.php` (1 new route)
- `apps/api/tests/AI/**`

## Data / schema changes

None.

## Risks / open questions

- None currently open. A real provider, persistence, rate limiting, and frontend UI are all
  named explicitly as deferred (see spec.md "Out of scope"), each with its own trigger
  condition, not silently dropped.
