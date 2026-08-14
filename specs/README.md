# Spec-Driven Development

This project follows Spec-Driven Development (SDD). Product code is never written from a
free-form request alone — every non-trivial feature is defined here first.

```
Requirement → Spec → Plan → Tasks → Implementation → Tests → Verification → Commit → Push
```

## Hard rule

```
NO SPEC = NO IMPLEMENTATION = NO COMMIT = NO PUSH
```

If a request is ambiguous, or asks to "just quickly add" something, stop and write or update the
spec first. If implementation reveals the spec is wrong or incomplete:

```
STOP → UPDATE SPEC → RE-APPROVE → CONTINUE
```

Never silently change requirements through code.

## Layout

Every feature lives in its own directory:

```
specs/<feature-name>/
├── spec.md      — problem, scope, requirements, acceptance criteria
├── plan.md      — technical approach, architecture decisions, affected files
└── tasks.md     — ordered implementation tasks, each traceable to a requirement
```

Start new specs from [`_template/`](_template/spec.md).

## Status lifecycle

Every `spec.md` declares a `Status:` field with one of:

| Status        | Meaning                                                             |
| ------------- | -------------------------------------------------------------------- |
| `draft`       | Being written or discussed. Not implementable.                     |
| `approved`    | Reviewed and signed off. Implementation may begin.                 |
| `in-progress` | Implementation underway against an approved spec.                  |
| `implemented` | Code complete, matches the spec, tests exist and pass.             |
| `verified`    | Implementation re-checked against the spec; considered done.       |
| `deprecated`  | No longer applicable; kept for history.                            |

Only `approved` (or later) specs may be implemented. Only `verified` specs are considered complete.

## Traceability

Every requirement in `spec.md` has an ID (`REQ-<AREA>-<NNN>`). Every task in `tasks.md`
references the requirement(s) it fulfills. Every test references the requirement it verifies.

```
REQ-HX-001 → TASK-HX-001 → TEST-HX-001
```

This is what lets us answer, at any time, whether a requirement is actually implemented and
tested — not just mentioned in a commit message.

## Commits

Commits reference the spec they implement:

```
feat(hexagrams): add hexagram domain model [SPEC-002]
```

No `update`, `changes`, `fix stuff`, or other content-free messages.

## Current specs

| ID       | Feature                                     | Status     |
| -------- | -------------------------------------------- | ---------- |
| SPEC-001 | [Project Architecture](project-architecture/spec.md) | `verified` |
| SPEC-002 | [I Ching Domain Model](domain-model/spec.md) | `verified` |
| SPEC-004 | [Casting Engine](casting-engine/spec.md) | `verified` |
| SPEC-005 | [Readings](readings/spec.md) | `verified` |
| SPEC-006 | [Consultation API](consultation-api/spec.md) | `verified` |
| SPEC-003 | [Hexagram Explorer](hexagram-explorer/spec.md) | `verified` |
| SPEC-007 | [Hexagram Explorer UI](hexagram-explorer-ui/spec.md) | `verified` |
| SPEC-009 | [Consultation Flow UI](consultation-flow-ui/spec.md) | `verified` |
| SPEC-008 | [AI Interpretation](ai-interpretation/spec.md) | `verified` |
| SPEC-010 | [Interpretation UI](interpretation-ui/spec.md) | `verified` |
