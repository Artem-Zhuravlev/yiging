# Tasks — AI Interpretation (SPEC-008)

Each task references the requirement(s) it fulfills. Mark done only once the linked test passes.

| Task ID    | Description                                                        | Requirement(s)         | Test(s)                          | Status  |
| ---------- | ----------------------------------------------------------------------- | --------------------------- | -------------------------------------- | ------- |
| TASK-AI-001 | Implement `InterpretationContext` + `InterpretationContextBuilder`      | REQ-AI-001                  | `InterpretationContextBuilderTest`     | done |
| TASK-AI-002 | Implement `Interpretation` + `InterpretationProvider` interface         | REQ-AI-002                  | (implemented by below)                 | done |
| TASK-AI-003 | Implement `MockInterpretationProvider`                                   | REQ-AI-003, REQ-AI-004      | `MockInterpretationProviderTest`       | done |
| TASK-AI-004 | Implement `InterpretationController` + route, incl. 404                  | REQ-AI-005                  | `InterpretationControllerTest`         | done |
| TASK-AI-005 | Verify provider-swap boundary (no direct provider construction outside DI) | REQ-AI-006, REQ-AI-007   | code review / PHPStan                  | done |
