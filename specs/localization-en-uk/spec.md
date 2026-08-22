# SPEC-038 — Localization (English/Ukrainian) with Guaranteed AI Response Language

**Status:** verified
**Owner:** unassigned
**Last updated:** 2026-08-22

## Problem

The app's UI is English-only, and the AI interpretation feature always writes in whatever
language Gemini defaults to (English). The user asked for translations, and specifically stressed
that whatever mechanism handles the AI side must be *reliable* — an LLM asked nicely to "please
respond in Ukrainian" can still slip back into English, especially for a lens/profile-heavy prompt
it's already juggling several instructions in. A translated UI sitting next to an AI response that
silently reverts to English would be a broken, confusing experience.

## Purpose

Let the user switch the whole app — static UI text and every AI-generated interpretation/follow-up
answer — between English and Ukrainian, with the AI side backed by an explicit verify-and-retry
mechanism so a wrong-language response is either corrected or surfaced as a clear error, never
silently served.

## Scope

### Frontend localization
- Add `vue-i18n`. `src/i18n/index.ts` creates the instance (Composition API mode,
  `globalInjection: true` so `$t()` works in every template without per-component boilerplate),
  with `en`/`uk` message catalogs under `src/i18n/locales/`.
- The active locale persists in `localStorage` (`yijing-locale`), defaulting to the browser's
  language if it's `uk`, else `en`.
- A locale switcher (EN/UA) lives in `App.vue`'s toolbar, visible on every route including the
  public share page.
- Every static UI string across all pages/shared components (labels, headings, button text,
  placeholders, empty-state copy) is extracted into the message catalogs and rendered via `$t()`.

### AI response language guarantee
- `App\AI\ResponseLanguage` enum: `English = 'en'`, `Ukrainian = 'uk'`.
- `InterpretationProvider::interpret()`/`answerFollowUp()` gain a `ResponseLanguage $language`
  parameter. The prompt gets an explicit, unambiguous instruction naming the target language.
- **Verification, not trust**: after Gemini responds, `GeminiInterpretationProvider` checks
  whether the combined AI-generated text (never `sourceReferences`, which is deterministic,
  never model-written) actually matches the requested language, via a Cyrillic-character-ratio
  check (`ResponseLanguage::matches()`) — Ukrainian text is overwhelmingly Cyrillic, English is
  essentially none, so this is a simple, dependency-free, deterministic classifier that's actually
  *more* reliable than a general-purpose language-detection library for exactly this language
  pair (it would not generalize to e.g. English/French, which share a script — not needed here).
- On a mismatch, the request is retried (same context, a strengthened corrective prompt line
  appended) up to `MAX_LANGUAGE_ATTEMPTS = 3` total attempts. If every attempt fails, the provider
  throws `InterpretationProviderException` — the existing `502 Bad Gateway` path — rather than
  ever serving a response in the wrong language.
- `MockInterpretationProvider` doesn't attempt real translation (it's a deterministic
  placeholder); it discloses the requested language in `uncertainties`/the follow-up answer, same
  as it already does for non-default lens/profile.
- The frontend sends the current UI locale as `language` on every interpret/follow-up request —
  one shared setting drives both the UI chrome and the AI's output, matching the user's "the same
  for AI" framing. No separate AI-language setting to keep in sync.
- Every consultation response's interpretation gains `language` in its JSON (`toJson()`), mirroring
  `lens`.

## Out of scope

- **Translating server-generated error/validation messages.** Only the static UI chrome
  (labels, headings, buttons, placeholders, empty states) is localized; API error strings stay
  English — translating validation copy is a much larger, separately-scoped effort.
- **Persisting a per-consultation AI-response-language cache keyed by language.** The frontend's
  existing per-lens interpretation cache isn't also keyed by language; switching UI language
  mid-session and re-requesting the same lens will simply refetch (the button already reads
  "Regenerate" once loaded) rather than serving a stale-language cached copy silently.
- **Any language beyond English/Ukrainian.** The Cyrillic-ratio classifier is a deliberate
  simplification specific to this pair; a third language would need a real classifier.
- **Translating AI-generated `sourceReferences`.** These are never model-written (always
  `$context->defaultSourceReferences()`, citing "Legge, 1899" etc.) — deliberately left as-is,
  consistent with SPEC-008's "never invented" citation guarantee.

## Functional requirements

- **REQ-L10N-001** — A locale switcher in the toolbar changes every static UI string on every
  route, immediately, without a reload.
- **REQ-L10N-002** — The chosen locale persists across reloads (`localStorage`).
- **REQ-L10N-003** — `POST /api/interpretations/{id}` and `.../followup` accept a `language`
  field (`en`/`uk`); absent defaults to `en`; an invalid value returns `422`.
- **REQ-L10N-004** — The Gemini provider verifies its own response's language and retries (up to
  3 attempts total) on a mismatch before ever returning a result.
- **REQ-L10N-005** — If all attempts fail the language check, the request fails with `502`
  (existing `InterpretationProviderException` path) — never a silent wrong-language response.
- **REQ-L10N-006** — The frontend sends the active UI locale as `language` on every
  interpret/follow-up request, with no separate setting.

## Edge cases

- A hexagram's Chinese name/pinyin (e.g. "乾, Qián") appears inside otherwise-Ukrainian prose —
  expected and harmless; the Cyrillic-ratio check only requires the response be *overwhelmingly*
  Cyrillic, not exclusively so.
- All 3 attempts fail the language check (rare, but possible) — the user sees the same inline
  error UI as any other provider failure (rate limit aside), not a silent English fallback.
- Mock provider (dev/test, no API key) — no retry loop applies; it's deterministic and never
  "wrong," it just discloses the requested language wasn't applied, like it already does for lens.

## Acceptance criteria

- [x] Every route's static UI text switches fully between English and Ukrainian via the toolbar
      switcher, persists across reload.
- [x] `PATCH`/`POST` interpretation endpoints accept/validate `language`; invalid value → `422`.
- [x] A real Gemini call requesting Ukrainian is manually verified to return genuinely Ukrainian
      prose (not just labels) for every free-text field.
- [x] Unit tests prove the retry-on-mismatch path: a fake client returning English first then
      Ukrainian second results in the Ukrainian response being used, with 2 calls recorded.
- [x] Unit tests prove the all-attempts-fail path throws `InterpretationProviderException` after
      exactly `MAX_LANGUAGE_ATTEMPTS` calls.
- [x] `npm run verify` passes end to end.
