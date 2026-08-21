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
| SPEC-011 | [Gemini Interpretation Provider](gemini-interpretation-provider/spec.md) | `verified` |
| SPEC-012 | [AI Endpoint Rate Limiting](ai-rate-limiting/spec.md) | `verified` |
| SPEC-013 | [Consultation Notes & Tags Editing](consultation-editing/spec.md) | `verified` |
| SPEC-014 | [Complete Hexagram Relationships](hexagram-relationships/spec.md) | `verified` |
| SPEC-015 | [Hexagram Relationship Navigation](hexagram-relationship-nav/spec.md) | `verified` |
| SPEC-016 | [Visual Hexagram Editor](hexagram-editor/spec.md) | `verified` |
| SPEC-017 | [Hexagram Comparison](hexagram-comparison/spec.md) | `verified` |
| SPEC-018 | [Deep Hexagram Page](deep-hexagram-page/spec.md) | `verified` |
| SPEC-019 | [Rich Consultation Context](consultation-context/spec.md) | `verified` |
| SPEC-020 | [Consultation Outcome](consultation-outcome/spec.md) | `verified` |
| SPEC-021 | [Follow-up Consultations](consultation-follow-ups/spec.md) | `verified` |
| SPEC-022 | [Consultation Timeline](consultation-timeline/spec.md) | `verified` |
| SPEC-023 | [Repeated Pattern Detection](repeated-pattern-detection/spec.md) | `verified` |
| SPEC-024 | [Personal Statistics](personal-statistics/spec.md) | `verified` |
| SPEC-025 | [Consultation Favorites](consultation-favorites/spec.md) | `verified` |
| SPEC-026 | [Full-Text Search](full-text-search/spec.md) | `verified` |
| SPEC-027 | [Consultation Print / PDF Export](consultation-print-export/spec.md) | `verified` |
| SPEC-028 | [Consultation History Backup](history-backup/spec.md) | `verified` |
| SPEC-029 | [Consultation Public Share Link](consultation-public-share/spec.md) | `verified` |
| SPEC-030 | [Practice Journal](practice-journal/spec.md) | `verified` |
| SPEC-031 | [Hexagram Favorites](hexagram-favorites/spec.md) | `verified` |
| SPEC-032 | [Hexagram of the Day](hexagram-of-the-day/spec.md) | `verified` |
| SPEC-033 | [Multi-Lens Interpretation](multi-lens-interpretation/spec.md) | `verified` |
| SPEC-034 | [Interpretation Follow-Up Questions](interpretation-followup/spec.md) | `verified` |
| SPEC-035 | [Interpretation Profile](interpretation-profile/spec.md) | `verified` |
