# Tasks — I Ching Domain Model (SPEC-002)

**Spec status: `verified`** — both the structural pass and the classical text pass are
complete (see `spec.md`).

## Structural pass (done)

| Task ID     | Description                                                     | Requirement(s)          | Test(s)                | Status  |
| ----------- | ------------------------------------------------------------------ | ------------------------- | ------------------------ | ------- |
| TASK-DM-002 | Implement `LinePolarity` + `Line` value object                   | REQ-LN-001, REQ-LN-002    | `LineTest`                | done |
| TASK-DM-003 | Implement `TrigramId` + `Trigram` + 8-trigram reference catalog  | REQ-TRI-001..003          | `TrigramTest`              | done |
| TASK-DM-004 | Implement `Hexagram::fromLines()` + 64-hexagram reference catalog | REQ-HX-001..003 (structural fields only) | `HexagramTest`  | done |
| TASK-DM-005 | Implement `Hexagram::getUpperTrigram()` / `getLowerTrigram()`     | REQ-HX-004                | `HexagramTest`             | done |
| TASK-DM-006 | Implement `Hexagram::changeLine()` / `getResultingHexagram()`     | REQ-HX-005..007           | `ChangingLinesTest`        | done |
| TASK-DM-007 | Implement `YijingRelations` (nuclear, opposite, complement)      | REQ-REL-001..003          | `YijingRelationsTest`      | done |
| TASK-DM-008 | Verify zero runtime dependencies beyond `php`                    | REQ-DM-001                | `composer.json` review     | done |
| TASK-DM-009 | Add PHPStan level 8 + php-cs-fixer (PSR-12) to `yijing-core`      | (coding-rules.md parity)  | `composer stan` / `lint`   | done |
| TASK-DM-010 | Wire `yijing-core` lint/stan into `scripts/verify.mjs`            | REQ-ARCH-008               | `npm run verify`           | done |
| TASK-DM-011 | Implement `Hexagram::fromKingWenNumber()` (added while building SPEC-003/006) | REQ-HX-008 | `HexagramTest` | done |

## Classical text pass (done)

| Task ID     | Description                                                     | Requirement(s)          | Test(s)                | Status  |
| ----------- | ------------------------------------------------------------------ | ------------------------- | ------------------------ | ------- |
| TASK-DM-001 | Populate judgment/image/6 line statements for all 64 hexagrams from Legge (1899, public domain), accurately transcribed | REQ-HX-003, REQ-DM-003 | `HexagramTextCatalogTest`, cross-checked against ctext.org | done |
| TASK-DM-012 | Add `Data/HexagramTextCatalog.php`, wire into `Hexagram::fromLines()` | REQ-HX-003 | `HexagramTest`, `HexagramTextCatalogTest` | done |
| TASK-DM-013 | Make `judgment`/`image`/`lineStatements` non-nullable on `Hexagram`  | REQ-DM-003 | `HexagramTest` | done |
| TASK-DM-014 | Update downstream tests that asserted the old null-placeholder state | (apps/api regression) | `HexagramControllerTest` | done |
