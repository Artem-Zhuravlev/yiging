# Tasks — Localization EN/UK (SPEC-038)

| Task ID       | Description                                                              | Status |
| ------------- | ------------------------------------------------------------------------- | ------ |
| TASK-L10N-001 | `ResponseLanguage` enum + Cyrillic-ratio `matches()`                      | done   |
| TASK-L10N-002 | `InterpretationProvider` interface + `Gemini`/`Mock` providers            | done   |
| TASK-L10N-003 | Retry loop in `GeminiInterpretationProvider`                              | done   |
| TASK-L10N-004 | `InterpretationController` language parsing/validation/response           | done   |
| TASK-L10N-005 | `FakeGeminiClient` response-queue + call counter                          | done   |
| TASK-L10N-006 | Backend tests (enum, provider retry/throw, controller 422/default)        | done   |
| TASK-L10N-007 | `npm install vue-i18n`, `src/i18n/*`, `main.ts` registration              | done   |
| TASK-L10N-008 | `App.vue` locale switcher                                                 | done   |
| TASK-L10N-009 | Extract strings: Home/HexagramList/Statistics/Settings/Journal/Editor     | done   |
| TASK-L10N-010 | Extract strings: Shared/Compare/Detail/NewConsultation/History/Lines      | done   |
| TASK-L10N-011 | Extract strings: `ConsultationPage.vue` (largest)                         | done   |
| TASK-L10N-012 | `entities/interpretation/api.ts` + model + `ConsultationPage` language wiring | done |
| TASK-L10N-013 | i18n plugin in `test-setup.ts`, fix any broken `.text()` assertions       | done   |
| TASK-L10N-014 | `npm run verify` + manual browser pass incl. real Ukrainian Gemini call   | done   |
