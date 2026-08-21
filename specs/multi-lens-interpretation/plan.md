# Plan — Multi-Lens Interpretation (SPEC-033)

**Depends on spec status:** `approved`

## Technical approach

- `apps/api/src/AI/InterpretationLens.php` (new enum, string-backed):
  `General = 'general'`, `Psychological = 'psychological'`, `Practical = 'practical'`,
  `Symbolic = 'symbolic'`.
- `InterpretationProvider::interpret(InterpretationContext $context, InterpretationLens $lens):
  Interpretation` — interface signature change, both implementations updated.
- `GeminiInterpretationProvider::buildPrompt()` gains a `$lens` parameter; a `match` maps each
  non-general lens to one framing sentence appended to the existing prompt lines array, `General`
  maps to nothing appended — the exact same array of lines as before this spec for that case,
  keeping REQ-LENS-003 true by construction (no separate "is this general" branch duplicating the
  base prompt — one array, one optional extra line).
- `MockInterpretationProvider::interpret()` gains `$lens`; every existing field's computation is
  untouched; `uncertainties` gains one more entry naming the lens (e.g. `"Requested lens:
  psychological — the mock provider does not vary its interpretation by lens."` for non-general,
  omitted entirely for `general` since there's nothing non-default to disclose).
- `InterpretationController::create()`: parses `lens` from the decoded body the same way
  `ConsultationController` already parses enum-like fields (`CastingMethodName::tryFrom()`
  precedent) — `InterpretationLens::tryFrom($body['lens'] ?? 'general')`; `null` (unrecognized
  value or non-string) → `422` before any repository/context/provider work, matching the existing
  rate-limit-first, validate-early ordering. `toJson()` gains `'lens' => $lens->value`.
- `apps/web/src/entities/interpretation/model.ts`: `InterpretationLens` union type
  (`'general' | 'psychological' | 'practical' | 'symbolic'`); `Interpretation` gains `lens:
  InterpretationLens`.
- `apps/web/src/entities/interpretation/api.ts`: `requestInterpretation(consultationId, lens?:
  InterpretationLens)` — posts `{ lens }` when provided, `{}` when not (preserving the exact
  existing default-omitted-body call shape for `general`).
- `ConsultationPage.vue`: `interpretationStates: Record<InterpretationLens,
  InterpretationState>` (all four keys initialized to `{ status: 'idle' }`), `selectedLens =
  ref<InterpretationLens>('general')`; the existing "Get Interpretation" button and result
  rendering read/write `interpretationStates[selectedLens.value]` instead of a single
  `interpretationState`; four lens-selector buttons set `selectedLens`.

## Architecture decisions

- **Lens changes the prompt, not the provider selection.** Matches the spec's own framing:
  `AI_PROVIDER` (mock/gemini) is an orthogonal axis to lens; every lens goes through whichever
  provider is configured, exactly as today.
- **The mock provider discloses rather than fakes.** Inventing lens-flavored placeholder prose
  would be indistinguishable, to a casual reader, from an actual attempt at a psychological
  reading — this codebase's consistent stance (SPEC-002's classical-text-only rule, SPEC-014's
  "no fabricated relationship data") is that nothing simulates real analysis it isn't actually
  doing. Naming the lens honestly in `uncertainties` is the mock-appropriate move.
- **Per-lens client-side cache, not a re-fetch-on-tab-switch UX.** A "tabs" UI pattern usually
  implies re-fetching per tab; here that would silently multiply real API cost and burn rate-limit
  budget every time a user compares lenses. Caching per lens in local component state (gone on
  reload, matching SPEC-008's "not persisted" stance) is the one design that keeps "compare
  lenses side by side" cheap after the first fetch of each.

## Affected areas

- `apps/api/src/AI/InterpretationLens.php` (new)
- `apps/api/src/AI/InterpretationProvider.php`
- `apps/api/src/AI/GeminiInterpretationProvider.php`
- `apps/api/src/AI/MockInterpretationProvider.php`
- `apps/api/src/AI/InterpretationController.php`
- `apps/api/tests/AI/GeminiInterpretationProviderTest.php`
- `apps/api/tests/AI/MockInterpretationProviderTest.php`
- `apps/api/tests/AI/InterpretationControllerTest.php`
- `apps/web/src/entities/interpretation/model.ts`
- `apps/web/src/entities/interpretation/api.ts`
- `apps/web/src/entities/interpretation/api.spec.ts`
- `apps/web/src/pages/consultations/ConsultationPage.vue`
- `apps/web/src/pages/consultations/ConsultationPage.spec.ts`

## Data / schema changes

None.

## Risks / open questions

- None currently open.
