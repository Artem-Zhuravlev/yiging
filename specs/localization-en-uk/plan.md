# Plan — Localization EN/UK (SPEC-038)

## Backend (AI language guarantee) — do first, self-contained, testable
1. `App\AI\ResponseLanguage` enum (`English`/`Ukrainian`) with `promptInstruction(): string` and
   `matches(string $text): bool` (Cyrillic-ratio classifier).
2. `InterpretationProvider` interface gains `ResponseLanguage $language` on both methods.
3. `GeminiInterpretationProvider`: prompt gains the language instruction line; `interpret()`/
   `answerFollowUp()` wrapped in a retry loop (`MAX_LANGUAGE_ATTEMPTS = 3`) that checks
   `$language->matches()` against the concatenated AI-written fields (excludes
   `sourceReferences`), retrying with a strengthened corrective prompt line, throwing
   `InterpretationProviderException` after exhausting attempts.
4. `MockInterpretationProvider`: accepts `$language`, discloses non-English requests in
   `uncertainties`/answer text, same pattern as lens/profile — no retry logic needed.
5. `InterpretationController`: parse/validate `language` from the request body (default `en`,
   invalid → 422) for both `create()` and `followUp()`; `toJson()` includes `language`.
6. Update `FakeGeminiClient` (test double) to support a *queue* of responses (not just one) plus a
   call counter, so retry behavior is testable.
7. Tests: `ResponseLanguageTest` (matches() truth table), `GeminiInterpretationProviderTest`
   (retry-until-match, exhausts-then-throws), `MockInterpretationProviderTest`,
   `InterpretationControllerTest` (422 on bad language, default, response includes it).
8. `composer test`/`phpstan`/`php-cs-fixer` clean.

## Frontend (i18n)
9. `npm install vue-i18n`. `src/i18n/index.ts` + `src/i18n/locales/{en,uk}.ts`. Register in
   `main.ts`. LocalStorage persistence, browser-language default.
10. Locale switcher in `App.vue`'s `Toolbar`.
11. Extract every static string per page into the message catalogs, in the same order already
    established for the PrimeVue pass (simple pages first): `HomePage` → `HexagramListPage` →
    `StatisticsPage` → `InterpretationSettingsPage` → `JournalPage` → `HexagramEditorPage` →
    `SharedConsultationPage` → `HexagramComparePage` → `HexagramDetailPage` →
    `NewConsultationPage` → `ConsultationHistoryPage` → `HexagramLines.vue` → `ConsultationPage`
    (largest, last) — plus `App.vue`'s nav labels.
12. `entities/interpretation/api.ts` — `requestInterpretation()`/`requestFollowUp()` gain a
    `language` param; `ConsultationPage.vue` passes the active `useI18n().locale` value on every
    call. `Interpretation` model gains `language`.
13. Existing `*.spec.ts` files: since `globalInjection: true` needs the i18n plugin installed
    globally in tests too (same pattern as the PrimeVue `test-setup.ts` push), add it there;
    fix any test assertions that hard-coded English copy where a key now resolves through i18n
    (most structural selectors — ids, `data-*`, button-found-by-role — are unaffected, only
    `.text()`-content assertions that hardcode label copy need review).
14. `npm run verify` + manual browser pass: toggle EN→UK, confirm every page's static text
    switches, and request a real Gemini interpretation in Ukrainian, confirming genuinely
    Ukrainian prose in every free-text field (not just labels).
