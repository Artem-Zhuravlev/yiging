# Tasks — Hexagram Favorites (SPEC-031)

Each task references the requirement(s) it fulfills. Mark done only once the linked test passes.

| Task ID          | Description                                                              | Requirement(s)                | Test(s)                             | Status |
| ------------------ | -------------------------------------------------------------------------- | ---------------------------------- | ---------------------------------------- | ------ |
| TASK-HEXFAV-001 | Add migration + `HexagramFavoritesRepository`/`SqliteHexagramFavoritesRepository` | REQ-HEXFAV-001, 002        | `SqliteHexagramFavoritesRepositoryTest` | done   |
| TASK-HEXFAV-002 | Add constructor + `favorite` param to `HexagramController`, mark/unmark routes | REQ-HEXFAV-001..003, 006  | `HexagramControllerTest`                | done   |
| TASK-HEXFAV-003 | Add `apiPut`/`apiDelete` to `shared/api/http.ts`                           | (supports 007)                     | `http.spec.ts`                          | done   |
| TASK-HEXFAV-004 | Extend `entities/hexagram` types + api                                     | REQ-HEXFAV-007                     | (typechecked via page tests)            | done   |
| TASK-HEXFAV-005 | Add star toggle + "Favorites only" to `HexagramListPage.vue`               | REQ-HEXFAV-004                     | `HexagramListPage.spec.ts`              | done   |
| TASK-HEXFAV-006 | Add favorite toggle to `HexagramDetailPage.vue`                            | REQ-HEXFAV-005                     | `HexagramDetailPage.spec.ts`            | done   |
| TASK-HEXFAV-007 | Run `npm run verify`, manually verify against real running API/UI, update README + specs/README | acceptance criteria | `npm run verify`, manual | done   |
