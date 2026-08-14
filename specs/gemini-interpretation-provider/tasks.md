# Tasks — Gemini Interpretation Provider (SPEC-011)

Each task references the requirement(s) it fulfills. Mark done only once the linked test passes.

| Task ID       | Description                                                          | Requirement(s)              | Test(s)                              | Status  |
| ------------- | ------------------------------------------------------------------------ | -------------------------------- | ------------------------------------------ | ------- |
| TASK-GEM-001 | Move `sourceReferences` logic onto `InterpretationContext::defaultSourceReferences()`; update Mock | REQ-GEM-005 | `MockInterpretationProviderTest` (unchanged assertions still pass) | done |
| TASK-GEM-002 | Implement `InterpretationProviderException`, `GeminiClient` interface    | (foundation)                     | —                                            | done |
| TASK-GEM-003 | Implement `HttpGeminiClient`                                             | REQ-GEM-008, REQ-GEM-010         | manual (needs a real key; see spec.md)      | done (code); live call unverified |
| TASK-GEM-004 | Implement `GeminiInterpretationProvider`                                 | REQ-GEM-001..005, REQ-GEM-009    | `GeminiInterpretationProviderTest`          | done |
| TASK-GEM-005 | Add `ai_provider`/`ai_api_key`/`ai_model` to `Config`                    | (foundation for REQ-GEM-006)     | —                                            | done |
| TASK-GEM-006 | Wire provider selection + 502 handling into `InterpretationController`  | REQ-GEM-006, REQ-GEM-007         | `InterpretationControllerTest`              | done |
| TASK-GEM-007 | Add Kernel-level catch-all for uncaught exceptions                       | REQ-GEM-011                      | `KernelTest`                                | done |
| TASK-GEM-008 | Document `AI_PROVIDER`/`AI_API_KEY`/`AI_MODEL` in `.env.example`         | (documentation)                  | manual review                               | done |
| TASK-GEM-009 | Verify + commit; flag live-key verification as the user's to perform     | acceptance criteria              | `npm run verify`                            | done |
