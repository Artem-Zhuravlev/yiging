# Tasks — Interpretation Profile (SPEC-035)

Each task references the requirement(s) it fulfills. Mark done only once the linked test passes.

| Task ID           | Description                                                              | Requirement(s)             | Test(s)                             | Status |
| -------------------- | -------------------------------------------------------------------------- | -------------------------------- | ---------------------------------------- | ------ |
| TASK-PROFILE-001 | Add migration, `Tone`/`ResponseLength`/`InterpretationProfile`, repository  | REQ-PROFILE-001, 002             | `SqliteInterpretationProfileRepositoryTest` | done |
| TASK-PROFILE-002 | Add `InterpretationProfileController` + routes                              | REQ-PROFILE-001, 002             | `InterpretationProfileControllerTest`   | done   |
| TASK-PROFILE-003 | Extend `InterpretationProvider`/`GeminiInterpretationProvider` with profile  | REQ-PROFILE-003, 004             | `GeminiInterpretationProviderTest`      | done   |
| TASK-PROFILE-004 | Extend `MockInterpretationProvider` with profile disclosure                 | REQ-PROFILE-005                  | `MockInterpretationProviderTest`        | done   |
| TASK-PROFILE-005 | Wire profile loading into `InterpretationController`                        | REQ-PROFILE-006                  | `InterpretationControllerTest`          | done   |
| TASK-PROFILE-006 | Add `entities/interpretation-profile` (model + api)                         | REQ-PROFILE-008                  | (typechecked via page test)             | done   |
| TASK-PROFILE-007 | Add `InterpretationSettingsPage.vue` + route + nav link                     | REQ-PROFILE-007                  | `InterpretationSettingsPage.spec.ts`, `App.spec.ts` | done |
| TASK-PROFILE-008 | Run `npm run verify`, manually verify (incl. real Gemini call showing a genuinely different tone), update README + specs/README | acceptance criteria | `npm run verify`, manual | done |
