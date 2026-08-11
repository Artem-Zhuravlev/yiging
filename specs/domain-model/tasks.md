# Tasks — I Ching Domain Model (SPEC-002)

**Spec status is `draft`.** These tasks are placeholders and MUST NOT be started until
`spec.md` is reviewed and moved to `approved`.

| Task ID     | Description                                                   | Requirement(s)                        | Test(s)                     | Status |
| ----------- | ---------------------------------------------------------------- | -------------------------------------- | ----------------------------- | ------ |
| TASK-DM-001 | Resolve classical text source/license                          | (blocks Data requirements)             | —                              | blocked |
| TASK-DM-002 | Implement `Line` value object                                  | REQ-LN-001, REQ-LN-002                 | TEST-LN-001, TEST-LN-002      | pending |
| TASK-DM-003 | Implement `Trigram` + 8-trigram reference data                 | REQ-TRI-001..003                       | TEST-TRI-001..003             | pending |
| TASK-DM-004 | Implement `Hexagram::fromLines()` + 64-hexagram reference data  | REQ-HX-001..003                        | TEST-HX-001..003              | pending |
| TASK-DM-005 | Implement `Hexagram::getUpperTrigram()` / `getLowerTrigram()`   | REQ-HX-004                              | TEST-HX-004                   | pending |
| TASK-DM-006 | Implement `Hexagram::changeLine()` / `getResultingHexagram()`   | REQ-HX-005..007                         | TEST-HX-005..007              | pending |
| TASK-DM-007 | Implement `YijingRelations` (nuclear, opposite, complement)     | REQ-REL-001..003                        | TEST-REL-001..003             | pending |
| TASK-DM-008 | Verify zero runtime dependencies beyond `php`                  | REQ-DM-001                              | `composer.json` review        | pending |
